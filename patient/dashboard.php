<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_role('patient');
send_due_reminders((int) $user['id']);

$active = placeholders(ACTIVE_STATUSES);
$upcoming = q_all(
    APPOINTMENT_SELECT . " WHERE a.patient_id = ? AND a.status IN ($active)
      AND TIMESTAMP(a.appointment_date, a.appointment_time) >= NOW()
    ORDER BY a.appointment_date, a.appointment_time LIMIT 6",
    array_merge([$user['id']], ACTIVE_STATUSES)
);
$next = $upcoming[0] ?? null;
$later = array_slice($upcoming, 1);

$counts = status_counts('a.patient_id = ?', [$user['id']]);
$upcomingCount = (int) q_val(
    "SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND status IN ($active) AND TIMESTAMP(appointment_date, appointment_time) >= NOW()",
    array_merge([$user['id']], ACTIVE_STATUSES)
);
$notifications = q_all("SELECT * FROM notifications WHERE user_role = 'patient' AND user_id = ? ORDER BY created_at DESC, id DESC LIMIT 4", [$user['id']]);

$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

app_start($user, 'Overview', [
    'active' => 'patient/dashboard.php',
    'heading' => $greeting . ', ' . first_name($user['name']),
    'subtitle' => $next ? 'Your next visit is ' . strtolower(relative_day($next['appointment_date'])) . ' at ' . fmt_time($next['appointment_time']) . '.' : 'You have no upcoming appointments.',
    'actions' => '<a class="btn btn-primary" href="' . e(url('patient/book.php')) . '">' . icon('calendar-plus', 16) . ' Book appointment</a>',
]);
?>
<div class="stack">
  <?php if ($next): ?>
    <section class="card">
      <div class="next-appt">
        <div class="next-date">
          <div class="m"><?= e(date('M', strtotime($next['appointment_date']))) ?></div>
          <div class="d"><?= e(date('j', strtotime($next['appointment_date']))) ?></div>
          <div class="w"><?= e(date('l', strtotime($next['appointment_date']))) ?></div>
        </div>
        <div style="min-width:0">
          <div class="row wrap" style="gap:8px">
            <span class="eyebrow">Next appointment</span>
            <?= status_badge($next['status']) ?>
          </div>
          <h2 style="font-size:20px;margin-top:6px"><?= e($next['doctor_name']) ?></h2>
          <div class="next-meta">
            <span><?= icon('clock', 15) ?> <?= e(fmt_time($next['appointment_time'])) ?></span>
            <span><?= icon('building', 15) ?> <?= e($next['clinic_name']) ?></span>
            <span><?= icon('stethoscope', 15) ?> <?= e($next['specialty']) ?></span>
          </div>
          <?php if ($next['status'] !== 'Approved'): ?>
            <p class="muted" style="margin-top:10px;font-size:13px">Waiting for the doctor to confirm. You will be notified.</p>
          <?php endif; ?>
        </div>
        <div class="next-actions row wrap">
          <a class="btn btn-secondary" href="<?= e(url('appointment.php?id=' . $next['appointment_id'])) ?>">View details</a>
          <?php if (can_patient_change($next)): ?>
            <a class="btn btn-ghost" href="<?= e(url('patient/book.php?reschedule=' . $next['appointment_id'])) ?>"><?= icon('refresh', 15) ?> Reschedule</a>
          <?php endif; ?>
        </div>
      </div>
    </section>
  <?php else: ?>
    <section class="card">
      <?= empty_state('calendar-plus', 'Nothing booked yet', 'Choose a clinic and a time that suits you. It takes about a minute.', '<a class="btn btn-primary" href="' . e(url('patient/book.php')) . '">' . icon('calendar-plus', 16) . ' Book an appointment</a>') ?>
    </section>
  <?php endif; ?>

  <section class="card">
    <div class="stats">
      <div class="stat">
        <div class="stat-label"><?= icon('calendar-clock', 15) ?> Upcoming</div>
        <div class="stat-value"><?= $upcomingCount ?></div>
      </div>
      <div class="stat">
        <div class="stat-label"><?= icon('clock', 15) ?> Awaiting confirmation</div>
        <div class="stat-value"><?= $counts['Pending'] + $counts['Rescheduled'] ?></div>
      </div>
      <div class="stat">
        <div class="stat-label"><?= icon('check-circle', 15) ?> Completed</div>
        <div class="stat-value"><?= $counts['Completed'] ?></div>
      </div>
      <div class="stat">
        <div class="stat-label"><?= icon('x-circle', 15) ?> Cancelled</div>
        <div class="stat-value"><?= $counts['Cancelled'] ?></div>
      </div>
    </div>
  </section>

  <div class="grid grid-2">
    <section class="card">
      <div class="card-header">
        <h2>Coming up</h2>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('patient/appointments.php')) ?>">View all <?= icon('arrow-right', 14) ?></a>
      </div>
      <?php if ($later): ?>
        <div class="list"><?php foreach ($later as $a) { echo appointment_list_item($a, 'patient'); } ?></div>
      <?php else: ?>
        <?= empty_state('calendar', 'No other appointments', $next ? 'Your next visit is shown above.' : 'Booked appointments will be listed here.') ?>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-header">
        <h2>Recent updates</h2>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('notifications.php')) ?>">All notifications <?= icon('arrow-right', 14) ?></a>
      </div>
      <?php if ($notifications): ?>
        <ul class="list">
          <?php foreach ($notifications as $n): ?>
            <li>
              <a class="list-item" href="<?= e(url('notifications.php?open=' . $n['id'])) ?>">
                <span class="avatar avatar-sm" style="<?= $n['is_read'] ? 'background:var(--surface-3);color:var(--ink-3)' : '' ?>"><?= icon('bell', 13) ?></span>
                <div class="list-main">
                  <div class="list-title"><?= e($n['title']) ?></div>
                  <div class="list-sub"><?= e(time_ago($n['created_at'])) ?></div>
                </div>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <?= empty_state('inbox', 'No updates yet', 'Confirmations and reminders will appear here.') ?>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php app_end();
