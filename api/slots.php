<?php
/*
 * Returns the time slots for one doctor on one date as JSON.
 * Used by the booking page; requires a signed-in patient or administrator.
 */
require dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body);
    exit;
}

$user = current_user();
if (!$user || !in_array($user['role'], ['patient', 'admin'], true)) {
    respond(401, ['error' => 'Sign in again to see available times.']);
}

$doctorId = to_int(query('doctor'));
$date = query('date');
$exceptId = to_int(query('except'));

if (!$doctorId || !valid_date($date)) {
    respond(422, ['error' => 'Choose a doctor and a date.']);
}
[$first, $last] = booking_window();
if ($date < $first || $date > $last) {
    respond(422, ['error' => 'That date is outside the booking window.']);
}

// Patients may only exclude their own appointment (when rescheduling).
if ($exceptId && $user['role'] === 'patient') {
    $own = q_val('SELECT COUNT(*) FROM appointments WHERE appointment_id = ? AND patient_id = ?', [$exceptId, $user['id']]);
    if (!$own) {
        $exceptId = 0;
    }
}

$doctor = q_one('SELECT * FROM doctors WHERE doctor_id = ? AND is_active = 1', [$doctorId]);
if (!$doctor) {
    respond(404, ['error' => 'That doctor is not taking appointments.']);
}

$slots = slots_for($doctor, $date, $exceptId);
respond(200, [
    'date' => $date,
    'label' => fmt_date($date),
    'works' => doctor_works_on($doctor, $date),
    'slots' => $slots,
    'available' => count(array_filter($slots, fn ($s) => $s['available'])),
]);
