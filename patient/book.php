<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_role('patient');

// Rescheduling reuses this page with the existing appointment preselected.
$existing = null;
if ($rid = to_int($_GET['reschedule'] ?? $_POST['reschedule'] ?? '')) {
    $existing = find_appointment($rid);
    if (!$existing || (int) $existing['patient_id'] !== (int) $user['id']) {
        render_error_page(404, 'Appointment not found', 'It may have been removed, or it belongs to another account.');
    }
    if (!can_patient_change($existing)) {
        flash('error', 'This appointment can no longer be rescheduled online.');
        redirect('appointment.php?id=' . $existing['appointment_id']);
    }
}

$selected = [
    'clinic' => $existing ? $existing['clinic_name'] : query('clinic'),
    'doctor' => $existing ? (int) $existing['doctor_id'] : to_int(query('doctor')),
    'date' => '',
    'time' => '',
    'reason' => $existing['reason'] ?? '',
];
$errors = [];

if (is_post()) {
    verify_csrf();
    $selected = [
        'clinic' => post('clinic'),
        'doctor' => to_int(post('doctor_id')),
        'date' => post('date'),
        'time' => post('time'),
        'reason' => post('reason'),
    ];
    if (!$selected['doctor'] || $selected['date'] === '' || $selected['time'] === '') {
        $errors[] = 'Choose a doctor, a date and a time.';
    } else {
        $result = book_appointment($user, $selected['doctor'], $selected['date'], $selected['time'], $selected['reason'], $existing);
        if (isset($result['appointment_id'])) {
            flash('success', $existing
                ? 'Your new time has been sent to the doctor for confirmation.'
                : 'Your request has been sent. You will be notified when the doctor confirms it.');
            redirect('appointment.php?id=' . $result['appointment_id']);
        }
        $errors[] = $result['error'];
        $selected['time'] = '';
    }
}

// Clinic types come from the clinic field of active doctors (Table 3.2).
$clinics = q_all('SELECT clinic AS name, COUNT(*) AS doctors FROM doctors WHERE is_active = 1 GROUP BY clinic ORDER BY clinic');
$doctors = array_map(fn ($d) => [
    'id' => (int) $d['doctor_id'],
    'clinic' => $d['clinic'],
    'name' => $d['name'],
    'specialty' => $d['specialty'],
    'days' => doctor_work_days($d),
    'hours' => fmt_time($d['start_time']) . ' to ' . fmt_time($d['end_time']),
    'daysLabel' => describe_work_days($d['work_days']),
    'minutes' => (int) $d['slot_minutes'],
    'initials' => initials($d['name']),
], q_all('SELECT * FROM doctors WHERE is_active = 1 ORDER BY name'));

[$first, $last] = booking_window();
$dates = [];
for ($ts = strtotime($first); date('Y-m-d', $ts) <= $last; $ts = strtotime('+1 day', $ts)) {
    $dates[] = ['value' => date('Y-m-d', $ts), 'dow' => (int) date('N', $ts)];
}

$bookingData = [
    'doctors' => $doctors,
    'selected' => $selected,
    'slotsUrl' => url('api/slots.php'),
    'except' => $existing ? (int) $existing['appointment_id'] : 0,
];

$title = $existing ? 'Reschedule appointment' : 'Book appointment';
app_start($user, $title, [
    'active' => $existing ? 'patient/appointments.php' : 'patient/book.php',
    'subtitle' => $existing
        ? 'Currently ' . fmt_date($existing['appointment_date']) . ' at ' . fmt_time($existing['appointment_time']) . ' with ' . $existing['doctor_name'] . '. Pick a new time below.'
        : 'Choose the clinic type, a doctor and a free time. The doctor will confirm your request.',
    'back' => $existing ? [url('appointment.php?id=' . $existing['appointment_id']), 'Back to appointment'] : null,
    'errors' => $errors,
]);
?>
<noscript><div class="alert alert-warning" style="margin-bottom:20px"><?= icon('circle-alert', 18) ?><div class="alert-body">Turn on JavaScript in your browser to see available times.</div></div></noscript>

<?php if (!$clinics): ?>
  <div class="card"><?= empty_state('stethoscope', 'No doctors are taking bookings', 'Please check back later or contact the Health Service.') ?></div>
<?php else: ?>
<form method="post" class="grid grid-main-aside" data-booking novalidate>
  <?= csrf_field() ?>
  <?php if ($existing): ?><input type="hidden" name="reschedule" value="<?= (int) $existing['appointment_id'] ?>"><?php endif; ?>
  <script type="application/json" id="booking-data"><?= json_encode($bookingData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

  <div class="steps">
    <section class="card step" data-step="clinic">
      <div class="step-head">
        <span class="step-num">1</span>
        <div><h2 class="step-title">Clinic type</h2><p class="step-sub">What kind of consultation do you need?</p></div>
      </div>
      <div class="choice-grid" role="radiogroup" aria-label="Clinic type">
        <?php foreach ($clinics as $i => $c): ?>
          <div class="choice">
            <input type="radio" name="clinic" id="clinic-<?= $i ?>" value="<?= e($c['name']) ?>" <?= $selected['clinic'] === $c['name'] ? 'checked' : '' ?> data-clinic-input data-name="<?= e($c['name']) ?>">
            <label for="clinic-<?= $i ?>">
              <span class="icon-box"><?= icon('building', 16) ?></span>
              <span>
                <span class="choice-title"><?= e($c['name']) ?></span>
                <span class="choice-sub" style="display:block"><?= e(plural((int) $c['doctors'], 'doctor')) ?></span>
              </span>
              <?= icon('check-circle', 17, 'check-mark') ?>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="card step" data-step="doctor">
      <div class="step-head">
        <span class="step-num">2</span>
        <div><h2 class="step-title">Doctor</h2><p class="step-sub">Each doctor consults on set days.</p></div>
      </div>
      <div data-doctors>
        <div class="placeholder-box"><?= icon('arrow-up-right', 16) ?> Choose a clinic type first.</div>
      </div>
    </section>

    <section class="card step" data-step="date">
      <div class="step-head">
        <span class="step-num">3</span>
        <div><h2 class="step-title">Date</h2><p class="step-sub">Greyed-out days are when the doctor does not consult.</p></div>
      </div>
      <div class="date-strip" data-dates role="radiogroup" aria-label="Date">
        <?php foreach ($dates as $d): ?>
          <div class="date-opt">
            <input type="radio" name="date" id="date-<?= e($d['value']) ?>" value="<?= e($d['value']) ?>" data-dow="<?= $d['dow'] ?>" data-label="<?= e(fmt_date($d['value'])) ?>" <?= $selected['date'] === $d['value'] ? 'checked' : '' ?> disabled>
            <label for="date-<?= e($d['value']) ?>">
              <span class="w"><?= e(date('D', strtotime($d['value']))) ?></span>
              <span class="d"><?= e(date('j', strtotime($d['value']))) ?></span>
              <span class="m"><?= e(date('M', strtotime($d['value']))) ?></span>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="card step" data-step="time">
      <div class="step-head">
        <span class="step-num">4</span>
        <div><h2 class="step-title">Time</h2><p class="step-sub" data-time-sub>Crossed-out times are already taken.</p></div>
      </div>
      <div data-slots>
        <div class="placeholder-box"><?= icon('calendar', 16) ?> Choose a doctor and a date to see open times.</div>
      </div>
    </section>

    <section class="card step" data-step="reason">
      <div class="step-head">
        <span class="step-num">5</span>
        <div><h2 class="step-title">Reason for visit</h2><p class="step-sub">A short note helps the doctor prepare. Only clinic staff can see it.</p></div>
      </div>
      <div class="field">
        <label class="sr-only" for="reason">Reason for visit</label>
        <textarea class="textarea" id="reason" name="reason" maxlength="500" placeholder="For example: persistent headache for three days" <?= $existing ? '' : 'required' ?>><?= e($selected['reason']) ?></textarea>
        <span class="hint" data-reason-count>0 / 500</span>
      </div>
    </section>
  </div>

  <aside class="card summary">
    <div class="card-header"><h2><?= $existing ? 'New time' : 'Your appointment' ?></h2></div>
    <div class="card-body" style="padding-top:4px;padding-bottom:8px">
      <div class="summary-row"><?= icon('building', 16) ?><div><div class="summary-label">Clinic type</div><div class="summary-value is-empty" data-summary="clinic">Not chosen</div></div></div>
      <div class="summary-row"><?= icon('stethoscope', 16) ?><div><div class="summary-label">Doctor</div><div class="summary-value is-empty" data-summary="doctor">Not chosen</div></div></div>
      <div class="summary-row"><?= icon('calendar', 16) ?><div><div class="summary-label">Date</div><div class="summary-value is-empty" data-summary="date">Not chosen</div></div></div>
      <div class="summary-row"><?= icon('clock', 16) ?><div><div class="summary-label">Time</div><div class="summary-value is-empty" data-summary="time">Not chosen</div></div></div>
    </div>
    <div class="card-footer">
      <button class="btn btn-primary btn-lg btn-block" type="submit" data-submit disabled>
        <?= icon('check', 17) ?> <?= $existing ? 'Request new time' : 'Request appointment' ?>
      </button>
      <p class="hint" style="margin-top:10px;text-align:center">The slot is held for you as soon as you submit.</p>
    </div>
  </aside>
</form>
<?php endif; ?>
<?php app_end();
