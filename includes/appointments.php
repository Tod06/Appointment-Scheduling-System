<?php
defined('APP') || exit;

const STATUSES = ['Pending', 'Approved', 'Rescheduled', 'Completed', 'Cancelled'];
/** Statuses that hold a time slot. */
const ACTIVE_STATUSES = ['Pending', 'Approved', 'Rescheduled'];

/* ---------- Doctor availability ---------- */

function doctor_work_days(array $doctor): array
{
    return array_values(array_filter(array_map('intval', explode(',', (string) $doctor['work_days']))));
}

function doctor_works_on(array $doctor, string $date): bool
{
    return in_array((int) date('N', strtotime($date)), doctor_work_days($doctor), true);
}

/** Every slot start time ('H:i:s') in a doctor's working day. */
function doctor_slot_times(array $doctor): array
{
    $start = strtotime('2000-01-01 ' . $doctor['start_time']);
    $end = strtotime('2000-01-01 ' . $doctor['end_time']);
    $step = max(10, (int) $doctor['slot_minutes']) * 60;
    $times = [];
    for ($t = $start; $t + $step <= $end; $t += $step) {
        $times[] = date('H:i:s', $t);
    }
    return $times;
}

/** First and last dates a patient may book. */
function booking_window(): array
{
    $days = max(1, (int) config('booking_window_days', 30));
    return [date('Y-m-d'), date('Y-m-d', strtotime("+{$days} days"))];
}

/** Slot times already held by an active appointment. */
function taken_times(int $doctorId, string $date, int $exceptAppointmentId = 0): array
{
    $rows = q_all(
        'SELECT appointment_time FROM appointments
         WHERE doctor_id = ? AND appointment_date = ? AND appointment_id <> ?
           AND status IN (' . placeholders(ACTIVE_STATUSES) . ')',
        array_merge([$doctorId, $date, $exceptAppointmentId], ACTIVE_STATUSES)
    );
    return array_column($rows, 'appointment_time');
}

/**
 * Slots for one doctor on one date, each marked available or not.
 * Slots that start within 30 minutes from now are closed.
 */
function slots_for(array $doctor, string $date, int $exceptAppointmentId = 0): array
{
    if (!doctor_works_on($doctor, $date)) {
        return [];
    }
    $taken = array_flip(taken_times((int) $doctor['doctor_id'], $date, $exceptAppointmentId));
    $cutoff = time() + 30 * 60;
    $slots = [];
    foreach (doctor_slot_times($doctor) as $time) {
        $isPast = strtotime("$date $time") < $cutoff;
        $isTaken = isset($taken[$time]);
        $slots[] = [
            'time' => substr($time, 0, 5),
            'label' => fmt_time($time),
            'available' => !$isPast && !$isTaken,
            'reason' => $isTaken ? 'booked' : ($isPast ? 'past' : ''),
        ];
    }
    return $slots;
}

/** Clinic types in use, taken from the clinic field of active doctors (Table 3.2). */
function clinic_types(bool $activeOnly = true): array
{
    return array_column(q_all('SELECT DISTINCT clinic FROM doctors' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY clinic'), 'clinic');
}

/* ---------- Loading appointments ---------- */

const APPOINTMENT_SELECT = '
    SELECT a.*,
           p.name AS patient_name, p.email AS patient_email, p.phone AS patient_phone, p.matric_no,
           d.name AS doctor_name, d.specialty, d.slot_minutes, d.clinic AS clinic_name
    FROM appointments a
    JOIN patients p ON p.patient_id = a.patient_id
    JOIN doctors d ON d.doctor_id = a.doctor_id';

function find_appointment(int $id): ?array
{
    return q_one(APPOINTMENT_SELECT . ' WHERE a.appointment_id = ?', [$id]);
}

/** Whether $user may see this appointment at all. */
function can_view_appointment(array $user, array $appt): bool
{
    return match ($user['role']) {
        'admin' => true,
        'patient' => (int) $appt['patient_id'] === (int) $user['id'],
        'doctor' => (int) $appt['doctor_id'] === (int) $user['id'],
        default => false,
    };
}

function appointment_timestamp(array $appt): int
{
    return strtotime($appt['appointment_date'] . ' ' . $appt['appointment_time']);
}

function is_upcoming(array $appt): bool
{
    return appointment_timestamp($appt) > time();
}

/* ---------- Status rules ---------- */

/** Status changes $user may make on $appt (rescheduling is separate). */
function allowed_transitions(array $user, array $appt): array
{
    $status = $appt['status'];
    $started = appointment_timestamp($appt) <= time();
    $today = $appt['appointment_date'] <= date('Y-m-d');

    if ($user['role'] === 'patient') {
        return can_patient_change($appt) ? ['Cancelled'] : [];
    }

    // Doctors and administrators share the same workflow.
    $next = [];
    if (in_array($status, ['Pending', 'Rescheduled'], true)) {
        if (!$started) {
            $next[] = 'Approved';
        }
        $next[] = 'Cancelled';
    } elseif ($status === 'Approved') {
        if ($today) {
            $next[] = 'Completed';
        }
        $next[] = 'Cancelled';
    }
    return $next;
}

/** Patients may cancel or reschedule an active appointment until shortly before it starts. */
function can_patient_change(array $appt): bool
{
    $cutoff = (int) config('change_cutoff_hours', 2) * 3600;
    return in_array($appt['status'], ACTIVE_STATUSES, true)
        && appointment_timestamp($appt) - time() > $cutoff;
}

function status_action_label(string $status): string
{
    return match ($status) {
        'Approved' => 'Approve',
        'Completed' => 'Mark completed',
        'Cancelled' => 'Cancel appointment',
        default => $status,
    };
}

function log_history(int $appointmentId, ?string $from, string $to, string $role, int $actorId, string $note = '', array $move = []): void
{
    q(
        'INSERT INTO appointment_history (appointment_id, from_status, to_status, from_date, from_time, to_date, to_time, actor_role, actor_id, note)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $appointmentId, $from, $to,
            $move['from_date'] ?? null, $move['from_time'] ?? null,
            $move['to_date'] ?? null, $move['to_time'] ?? null,
            $role, $actorId, mb_substr($note, 0, 500),
        ]
    );
}

/**
 * Changes an appointment's status after checking the rules.
 * Returns null on success or an error message.
 */
function change_status(array $user, array $appt, string $to, string $note = ''): ?string
{
    if (!can_view_appointment($user, $appt) || !in_array($to, allowed_transitions($user, $appt), true)) {
        return 'That change is not allowed for this appointment.';
    }
    $note = mb_substr(trim($note), 0, 500);

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $fresh = q_one('SELECT status FROM appointments WHERE appointment_id = ? FOR UPDATE', [$appt['appointment_id']]);
        if (!$fresh || $fresh['status'] !== $appt['status']) {
            $pdo->rollBack();
            return 'This appointment was changed by someone else. Refresh to see the latest status.';
        }
        if ($user['role'] === 'patient') {
            q('UPDATE appointments SET status = ? WHERE appointment_id = ?', [$to, $appt['appointment_id']]);
        } else {
            q('UPDATE appointments SET status = ?, doctor_note = ? WHERE appointment_id = ?', [$to, $note !== '' ? $note : $appt['doctor_note'], $appt['appointment_id']]);
        }
        log_history((int) $appt['appointment_id'], $appt['status'], $to, $user['role'], (int) $user['id'], $note);
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }

    $when = fmt_date($appt['appointment_date']) . ' at ' . fmt_time($appt['appointment_time']);
    $link = 'appointment.php?id=' . $appt['appointment_id'];

    if ($user['role'] === 'patient') {
        notify('doctor', (int) $appt['doctor_id'], 'Appointment cancelled by patient', $appt['patient_name'] . ' cancelled their appointment on ' . $when . '.', $link);
    } else {
        $titles = [
            'Approved' => 'Your appointment is confirmed',
            'Completed' => 'Appointment marked as completed',
            'Cancelled' => 'Your appointment was cancelled',
        ];
        $body = $appt['doctor_name'] . ', ' . $appt['clinic_name'] . ', ' . $when . '.';
        if ($note !== '') {
            $body .= ' Note: ' . $note;
        }
        notify('patient', (int) $appt['patient_id'], $titles[$to] ?? 'Appointment updated', $body, $link);
        if ($user['role'] === 'admin') {
            audit((int) $user['id'], 'appointment.status', 'appointment', (int) $appt['appointment_id'], $appt['status'] . ' to ' . $to);
        }
    }
    return null;
}

/**
 * Books a new appointment, or moves an existing one when $existing is given.
 * The doctor row is locked during the check so two patients cannot take the same slot.
 * Returns ['appointment_id' => int] on success or ['error' => string].
 */
function book_appointment(array $patient, int $doctorId, string $date, string $time, string $reason, ?array $existing = null): array
{
    $reason = mb_substr(trim($reason), 0, 500);
    if (!valid_date($date) || !valid_time($time)) {
        return ['error' => 'Choose a valid date and time.'];
    }
    $time = normalize_time($time);
    [$first, $last] = booking_window();
    if ($date < $first || $date > $last) {
        return ['error' => 'Choose a date within the next ' . config('booking_window_days', 30) . ' days.'];
    }
    if ($reason === '' && !$existing) {
        return ['error' => 'Tell the doctor briefly why you are booking.'];
    }
    if ($existing && !can_patient_change($existing)) {
        return ['error' => 'This appointment can no longer be rescheduled.'];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $doctor = q_one(
            'SELECT * FROM doctors WHERE doctor_id = ? AND is_active = 1 FOR UPDATE',
            [$doctorId]
        );
        if (!$doctor) {
            $pdo->rollBack();
            return ['error' => 'That doctor is not taking appointments.'];
        }
        if (!doctor_works_on($doctor, $date) || !in_array($time, doctor_slot_times($doctor), true)) {
            $pdo->rollBack();
            return ['error' => 'The doctor does not consult at that time. Pick one of the listed slots.'];
        }
        if (strtotime("$date $time") < time() + 30 * 60) {
            $pdo->rollBack();
            return ['error' => 'That time has passed or starts too soon. Pick a later slot.'];
        }

        $exceptId = $existing ? (int) $existing['appointment_id'] : 0;
        $status = placeholders(ACTIVE_STATUSES);
        $clash = (int) q_val(
            "SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ?
             AND appointment_id <> ? AND status IN ($status)",
            array_merge([$doctorId, $date, $time, $exceptId], ACTIVE_STATUSES)
        );
        if ($clash) {
            $pdo->rollBack();
            return ['error' => 'Someone has just booked that slot. Choose another time.'];
        }
        $ownClash = (int) q_val(
            "SELECT COUNT(*) FROM appointments WHERE patient_id = ? AND appointment_date = ? AND appointment_time = ?
             AND appointment_id <> ? AND status IN ($status)",
            array_merge([$patient['id'], $date, $time, $exceptId], ACTIVE_STATUSES)
        );
        if ($ownClash) {
            $pdo->rollBack();
            return ['error' => 'You already have another appointment at that time.'];
        }

        if ($existing) {
            q(
                "UPDATE appointments SET doctor_id = ?, appointment_date = ?, appointment_time = ?, status = 'Rescheduled',
                 reason = ?, reminder_sent = 0 WHERE appointment_id = ?",
                [$doctorId, $date, $time, $reason !== '' ? $reason : $existing['reason'], $existing['appointment_id']]
            );
            $id = (int) $existing['appointment_id'];
            log_history($id, $existing['status'], 'Rescheduled', 'patient', (int) $patient['id'], '', [
                'from_date' => $existing['appointment_date'], 'from_time' => $existing['appointment_time'],
                'to_date' => $date, 'to_time' => $time,
            ]);
        } else {
            q(
                'INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason) VALUES (?, ?, ?, ?, ?)',
                [$patient['id'], $doctorId, $date, $time, $reason]
            );
            $id = (int) $pdo->lastInsertId();
            log_history($id, null, 'Pending', 'patient', (int) $patient['id'], '', ['to_date' => $date, 'to_time' => $time]);
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }

    $when = fmt_date($date) . ' at ' . fmt_time($time);
    if ($existing) {
        notify('doctor', $doctorId, 'Reschedule request', $patient['name'] . ' asked to move their appointment to ' . $when . '.', 'appointment.php?id=' . $id);
        if ((int) $existing['doctor_id'] !== $doctorId) {
            notify('doctor', (int) $existing['doctor_id'], 'Appointment moved to another doctor', $patient['name'] . ' moved their appointment away from your schedule.', '');
        }
    } else {
        notify('doctor', $doctorId, 'New appointment request', $patient['name'] . ' requested ' . $when . '.', 'appointment.php?id=' . $id);
    }
    return ['appointment_id' => $id];
}

/**
 * Creates a reminder for each approved appointment starting within 24 hours.
 * Called when a patient signs in or opens their dashboard (free hosting has no scheduled jobs).
 */
function send_due_reminders(int $patientId): void
{
    $due = q_all(
        "SELECT a.appointment_id, a.appointment_date, a.appointment_time, d.name AS doctor_name, d.clinic AS clinic_name
         FROM appointments a JOIN doctors d ON d.doctor_id = a.doctor_id
         WHERE a.patient_id = ? AND a.status = 'Approved' AND a.reminder_sent = 0
           AND TIMESTAMP(a.appointment_date, a.appointment_time) BETWEEN NOW() AND (NOW() + INTERVAL 24 HOUR)",
        [$patientId]
    );
    foreach ($due as $appt) {
        notify(
            'patient',
            $patientId,
            'Reminder: appointment ' . strtolower(relative_day($appt['appointment_date'])),
            $appt['doctor_name'] . ', ' . $appt['clinic_name'] . ', ' . fmt_time($appt['appointment_time']) . '. Please arrive 10 minutes early with your ID card.',
            'appointment.php?id=' . $appt['appointment_id']
        );
        q('UPDATE appointments SET reminder_sent = 1 WHERE appointment_id = ?', [$appt['appointment_id']]);
    }
}

/** Counts per status for a WHERE clause, with every status present. */
function status_counts(string $where = '1=1', array $params = []): array
{
    $counts = array_fill_keys(STATUSES, 0);
    foreach (q_all("SELECT a.status, COUNT(*) AS n FROM appointments a JOIN doctors d ON d.doctor_id = a.doctor_id WHERE $where GROUP BY a.status", $params) as $row) {
        $counts[$row['status']] = (int) $row['n'];
    }
    return $counts;
}
