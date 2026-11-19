<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_role('admin');
$today = date('Y-m-d');

$all = status_counts();
$total = array_sum($all);
$todayCount = (int) q_val("SELECT COUNT(*) FROM appointments WHERE appointment_date = ? AND status <> 'Cancelled'", [$today]);
$patients = (int) q_val('SELECT COUNT(*) FROM patients WHERE is_active = 1');
$newPatients = (int) q_val('SELECT COUNT(*) FROM patients WHERE created_at >= (NOW() - INTERVAL 7 DAY)');

$weekStart = week_start($today);
$weekEnd = date('Y-m-d', strtotime("$weekStart +6 days"));
$clinicLoad = q_all(
    "SELECT d.clinic AS name, COUNT(a.appointment_id) AS n
     FROM doctors d
     LEFT JOIN appointments a ON a.doctor_id = d.doctor_id AND a.appointment_date BETWEEN ? AND ? AND a.status <> 'Cancelled'
     WHERE d.is_active = 1 GROUP BY d.clinic ORDER BY n DESC, d.clinic",
    [$weekStart, $weekEnd]
);
$maxLoad = max(1, ...array_map(fn ($r) => (int) $r['n'], $clinicLoad ?: [['n' => 1]]));

$todayList = q_all(
    APPOINTMENT_SELECT . " WHERE a.appointment_date = ? AND a.status <> 'Cancelled' ORDER BY a.appointment_time LIMIT 8",
    [$today]
);
$activity = q_all(
    "SELECT h.*, p.name AS patient_name, a.appointment_id AS appointment_id
     FROM appointment_history h
     JOIN appointments a ON a.appointment_id = h.appointment_id
     JOIN patients p ON p.patient_id = a.patient_id
     ORDER BY h.created_at DESC, h.id DESC LIMIT 8"
);

app_start($user, 'Overview', [
    'active' => 'admin/dashboard.php',
    'heading' => 'Health Service overview',
    'subtitle' => date('l, j F Y') . '. A summary of appointment activity at the Health Service.',
    'actions' => '<a class="btn btn-secondary" href="' . e(url('admin/reports.php')) . '">' . icon('bar-chart', 16) . ' Reports</a>'
        . '<a class="btn btn-primary" href="' . e(url('admin/calendar.php')) . '">' . icon('calendar', 16) . ' Calendar</a>',
]);
?>
<div class="stack">
  <section class="card">
    <div class="stats cols-5">
      <div class="stat">
        <div class="stat-label"><?= icon('calendar', 15) ?> All appointments</div>
        <div class="stat-value"><?= $total ?></div>
        <div class="stat-foot"><?= $todayCount ?> scheduled today</div>
      </div>
      <div class="stat">
        <div class="stat-label"><span class="badge-dot" style="color:var(--amber-dot)"></span> Pending</div>
        <div class="stat-value"><?= $all['Pending'] + $all['Rescheduled'] ?></div>
        <div class="stat-foot"><?= $all['Rescheduled'] ?> rescheduled</div>
      </div>
      <div class="stat">
        <div class="stat-label"><span class="badge-dot" style="color:var(--green-dot)"></span> Approved</div>
        <div class="stat-value"><?= $all['Approved'] ?></div>
        <div class="stat-foot">Confirmed with patients</div>
      </div>
      <div class="stat">
        <div class="stat-label"><span class="badge-dot" style="color:var(--red-dot)"></span> Cancelled</div>
        <div class="stat-value"><?= $all['Cancelled'] ?></div>
        <div class="stat-foot"><?= $total ? round($all['Cancelled'] / $total * 100) : 0 ?>% of all</div>
      </div>
      <div class="stat">
        <div class="stat-label"><?= icon('users', 15) ?> Patients</div>
        <div class="stat-value"><?= $patients ?></div>
        <div class="stat-foot"><?= $newPatients ?> joined this week</div>
      </div>
    </div>
    <div class="card-footer" style="background:var(--surface)">
      <?= status_breakdown($all) ?>
    </div>
  </section>

  <div class="grid grid-2">
    <section class="card">
      <div class="card-header">
        <div><h2>Today</h2><p class="hint"><?= e(date('l, j F')) ?></p></div>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/appointments.php?from=' . $today . '&to=' . $today)) ?>">View all <?= icon('arrow-right', 14) ?></a>
      </div>
      <?php if ($todayList): ?>
        <ul class="list">
          <?php foreach ($todayList as $a): ?>
            <li>
              <a class="list-item" href="<?= e(url('appointment.php?id=' . $a['appointment_id'])) ?>">
                <span class="time-chip"><?= e(fmt_time($a['appointment_time'])) ?></span>
                <div class="list-main">
                  <div class="list-title"><?= e($a['patient_name']) ?></div>
                  <div class="list-sub"><?= e($a['doctor_name']) ?> · <?= e($a['clinic_name']) ?></div>
                </div>
                <?= status_badge($a['status']) ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <?= empty_state('calendar', 'Nothing scheduled today', 'Appointments booked for today will be listed here.') ?>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-header"><div><h2>Load by clinic</h2><p class="hint">Active appointments this week</p></div></div>
      <div class="card-body">
        <?php if ($clinicLoad): ?>
          <div class="bar-list">
            <?php foreach ($clinicLoad as $c): ?>
              <div class="bar-row">
                <span><?= e($c['name']) ?></span>
                <span class="num muted"><?= (int) $c['n'] ?></span>
                <div class="bar-track"><div class="bar-fill" style="width:<?= round((int) $c['n'] / $maxLoad * 100, 1) ?>%"></div></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p class="muted">No doctors yet. <a class="link-accent" href="<?= e(url('admin/doctors.php?id=new')) ?>">Add a doctor</a></p>
        <?php endif; ?>
      </div>
    </section>
  </div>

  <section class="card">
    <div class="card-header"><div><h2>Recent activity</h2><p class="hint">Latest bookings and status changes</p></div></div>
    <?php if ($activity): ?>
      <ul class="list">
        <?php foreach ($activity as $h): ?>
          <?php $label = $h['from_status'] === null ? 'New request' : ($h['to_status'] === 'Rescheduled' ? 'Rescheduled' : $h['to_status']); ?>
          <li>
            <a class="list-item" href="<?= e(url('appointment.php?id=' . $h['appointment_id'])) ?>">
              <span class="avatar avatar-sm"><?= e(initials($h['patient_name'])) ?></span>
              <div class="list-main">
                <div class="list-title"><?= e($h['patient_name']) ?> <span class="muted" style="font-weight:400">· <?= e($label) ?> by <?= e(strtolower(role_label($h['actor_role']))) ?></span></div>
                <div class="list-sub"><?= e(time_ago($h['created_at'])) ?></div>
              </div>
              <?= status_badge($h['to_status']) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <?= empty_state('activity', 'No activity yet', 'Bookings and status changes will appear here as they happen.') ?>
    <?php endif; ?>
  </section>
</div>
<?php app_end();
