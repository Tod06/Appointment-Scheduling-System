<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_role('admin');
$clinics = clinic_types(false);
$idParam = $_GET['id'] ?? $_POST['id'] ?? '';
$isNew = $idParam === 'new';
$id = $isNew ? 0 : to_int($idParam);
$errors = [];

/* ---------- Add or edit one doctor ---------- */
if ($isNew || $id) {
    $doctor = $id ? q_one('SELECT * FROM doctors WHERE id = ?', [$id]) : null;
    if ($id && !$doctor) {
        render_error_page(404, 'Doctor not found', 'This doctor record does not exist.');
    }
    $form = [
        'name' => $doctor['name'] ?? 'Dr. ',
        'email' => $doctor['email'] ?? '',
        'phone' => $doctor['phone'] ?? '',
        'specialty' => $doctor['specialty'] ?? '',
        'clinic' => $doctor['clinic'] ?? query('clinic'),
        'is_active' => (int) ($doctor['is_active'] ?? 1),
    ];
    $schedule = [
        'days' => $doctor ? doctor_work_days($doctor) : [1, 2, 3, 4, 5],
        'start' => substr($doctor['start_time'] ?? '08:00:00', 0, 5),
        'end' => substr($doctor['end_time'] ?? '16:00:00', 0, 5),
        'slot' => (int) ($doctor['slot_minutes'] ?? 30),
    ];

    if (is_post()) {
        verify_csrf();
        $form = [
            'name' => post('name'),
            'email' => mb_strtolower(post('email')),
            'phone' => post('phone'),
            'specialty' => post('specialty'),
            'clinic' => post('clinic'),
            'is_active' => post('is_active') === '1' ? 1 : 0,
        ];
        $password = (string) ($_POST['password'] ?? '');
        $result = validate_schedule($_POST['days'] ?? [], post('start_time'), post('end_time'), post('slot_minutes'));
        $schedule = $result['form'];
        $errors = $result['errors'];

        if (mb_strlen($form['name']) < 4 || mb_strlen($form['name']) > 120) $errors[] = 'Enter the doctor\'s full name.';
        if (!valid_email($form['email'])) $errors[] = 'Enter a valid email address.';
        if ($form['phone'] !== '' && !valid_phone($form['phone'])) $errors[] = 'Enter a valid phone number or leave it blank.';
        if (mb_strlen($form['clinic']) < 2 || mb_strlen($form['clinic']) > 120) $errors[] = 'Enter the clinic the doctor consults in.';
        if (!$errors && q_val('SELECT COUNT(*) FROM doctors WHERE email = ? AND id <> ?', [$form['email'], $id])) $errors[] = 'Another doctor already uses this email.';
        if ($isNew && $password === '') {
            $errors[] = 'Set a password so the doctor can sign in.';
        } elseif ($password !== '' && ($problem = password_problem($password))) {
            $errors[] = 'Password: ' . $problem;
        }

        if (!$errors) {
            $values = [
                $form['clinic'], $form['name'], $form['specialty'], $form['email'], $form['phone'],
                implode(',', $schedule['days']), $schedule['start'] . ':00', $schedule['end'] . ':00', $schedule['slot'], $form['is_active'],
            ];
            if ($isNew) {
                q(
                    'INSERT INTO doctors (clinic, name, specialty, email, phone, work_days, start_time, end_time, slot_minutes, is_active, password_hash)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    array_merge($values, [password_hash($password, PASSWORD_DEFAULT)])
                );
                $id = (int) db()->lastInsertId();
                audit((int) $user['id'], 'doctor.create', 'doctor', $id, 'Added ' . $form['name']);
                flash('success', $form['name'] . ' has been added and can now sign in.');
            } else {
                q(
                    'UPDATE doctors SET clinic = ?, name = ?, specialty = ?, email = ?, phone = ?, work_days = ?, start_time = ?, end_time = ?, slot_minutes = ?, is_active = ? WHERE id = ?',
                    array_merge($values, [$id])
                );
                if ($password !== '') {
                    q('UPDATE doctors SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
                }
                audit((int) $user['id'], 'doctor.update', 'doctor', $id, 'Updated ' . $form['name'] . ($password !== '' ? ' (password reset)' : ''));
                flash('success', 'Doctor details saved.');
            }
            redirect('admin/doctors.php?id=' . $id);
        }
    }

    $upcoming = $id ? (int) q_val(
        "SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status IN ('Pending','Approved','Rescheduled') AND appointment_date >= CURDATE()",
        [$id]
    ) : 0;

    app_start($user, $isNew ? 'Add doctor' : 'Doctor', [
        'active' => 'admin/doctors.php',
        'heading' => $isNew ? 'Add a doctor' : $doctor['name'],
        'subtitle' => $isNew ? 'Create a doctor account and set when they consult.' : ($doctor['specialty'] ?: 'Doctor') . ' · ' . plural($upcoming, 'upcoming appointment'),
        'back' => [url('admin/doctors.php'), 'All doctors'],
        'errors' => $errors,
        'actions' => $isNew ? '' : '<a class="btn btn-secondary" href="' . e(url('admin/calendar.php?doctor=' . $id)) . '">' . icon('calendar', 16) . ' View calendar</a>',
    ]);
    ?>
<form method="post" class="grid grid-main-aside">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= $isNew ? 'new' : $id ?>">
  <div class="stack">
    <section class="card">
      <div class="card-header"><h2>Profile</h2></div>
      <div class="card-body form-grid cols-2">
        <div class="field span-2"><label class="label" for="name">Full name</label><input class="input" id="name" name="name" value="<?= e($form['name']) ?>" required></div>
        <div class="field">
          <label class="label" for="clinic">Clinic</label>
          <input class="input" id="clinic" name="clinic" value="<?= e($form['clinic']) ?>" list="clinic-options" maxlength="120" required>
          <datalist id="clinic-options"><?php foreach ($clinics as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
          <span class="hint">Patients choose this as the clinic type when booking.</span>
        </div>
        <div class="field"><label class="label" for="specialty">Specialty</label><input class="input" id="specialty" name="specialty" value="<?= e($form['specialty']) ?>" placeholder="General Practitioner"></div>
        <div class="field"><label class="label" for="email">Email</label><input class="input" type="email" id="email" name="email" value="<?= e($form['email']) ?>" required><span class="hint">Used to sign in.</span></div>
        <div class="field"><label class="label" for="phone">Phone <span class="optional">Optional</span></label><input class="input" type="tel" id="phone" name="phone" value="<?= e($form['phone']) ?>"></div>
      </div>
    </section>
    <section class="card"><?= schedule_fields($schedule) ?></section>
  </div>

  <aside class="stack">
    <section class="card">
      <div class="card-header"><div><h2><?= $isNew ? 'Password' : 'Reset password' ?></h2><p class="hint"><?= $isNew ? 'Share it with the doctor in person.' : 'Leave blank to keep the current password.' ?></p></div></div>
      <div class="card-body">
        <div class="field"><label class="label" for="password">New password</label><?= str_replace(' required>', ($isNew ? ' required' : '') . '>', password_input('password', 'new-password')) ?></div>
      </div>
    </section>
    <section class="card card-body">
      <label class="check"><input type="checkbox" name="is_active" value="1" <?= $form['is_active'] ? 'checked' : '' ?>> Taking appointments</label>
      <p class="hint" style="margin-top:6px">When off, the doctor cannot sign in and patients cannot book them. Existing appointments are kept.</p>
    </section>
    <button class="btn btn-primary btn-lg btn-block" type="submit"><?= icon('check', 17) ?> <?= $isNew ? 'Add doctor' : 'Save changes' ?></button>
  </aside>
</form>
<?php
    app_end();
    exit;
}

/* ---------- Doctor list ---------- */
$clinicFilter = query('clinic');
$where = $clinicFilter !== '' ? 'WHERE d.clinic = ?' : '';
$rows = q_all(
    "SELECT d.*,
            SUM(a.status IN ('Pending','Approved','Rescheduled') AND a.appointment_date >= CURDATE()) AS upcoming
     FROM doctors d
     LEFT JOIN appointments a ON a.doctor_id = d.id
     $where GROUP BY d.id ORDER BY d.is_active DESC, d.clinic, d.name",
    $clinicFilter !== '' ? [$clinicFilter] : []
);

app_start($user, 'Doctors', [
    'active' => 'admin/doctors.php',
    'subtitle' => 'Doctors who consult at the Health Service and their consulting hours.',
    'actions' => '<a class="btn btn-primary" href="' . e(url('admin/doctors.php?id=new')) . '">' . icon('plus', 16) . ' Add doctor</a>',
]);
?>
<section class="card">
  <form class="filters" method="get">
    <div class="field">
      <label class="label" for="clinic">Clinic</label>
      <select class="select" id="clinic" name="clinic" data-autosubmit>
        <option value="">All clinics</option>
        <?php foreach ($clinics as $c): ?><option value="<?= e($c) ?>" <?= $clinicFilter === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
      </select>
    </div>
  </form>
  <?php if ($rows): ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Doctor</th><th>Clinic</th><th class="hide-sm">Consults</th><th class="hide-sm">Upcoming</th><th>Status</th><th class="actions"><span class="sr-only">Edit</span></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $d): ?>
            <tr>
              <td>
                <div class="row">
                  <span class="avatar avatar-sm"><?= e(initials($d['name'])) ?></span>
                  <div><div class="cell-main"><?= e($d['name']) ?></div><div class="cell-sub"><?= e($d['specialty'] ?: $d['email']) ?></div></div>
                </div>
              </td>
              <td><?= e($d['clinic']) ?></td>
              <td class="hide-sm">
                <div class="cell-main" style="font-weight:400"><?= e(describe_work_days($d['work_days'])) ?></div>
                <div class="cell-sub num"><?= e(fmt_time($d['start_time'])) ?> to <?= e(fmt_time($d['end_time'])) ?> · <?= (int) $d['slot_minutes'] ?> min</div>
              </td>
              <td class="hide-sm num"><?= (int) $d['upcoming'] ?></td>
              <td>
                <?php if (!$d['is_active']): ?><span class="badge badge-cancelled"><span class="badge-dot"></span>Inactive</span>
                <?php elseif (!$d['password_hash']): ?><span class="badge badge-pending"><span class="badge-dot"></span>No password</span>
                <?php else: ?><span class="badge badge-approved"><span class="badge-dot"></span>Active</span><?php endif; ?>
              </td>
              <td class="actions"><a class="btn btn-secondary btn-sm" href="<?= e(url('admin/doctors.php?id=' . $d['id'])) ?>"><?= icon('pencil', 14) ?> Edit</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php else: ?>
    <?= empty_state('stethoscope', 'No doctors yet', 'Add a doctor so patients can start booking.', '<a class="btn btn-primary" href="' . e(url('admin/doctors.php?id=new')) . '">' . icon('plus', 16) . ' Add doctor</a>') ?>
  <?php endif; ?>
</section>
<?php app_end();
