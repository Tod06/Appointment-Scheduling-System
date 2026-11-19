<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_role('admin');

$from = valid_date(query('from')) ? query('from') : date('Y-m-01');
$to = valid_date(query('to')) ? query('to') : date('Y-m-t');
if ($to < $from) {
    [$from, $to] = [$to, $from];
}
$clinic = query('clinic');
$doctorId = to_int(query('doctor'));
$status = in_array(query('status'), STATUSES, true) ? query('status') : '';

$where = ['a.appointment_date BETWEEN ? AND ?'];
$params = [$from, $to];
if ($clinic !== '') { $where[] = 'd.clinic = ?'; $params[] = $clinic; }
if ($doctorId) { $where[] = 'a.doctor_id = ?'; $params[] = $doctorId; }
if ($status !== '') { $where[] = 'a.status = ?'; $params[] = $status; }
$whereSql = implode(' AND ', $where);

/* ---------- CSV export ---------- */
if (query('export') === 'csv') {
    audit((int) $user['id'], 'report.export', 'report', null, "CSV $from to $to");
    $rows = q_all(APPOINTMENT_SELECT . " WHERE $whereSql ORDER BY a.appointment_date, a.appointment_time", $params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="uhs-appointments-' . $from . '-to-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // lets Excel detect UTF-8
    // Stop spreadsheet apps from treating text as a formula.
    $cell = fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'" . $v : (string) $v;
    fputcsv($out, ['Reference', 'Date', 'Time', 'Status', 'Patient', 'Matric number', 'Email', 'Phone', 'Clinic', 'Doctor', 'Reason', 'Booked on']);
    foreach ($rows as $r) {
        fputcsv($out, array_map($cell, [
            str_pad((string) $r['appointment_id'], 5, '0', STR_PAD_LEFT), $r['appointment_date'], substr($r['appointment_time'], 0, 5), $r['status'],
            $r['patient_name'], $r['matric_no'] ?? '', $r['patient_email'], $r['patient_phone'], $r['clinic_name'], $r['doctor_name'],
            $r['reason'], $r['created_at'],
        ]));
    }
    fclose($out);
    exit;
}

/* ---------- Summary ---------- */
$counts = status_counts($whereSql, $params);
$total = array_sum($counts);
$completionBase = $counts['Completed'] + $counts['Cancelled'];
$completionRate = $completionBase ? round($counts['Completed'] / $completionBase * 100) : 0;
$uniquePatients = (int) q_val("SELECT COUNT(DISTINCT a.patient_id) FROM appointments a JOIN doctors d ON d.doctor_id = a.doctor_id WHERE $whereSql", $params);

$byClinic = q_all(
    "SELECT d.clinic AS name, COUNT(*) AS n, SUM(a.status = 'Completed') AS done, SUM(a.status = 'Cancelled') AS cancelled
     FROM appointments a JOIN doctors d ON d.doctor_id = a.doctor_id
     WHERE $whereSql GROUP BY d.clinic ORDER BY n DESC",
    $params
);
$byDoctor = q_all(
    "SELECT d.name, d.clinic AS clinic_name, COUNT(*) AS n,
            SUM(a.status = 'Completed') AS done, SUM(a.status = 'Cancelled') AS cancelled,
            SUM(a.status IN ('Pending','Rescheduled')) AS pending
     FROM appointments a JOIN doctors d ON d.doctor_id = a.doctor_id
     WHERE $whereSql GROUP BY d.doctor_id ORDER BY n DESC",
    $params
);
$byWeekday = array_fill(1, 7, 0);
foreach (q_all("SELECT DAYOFWEEK(a.appointment_date) AS dow, COUNT(*) AS n FROM appointments a JOIN doctors d ON d.doctor_id = a.doctor_id WHERE $whereSql GROUP BY dow", $params) as $r) {
    $iso = ((int) $r['dow'] + 5) % 7 + 1; // MySQL: 1 = Sunday; convert to 1 = Monday
    $byWeekday[$iso] = (int) $r['n'];
}
$maxClinic = max(1, ...array_map(fn ($r) => (int) $r['n'], $byClinic ?: [['n' => 1]]));
$maxWeekday = max(1, ...array_values($byWeekday));

$clinics = clinic_types(false);
$doctors = q_all('SELECT doctor_id, name FROM doctors ORDER BY name');
$period = date('j M Y', strtotime($from)) . ' to ' . date('j M Y', strtotime($to));

app_start($user, 'Reports', [
    'active' => 'admin/reports.php',
    'subtitle' => 'Appointment summary for ' . $period . '.',
    'actions' => '<button class="btn btn-secondary no-print" type="button" onclick="window.print()">' . icon('printer', 16) . ' Print</button>'
        . '<a class="btn btn-primary no-print" href="' . e(query_with(['export' => 'csv'])) . '">' . icon('download', 16) . ' Export CSV</a>',
]);
?>
<div class="print-only" style="margin-bottom:16px">
  <strong><?= e(config('org_name')) ?></strong><br>
  Appointment report, <?= e($period) ?>. Generated <?= e(date('j M Y, g:i A')) ?> by <?= e($user['name']) ?>.
</div>

<div class="stack">
  <section class="card no-print">
    <form class="filters" method="get" style="border-bottom:0">
      <div class="field"><label class="label" for="from">From</label><input class="input" type="date" id="from" name="from" value="<?= e($from) ?>"></div>
      <div class="field"><label class="label" for="to">To</label><input class="input" type="date" id="to" name="to" value="<?= e($to) ?>"></div>
      <div class="field">
        <label class="label" for="clinic">Clinic</label>
        <select class="select" id="clinic" name="clinic">
          <option value="">All clinics</option>
          <?php foreach ($clinics as $c): ?><option value="<?= e($c) ?>" <?= $clinic === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="label" for="doctor">Doctor</label>
        <select class="select" id="doctor" name="doctor">
          <option value="">All doctors</option>
          <?php foreach ($doctors as $d): ?><option value="<?= (int) $d['doctor_id'] ?>" <?= $doctorId === (int) $d['doctor_id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="label" for="status">Status</label>
        <select class="select" id="status" name="status">
          <option value="">All</option>
          <?php foreach (STATUSES as $s): ?><option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-primary" type="submit" style="height:34px"><?= icon('refresh', 15) ?> Update report</button>
      <div class="row wrap" style="gap:6px">
        <a class="btn btn-ghost btn-sm" href="?from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>">Today</a>
        <a class="btn btn-ghost btn-sm" href="?from=<?= week_start(date('Y-m-d')) ?>&to=<?= date('Y-m-d', strtotime(week_start(date('Y-m-d')) . ' +6 days')) ?>">This week</a>
        <a class="btn btn-ghost btn-sm" href="?from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-t') ?>">This month</a>
      </div>
    </form>
  </section>

  <section class="card">
    <div class="stats">
      <div class="stat"><div class="stat-label"><?= icon('calendar', 15) ?> Appointments</div><div class="stat-value"><?= $total ?></div><div class="stat-foot"><?= e($period) ?></div></div>
      <div class="stat"><div class="stat-label"><?= icon('users', 15) ?> Patients seen or booked</div><div class="stat-value"><?= $uniquePatients ?></div><div class="stat-foot">Unique patients</div></div>
      <div class="stat"><div class="stat-label"><?= icon('check-circle', 15) ?> Completed</div><div class="stat-value"><?= $counts['Completed'] ?></div><div class="stat-foot"><?= $completionRate ?>% of closed appointments</div></div>
      <div class="stat"><div class="stat-label"><?= icon('x-circle', 15) ?> Cancelled</div><div class="stat-value"><?= $counts['Cancelled'] ?></div><div class="stat-foot"><?= $total ? round($counts['Cancelled'] / $total * 100) : 0 ?>% of all</div></div>
    </div>
    <div class="card-footer" style="background:var(--surface)"><?= status_breakdown($counts) ?></div>
  </section>

  <div class="grid grid-2">
    <section class="card">
      <div class="card-header"><h2>By clinic</h2></div>
      <div class="card-body">
        <?php if ($byClinic): ?>
          <div class="bar-list">
            <?php foreach ($byClinic as $r): ?>
              <div class="bar-row">
                <span><?= e($r['name']) ?></span>
                <span class="num muted"><?= (int) $r['n'] ?> <span style="font-size:12px">· <?= (int) $r['done'] ?> done, <?= (int) $r['cancelled'] ?> cancelled</span></span>
                <div class="bar-track"><div class="bar-fill" style="width:<?= round((int) $r['n'] / $maxClinic * 100, 1) ?>%"></div></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?><p class="muted">No appointments in this period.</p><?php endif; ?>
      </div>
    </section>
    <section class="card">
      <div class="card-header"><div><h2>By day of the week</h2><p class="hint">Helps plan staffing for busy days.</p></div></div>
      <div class="card-body">
        <div class="bar-list">
          <?php foreach ($byWeekday as $dow => $n): ?>
            <div class="bar-row">
              <span><?= e(WEEKDAY_NAMES[$dow]) ?></span>
              <span class="num muted"><?= $n ?></span>
              <div class="bar-track"><div class="bar-fill" style="width:<?= round($n / $maxWeekday * 100, 1) ?>%;<?= $n ? '' : 'min-width:0' ?>"></div></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  </div>

  <section class="card">
    <div class="card-header"><h2>By doctor</h2></div>
    <?php if ($byDoctor): ?>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Doctor</th><th class="hide-sm">Clinic</th><th>Total</th><th>Awaiting review</th><th>Completed</th><th>Cancelled</th></tr></thead>
          <tbody>
            <?php foreach ($byDoctor as $r): ?>
              <tr>
                <td class="cell-main"><?= e($r['name']) ?></td>
                <td class="hide-sm"><?= e($r['clinic_name']) ?></td>
                <td class="num"><?= (int) $r['n'] ?></td>
                <td class="num"><?= (int) $r['pending'] ?></td>
                <td class="num"><?= (int) $r['done'] ?></td>
                <td class="num"><?= (int) $r['cancelled'] ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <?= empty_state('bar-chart', 'No data for this period', 'Try a wider date range or remove a filter.') ?>
    <?php endif; ?>
  </section>
</div>
<?php app_end();
