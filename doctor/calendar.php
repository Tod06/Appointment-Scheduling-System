<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_role('doctor');
$week = query('week');
$weekStart = week_start(valid_date($week) ? $week : date('Y-m-d'));
$weekEnd = date('Y-m-d', strtotime("$weekStart +6 days"));
$showCancelled = query('cancelled') === '1';

$sql = APPOINTMENT_SELECT . ' WHERE a.doctor_id = ? AND a.appointment_date BETWEEN ? AND ?'
    . ($showCancelled ? '' : " AND a.status <> 'Cancelled'")
    . ' ORDER BY a.appointment_date, a.appointment_time';
$appointments = q_all($sql, [$user['id'], $weekStart, $weekEnd]);

app_start($user, 'Week calendar', [
    'active' => 'doctor/calendar.php',
    'subtitle' => plural(count($appointments), 'appointment') . ' between ' . date('j M', strtotime($weekStart)) . ' and ' . date('j M', strtotime($weekEnd)) . '.',
]);
?>
<section class="card">
  <div class="card-header">
    <?= week_nav($weekStart) ?>
    <a class="btn btn-ghost btn-sm" href="<?= e(query_with(['cancelled' => $showCancelled ? null : '1'])) ?>">
      <?= icon($showCancelled ? 'eye-off' : 'eye', 15) ?> <?= $showCancelled ? 'Hide' : 'Show' ?> cancelled
    </a>
  </div>
  <?= week_calendar($appointments, $weekStart, 'doctor') ?>
</section>
<?php app_end();
