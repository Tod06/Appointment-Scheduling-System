<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_role('admin');

$status = in_array(query('status'), STATUSES, true) ? query('status') : '';
$clinic = query('clinic');
$doctorId = to_int(query('doctor'));
$from = valid_date(query('from')) ? query('from') : '';
$to = valid_date(query('to')) ? query('to') : '';
$search = mb_substr(query('q'), 0, 80);

$where = ['1=1'];
$params = [];
if ($status !== '') { $where[] = 'a.status = ?'; $params[] = $status; }
if ($clinic !== '') { $where[] = 'a.clinic_type = ?'; $params[] = $clinic; }
if ($doctorId) { $where[] = 'a.doctor_id = ?'; $params[] = $doctorId; }
if ($from !== '') { $where[] = 'a.appointment_date >= ?'; $params[] = $from; }
if ($to !== '') { $where[] = 'a.appointment_date <= ?'; $params[] = $to; }
if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.matric_no LIKE ? OR p.email LIKE ?)';
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
    array_push($params, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$total = (int) q_val(
    "SELECT COUNT(*) FROM appointments a JOIN patients p ON p.patient_id = a.patient_id JOIN doctors d ON d.doctor_id = a.doctor_id WHERE $whereSql",
    $params
);
$p = paginate($total, 25);
$rows = q_all(
    APPOINTMENT_SELECT . " WHERE $whereSql ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}",
    $params
);

$clinics = clinic_types(false);
$doctors = q_all('SELECT doctor_id, name FROM doctors ORDER BY name');
$filtered = $status || $clinic !== '' || $doctorId || $from || $to || $search;

app_start($user, 'Appointments', [
    'active' => 'admin/appointments.php',
    'subtitle' => 'Search and manage every appointment. Open one to change its status.',
    'actions' => '<a class="btn btn-secondary" href="' . e(url('admin/reports.php' . ($filtered ? query_with(['page' => null, 'q' => null]) : ''))) . '">' . icon('bar-chart', 16) . ' Report for this view</a>',
]);
?>
<section class="card">
  <form class="filters" method="get">
    <div class="field grow">
      <label class="label" for="q">Patient</label>
      <div class="input-group"><?= icon('search', 15, 'icon-lead') ?><input class="input" id="q" name="q" value="<?= e($search) ?>" placeholder="Name, matric number or email"></div>
    </div>
    <div class="field">
      <label class="label" for="status">Status</label>
      <select class="select" id="status" name="status" data-autosubmit>
        <option value="">All</option>
        <?php foreach (STATUSES as $s): ?><option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label class="label" for="clinic">Clinic</label>
      <select class="select" id="clinic" name="clinic" data-autosubmit>
        <option value="">All</option>
        <?php foreach ($clinics as $c): ?><option value="<?= e($c) ?>" <?= $clinic === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label class="label" for="doctor">Doctor</label>
      <select class="select" id="doctor" name="doctor" data-autosubmit>
        <option value="">All</option>
        <?php foreach ($doctors as $d): ?><option value="<?= (int) $d['doctor_id'] ?>" <?= $doctorId === (int) $d['doctor_id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label class="label" for="from">From</label><input class="input" type="date" id="from" name="from" value="<?= e($from) ?>"></div>
    <div class="field"><label class="label" for="to">To</label><input class="input" type="date" id="to" name="to" value="<?= e($to) ?>"></div>
    <button class="btn btn-secondary" type="submit" style="height:34px"><?= icon('filter', 15) ?> Apply</button>
    <?php if ($filtered): ?><a class="btn btn-ghost" style="height:34px" href="?">Clear</a><?php endif; ?>
  </form>
  <?php if ($rows): ?>
    <?= appointments_table($rows, 'admin') ?>
    <?= pagination_bar($p) ?>
  <?php else: ?>
    <?= empty_state('search', $filtered ? 'No matching appointments' : 'No appointments yet', $filtered ? 'Try a different filter or clear the search.' : 'Appointments appear here as soon as patients book.') ?>
  <?php endif; ?>
</section>
<?php app_end();
