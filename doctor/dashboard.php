<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_role('doctor');
$today = date('Y-m-d');

$todayList = q_all(
    APPOINTMENT_SELECT . " WHERE a.doctor_id = ? AND a.appointment_date = ? AND a.status <> 'Cancelled' ORDER BY a.appointment_time",
    [$user['id'], $today]
);
$requests = q_all(
    APPOINTMENT_SELECT . " WHERE a.doctor_id = ? AND a.status IN ('Pending', 'Rescheduled')
      AND TIMESTAMP(a.appointment_date, a.appointment_time) >= NOW()
    ORDER BY a.appointment_date, a.appointment_time LIMIT 8",
    [$user['id']]
);
$requestCount = (int) q_val(
    "SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status IN ('Pending', 'Rescheduled') AND TIMESTAMP(appointment_date, appointment_time) >= NOW()",
    [$user['id']]
);
$weekStart = week_start($today);
$weekEnd = date('Y-m-d', strtotime("$weekStart +6 days"));
$weekCount = (int) q_val(
    "SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date BETWEEN ? AND ? AND status IN ('Approved', 'Completed')",
    [$user['id'], $weekStart, $weekEnd]
);
$completedToday = count(array_filter($todayList, fn ($a) => $a['status'] === 'Completed'));
$worksToday = doctor_works_on($user, $today);

$returnTo = current_path();

app_start($user, 'Today', [
    'active' => 'doctor/dashboard.php',
    'heading' => date('l, j F'),
    'subtitle' => $worksToday
        ? 'You consult today from ' . fmt_time($user['start_time']) . ' to ' . fmt_time($user['end_time']) . '.'
        : 'You do not consult today. Your days are ' . describe_work_days($user['work_days']) . '.',
    'actions' => '<a class="btn btn-secondary" href="' . e(url('doctor/calendar.php')) . '">' . icon('calendar', 16) . ' Week calendar</a>',
]);
?>
<div class="stack">
  <section class="card">
    <div class="stats">
      <div class="stat">
        <div class="stat-label"><?= icon('calendar-check', 15) ?> Today</div>
        <div class="stat-value"><?= count($todayList) ?></div>
        <div class="stat-foot"><?= $completedToday ?> completed</div>
      </div>
      <div class="stat">
        <div class="stat-label"><?= icon('inbox', 15) ?> Awaiting your review</div>
        <div class="stat-value"><?= $requestCount ?></div>
        <div class="stat-foot">New and rescheduled requests</div>
      </div>
      <div class="stat">
        <div class="stat-label"><?= icon('calendar', 15) ?> Confirmed this week</div>
        <div class="stat-value"><?= $weekCount ?></div>
        <div class="stat-foot"><?= e(date('j M', strtotime($weekStart))) ?> to <?= e(date('j M', strtotime($weekEnd))) ?></div>
      </div>
      <div class="stat">
        <div class="stat-label"><?= icon('clock', 15) ?> Slot length</div>
        <div class="stat-value"><?= (int) $user['slot_minutes'] ?><span style="font-size:15px;color:var(--ink-3);margin-left:4px">min</span></div>
        <div class="stat-foot"><a class="link-accent" href="<?= e(url('doctor/availability.php')) ?>">Change availability</a></div>
      </div>
    </div>
  </section>

  <div class="grid grid-2">
    <section class="card">
      <div class="card-header">
        <div><h2>Today's schedule</h2><p class="hint">Excludes cancelled appointments.</p></div>
      </div>
      <?php if ($todayList): ?>
        <ul class="list">
          <?php foreach ($todayList as $a): ?>
            <li class="list-item">
              <span class="time-chip"><?= e(fmt_time($a['appointment_time'])) ?></span>
              <div class="list-main">
                <a class="list-title" href="<?= e(url('appointment.php?id=' . $a['appointment_id'])) ?>" style="display:block"><?= e($a['patient_name']) ?></a>
                <div class="list-sub"><?= e($a['reason'] ?: 'No reason given') ?></div>
              </div>
              <?php if ($a['status'] === 'Approved' && in_array('Completed', allowed_transitions($user, $a), true)): ?>
                <form method="post" action="<?= e(url('appointment.php')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int) $a['appointment_id'] ?>">
                  <input type="hidden" name="return" value="<?= e($returnTo) ?>">
                  <button class="btn btn-secondary btn-sm" type="submit" name="status" value="Completed"><?= icon('check', 14) ?> Done</button>
                </form>
              <?php else: ?>
                <?= status_badge($a['status']) ?>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <?= empty_state('calendar', 'No appointments today', $worksToday ? 'Approved requests for today will appear here.' : 'Enjoy your day off.') ?>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-header">
        <div><h2>Awaiting your review</h2><p class="hint">Approve to confirm the time with the patient.</p></div>
        <?php if ($requestCount > count($requests)): ?>
          <a class="btn btn-ghost btn-sm" href="<?= e(url('doctor/appointments.php?status=Pending')) ?>">View all <?= icon('arrow-right', 14) ?></a>
        <?php endif; ?>
      </div>
      <?php if ($requests): ?>
        <ul class="list">
          <?php foreach ($requests as $a): ?>
            <li class="list-item">
              <?= date_chip($a['appointment_date']) ?>
              <div class="list-main">
                <a class="list-title" href="<?= e(url('appointment.php?id=' . $a['appointment_id'])) ?>" style="display:block"><?= e($a['patient_name']) ?></a>
                <div class="list-sub"><?= e(fmt_time($a['appointment_time'])) ?><?= $a['status'] === 'Rescheduled' ? ' · Rescheduled' : '' ?> · <?= e($a['reason'] ?: 'No reason given') ?></div>
              </div>
              <form method="post" action="<?= e(url('appointment.php')) ?>" class="row" style="gap:6px">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $a['appointment_id'] ?>">
                <input type="hidden" name="return" value="<?= e($returnTo) ?>">
                <button class="btn btn-ghost btn-sm btn-icon" type="submit" name="status" value="Cancelled" title="Decline" aria-label="Decline request from <?= e($a['patient_name']) ?>" data-confirm="Decline this request? The patient will be notified."><?= icon('x', 15) ?></button>
                <button class="btn btn-primary btn-sm" type="submit" name="status" value="Approved"><?= icon('check', 14) ?> Approve</button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <?= empty_state('check-circle', 'You are all caught up', 'New appointment requests will appear here.') ?>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php app_end();
