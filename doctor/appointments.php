<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_role('doctor');

$status = in_array(query('status'), STATUSES, true) ? query('status') : '';
$from = valid_date(query('from')) ? query('from') : '';
$to = valid_date(query('to')) ? query('to') : '';
$search = mb_substr(query('q'), 0, 80);

$where = ['a.doctor_id = ?'];
$params = [$user['id']];
if ($status !== '') {
    $where[] = $status === 'Pending' ? "a.status IN ('Pending', 'Rescheduled')" : 'a.status = ?';
    if ($status !== 'Pending') {
        $params[] = $status;
    }
}
if ($from !== '') { $where[] = 'a.appointment_date >= ?'; $params[] = $from; }
if ($to !== '') { $where[] = 'a.appointment_date <= ?'; $params[] = $to; }
if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.matric_no LIKE ? OR p.email LIKE ?)';
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
    array_push($params, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$total = (int) q_val("SELECT COUNT(*) FROM appointments a JOIN patients p ON p.patient_id = a.patient_id WHERE $whereSql", $params);
$p = paginate($total, 20);
$rows = q_all(
    APPOINTMENT_SELECT . " WHERE $whereSql ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}",
    $params
);

app_start($user, 'Appointments', [
    'active' => 'doctor/appointments.php',
    'subtitle' => 'Every appointment booked with you, newest first.',
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
        <option value="">All statuses</option>
        <?php foreach (STATUSES as $s): ?>
          <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($s === 'Pending' ? 'Awaiting review' : $s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field"><label class="label" for="from">From</label><input class="input" type="date" id="from" name="from" value="<?= e($from) ?>"></div>
    <div class="field"><label class="label" for="to">To</label><input class="input" type="date" id="to" name="to" value="<?= e($to) ?>"></div>
    <button class="btn btn-secondary" type="submit" style="height:34px"><?= icon('filter', 15) ?> Apply</button>
    <?php if ($status || $from || $to || $search): ?><a class="btn btn-ghost" style="height:34px" href="?">Clear</a><?php endif; ?>
  </form>
  <?php if ($rows): ?>
    <?= appointments_table($rows, 'doctor') ?>
    <?= pagination_bar($p) ?>
  <?php else: ?>
    <?= empty_state('search', 'No matching appointments', 'Try a different filter or clear the search.') ?>
  <?php endif; ?>
</section>
<?php app_end();
