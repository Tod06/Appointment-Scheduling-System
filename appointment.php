<?php
require __DIR__ . '/includes/bootstrap.php';

$user = require_role();
$id = to_int($_GET['id'] ?? $_POST['id'] ?? '');
$appt = $id ? find_appointment($id) : null;

if (!$appt || !can_view_appointment($user, $appt)) {
    render_error_page(404, 'Appointment not found', 'It may have been removed, or it belongs to another account.');
}

if (is_post()) {
    verify_csrf();
    $to = post('status');
    $error = change_status($user, $appt, $to, post('note'));
    if ($error) {
        flash('error', $error);
    } else {
        $messages = [
            'Approved' => 'Appointment approved. The patient has been notified.',
            'Completed' => 'Appointment marked as completed.',
            'Cancelled' => $user['role'] === 'patient' ? 'Your appointment has been cancelled.' : 'Appointment cancelled. The patient has been notified.',
        ];
        flash('success', $messages[$to] ?? 'Appointment updated.');
    }
    $back = safe_local_path(post('return'));
    redirect($back ?? 'appointment.php?id=' . $appt['id']);
}

$history = q_all('SELECT * FROM appointment_history WHERE appointment_id = ? ORDER BY created_at ASC, id ASC', [$appt['id']]);
$actorNames = [];
foreach ($history as $h) {
    $key = $h['actor_role'] . ':' . $h['actor_id'];
    if (!isset($actorNames[$key]) && in_array($h['actor_role'], ROLES, true)) {
        $actorNames[$key] = (string) q_val('SELECT name FROM ' . user_table($h['actor_role']) . ' WHERE id = ?', [$h['actor_id']]);
    }
}

$transitions = allowed_transitions($user, $appt);
$canReschedule = $user['role'] === 'patient' && can_patient_change($appt);

$backLink = match ($user['role']) {
    'patient' => [url('patient/appointments.php'), 'My appointments'],
    'doctor' => [url('doctor/appointments.php'), 'Appointments'],
    'admin' => [url('admin/appointments.php'), 'All appointments'],
};

$actions = '';
if ($canReschedule) {
    $actions .= '<a class="btn btn-secondary" href="' . e(url('patient/book.php?reschedule=' . $appt['id'])) . '">' . icon('refresh', 16) . ' Reschedule</a>';
}

app_start($user, 'Appointment', [
    'active' => '',
    'heading' => $user['role'] === 'patient' ? $appt['clinic_name'] : $appt['patient_name'],
    'subtitle' => fmt_date($appt['appointment_date']) . ' at ' . fmt_time($appt['appointment_time']) . ' · Reference #' . str_pad((string) $appt['id'], 5, '0', STR_PAD_LEFT),
    'back' => $backLink,
    'actions' => $actions,
]);
?>
<div class="grid grid-main-aside">
  <div class="stack">
    <section class="card">
      <div class="card-header">
        <h2>Details</h2>
        <?= status_badge($appt['status']) ?>
      </div>
      <div class="card-body" style="padding-top:4px;padding-bottom:4px">
        <dl class="dl">
          <dt>Date</dt><dd class="num"><?= e(fmt_date($appt['appointment_date'])) ?> <span class="muted">(<?= e(relative_day($appt['appointment_date'])) ?>)</span></dd>
          <dt>Time</dt><dd class="num"><?= e(fmt_time($appt['appointment_time'])) ?> <span class="muted">· <?= (int) $appt['slot_minutes'] ?> minutes</span></dd>
          <dt>Clinic</dt><dd><?= e($appt['clinic_name']) ?></dd>
          <dt>Doctor</dt><dd><?= e($appt['doctor_name']) ?> <span class="muted">· <?= e($appt['specialty']) ?></span></dd>
          <?php if ($user['role'] !== 'patient'): ?>
            <dt>Patient</dt><dd><?= e($appt['patient_name']) ?></dd>
            <dt>Matric number</dt><dd class="mono"><?= e($appt['matric_no'] ?: 'Not provided') ?></dd>
            <dt>Contact</dt><dd><?= e($appt['patient_phone']) ?> <span class="muted">· <?= e($appt['patient_email']) ?></span></dd>
          <?php endif; ?>
          <dt>Reason for visit</dt><dd><?= nl2br(e($appt['reason'] ?: 'Not given')) ?></dd>
          <?php if ($appt['doctor_note'] !== ''): ?>
            <dt>Note from clinic</dt><dd><?= nl2br(e($appt['doctor_note'])) ?></dd>
          <?php endif; ?>
        </dl>
      </div>
    </section>

    <section class="card">
      <div class="card-header"><h2>History</h2><span class="muted" style="font-size:12.5px"><?= e(plural(count($history), 'event')) ?></span></div>
      <ol class="timeline">
        <?php foreach ($history as $h): ?>
          <?php
            $who = $actorNames[$h['actor_role'] . ':' . $h['actor_id']] ?? '';
            $who = $who !== '' ? $who . ' (' . strtolower(role_label($h['actor_role'])) . ')' : role_label($h['actor_role']);
            if ($h['from_status'] === null) {
                $title = 'Requested';
            } elseif ($h['to_status'] === 'Rescheduled' && $h['to_date']) {
                $title = 'Moved from ' . fmt_date_short($h['from_date']) . ', ' . fmt_time($h['from_time']) . ' to ' . fmt_date_short($h['to_date']) . ', ' . fmt_time($h['to_time']);
            } else {
                $title = $h['to_status'];
            }
          ?>
          <li>
            <span class="dot <?= e(strtolower($h['to_status'])) ?>"></span>
            <div class="timeline-title"><?= e($title) ?></div>
            <div class="timeline-meta"><?= e($who) ?> · <?= e(fmt_datetime($h['created_at'])) ?></div>
            <?php if ($h['note'] !== ''): ?><div class="timeline-note"><?= e($h['note']) ?></div><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  </div>

  <aside class="stack">
    <?php if ($transitions): ?>
      <section class="card">
        <div class="card-header">
          <div>
            <h2><?= $user['role'] === 'patient' ? 'Cancel this appointment' : 'Update status' ?></h2>
            <p class="hint"><?= $user['role'] === 'patient' ? 'The time will be released for other patients.' : 'The patient is notified of every change.' ?></p>
          </div>
        </div>
        <form class="card-body form-grid" method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) $appt['id'] ?>">
          <div class="field">
            <label class="label" for="note"><?= $user['role'] === 'patient' ? 'Reason' : 'Note to patient' ?> <span class="optional">Optional</span></label>
            <textarea class="textarea" id="note" name="note" maxlength="500" placeholder="<?= $user['role'] === 'patient' ? 'Let the clinic know why' : 'For example, bring previous test results' ?>"></textarea>
          </div>
          <div class="form-grid" style="gap:8px">
            <?php foreach ($transitions as $to): ?>
              <?php $cls = $to === 'Cancelled' ? 'btn-danger' : ($to === 'Approved' ? 'btn-primary' : 'btn-secondary'); ?>
              <button class="btn <?= $cls ?> btn-block" type="submit" name="status" value="<?= e($to) ?>"
                <?= $to === 'Cancelled' ? 'data-confirm="Cancel this appointment? This cannot be undone."' : '' ?>>
                <?= icon($to === 'Approved' ? 'check' : ($to === 'Completed' ? 'check-circle' : 'x'), 16) ?>
                <?= e($user['role'] === 'patient' ? 'Cancel appointment' : status_action_label($to)) ?>
              </button>
            <?php endforeach; ?>
          </div>
        </form>
      </section>
    <?php elseif ($user['role'] === 'patient' && in_array($appt['status'], ACTIVE_STATUSES, true)): ?>
      <div class="alert alert-info"><?= icon('info', 18) ?><div class="alert-body">This appointment starts soon, so it can no longer be changed online. Please call the clinic if you cannot attend.</div></div>
    <?php endif; ?>

    <section class="card card-body">
      <div class="eyebrow">Before your visit</div>
      <ul class="list" style="margin-top:10px;display:grid;gap:10px;font-size:13.5px;color:var(--ink-2)">
        <li class="row" style="align-items:flex-start"><?= icon('clock', 16, 'muted') ?> Arrive 10 minutes before your time.</li>
        <li class="row" style="align-items:flex-start"><?= icon('id-card', 16, 'muted') ?> Bring your university ID card.</li>
        <li class="row" style="align-items:flex-start"><?= icon('file-text', 16, 'muted') ?> Bring any previous prescriptions or results.</li>
      </ul>
    </section>
  </aside>
</div>
<?php app_end();
