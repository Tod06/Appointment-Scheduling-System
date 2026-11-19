<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_role('admin');
$id = to_int($_GET['id'] ?? $_POST['id'] ?? '');
$errors = [];

/* ---------- Single patient: edit, activate, reset password ---------- */
if ($id) {
    $patient = q_one('SELECT * FROM patients WHERE patient_id = ?', [$id]);
    if (!$patient) {
        render_error_page(404, 'Patient not found', 'This patient record does not exist.');
    }

    if (is_post()) {
        verify_csrf();
        $action = post('action');

        if ($action === 'save') {
            $name = post('name');
            $email = mb_strtolower(post('email'));
            $phone = post('phone');
            $matric = strtoupper(post('matric_no'));
            if (mb_strlen($name) < 3) $errors[] = 'Enter the full name.';
            if (!valid_email($email)) $errors[] = 'Enter a valid email address.';
            if (!valid_phone($phone)) $errors[] = 'Enter a valid phone number.';
            if ($matric !== '' && !valid_matric($matric)) $errors[] = 'Matric numbers can contain letters, numbers, / and -.';
            if (!$errors && q_val('SELECT COUNT(*) FROM patients WHERE email = ? AND patient_id <> ?', [$email, $id])) $errors[] = 'Another patient already uses this email.';
            if (!$errors && $matric !== '' && q_val('SELECT COUNT(*) FROM patients WHERE matric_no = ? AND patient_id <> ?', [$matric, $id])) $errors[] = 'Another patient already uses this matric number.';
            if (!$errors) {
                q('UPDATE patients SET name = ?, email = ?, phone = ?, matric_no = ? WHERE patient_id = ?', [$name, $email, $phone, $matric !== '' ? $matric : null, $id]);
                audit((int) $user['id'], 'patient.update', 'patient', $id, 'Updated details for ' . $name);
                flash('success', 'Patient details saved.');
                redirect('admin/patients.php?id=' . $id);
            }
            $patient = array_merge($patient, ['name' => $name, 'email' => $email, 'phone' => $phone, 'matric_no' => $matric]);
        }

        if ($action === 'toggle') {
            $newState = (int) !$patient['is_active'];
            q('UPDATE patients SET is_active = ? WHERE patient_id = ?', [$newState, $id]);
            audit((int) $user['id'], $newState ? 'patient.activate' : 'patient.deactivate', 'patient', $id, $patient['name']);
            flash('success', $newState ? 'Account reactivated. The patient can sign in again.' : 'Account deactivated. The patient can no longer sign in.');
            redirect('admin/patients.php?id=' . $id);
        }

        if ($action === 'password') {
            $new = (string) ($_POST['new_password'] ?? '');
            if ($problem = password_problem($new)) {
                $errors[] = 'Password: ' . $problem;
            } else {
                q('UPDATE patients SET password_hash = ? WHERE patient_id = ?', [password_hash($new, PASSWORD_DEFAULT), $id]);
                audit((int) $user['id'], 'patient.password_reset', 'patient', $id, $patient['name']);
                flash('success', 'Password reset. Share it with the patient in person.');
                redirect('admin/patients.php?id=' . $id);
            }
        }
    }

    $appointments = q_all(APPOINTMENT_SELECT . ' WHERE a.patient_id = ? ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 50', [$id]);
    $counts = status_counts('a.patient_id = ?', [$id]);

    app_start($user, 'Patient', [
        'active' => 'admin/patients.php',
        'heading' => $patient['name'],
        'subtitle' => ($patient['matric_no'] ? $patient['matric_no'] . ' · ' : '') . 'Registered ' . date('j M Y', strtotime($patient['created_at'])),
        'back' => [url('admin/patients.php'), 'All patients'],
        'errors' => $errors,
        'actions' => $patient['is_active'] ? '<span class="badge badge-approved"><span class="badge-dot"></span>Active</span>' : '<span class="badge badge-cancelled"><span class="badge-dot"></span>Deactivated</span>',
    ]);
    ?>
<div class="grid grid-main-aside">
  <div class="stack">
    <section class="card">
      <div class="card-header"><h2>Appointments</h2><span class="muted" style="font-size:12.5px"><?= e(plural(array_sum($counts), 'appointment')) ?></span></div>
      <?php if ($appointments): ?>
        <?= appointments_table($appointments, 'patient') ?>
      <?php else: ?>
        <?= empty_state('calendar', 'No appointments', 'This patient has not booked yet.') ?>
      <?php endif; ?>
    </section>
  </div>
  <aside class="stack">
    <form class="card" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="save">
      <div class="card-header"><h2>Details</h2></div>
      <div class="card-body form-grid">
        <div class="field"><label class="label" for="name">Full name</label><input class="input" id="name" name="name" value="<?= e($patient['name']) ?>" required></div>
        <div class="field"><label class="label" for="matric_no">Matric number <span class="optional">Optional</span></label><input class="input" id="matric_no" name="matric_no" value="<?= e($patient['matric_no']) ?>"></div>
        <div class="field"><label class="label" for="email">Email</label><input class="input" type="email" id="email" name="email" value="<?= e($patient['email']) ?>" required></div>
        <div class="field"><label class="label" for="phone">Phone</label><input class="input" type="tel" id="phone" name="phone" value="<?= e($patient['phone']) ?>" required></div>
      </div>
      <div class="card-footer row" style="justify-content:flex-end"><button class="btn btn-primary" type="submit">Save details</button></div>
    </form>

    <form class="card" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="password">
      <div class="card-header"><div><h2>Reset password</h2><p class="hint">For patients who cannot sign in.</p></div></div>
      <div class="card-body form-grid">
        <div class="field"><label class="label" for="new_password">New password</label><?= password_input('new_password', 'new-password') ?></div>
      </div>
      <div class="card-footer row" style="justify-content:flex-end"><button class="btn btn-secondary" type="submit">Set password</button></div>
    </form>

    <form class="card card-body" method="post" data-confirm="<?= $patient['is_active'] ? 'Deactivate this account? The patient will not be able to sign in.' : 'Reactivate this account?' ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="toggle">
      <div class="row-between">
        <div>
          <div style="font-weight:600"><?= $patient['is_active'] ? 'Deactivate account' : 'Reactivate account' ?></div>
          <p class="hint"><?= $patient['is_active'] ? 'Blocks sign-in. Records are kept.' : 'Lets the patient sign in again.' ?></p>
        </div>
        <button class="btn <?= $patient['is_active'] ? 'btn-danger' : 'btn-secondary' ?> btn-sm" type="submit"><?= icon($patient['is_active'] ? 'user-x' : 'user-plus', 15) ?> <?= $patient['is_active'] ? 'Deactivate' : 'Reactivate' ?></button>
      </div>
    </form>
  </aside>
</div>
<?php
    app_end();
    exit;
}

/* ---------- Patient list ---------- */
$search = mb_substr(query('q'), 0, 80);
$state = in_array(query('state'), ['active', 'inactive'], true) ? query('state') : '';
$where = ['1=1'];
$params = [];
if ($search !== '') {
    $where[] = '(p.name LIKE ? OR p.matric_no LIKE ? OR p.email LIKE ? OR p.phone LIKE ?)';
    $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($state !== '') {
    $where[] = 'p.is_active = ?';
    $params[] = $state === 'active' ? 1 : 0;
}
$whereSql = implode(' AND ', $where);
$total = (int) q_val("SELECT COUNT(*) FROM patients p WHERE $whereSql", $params);
$p = paginate($total, 25);
$rows = q_all(
    "SELECT p.*, COUNT(a.appointment_id) AS total_appts, MAX(a.appointment_date) AS last_visit
     FROM patients p LEFT JOIN appointments a ON a.patient_id = p.patient_id
     WHERE $whereSql GROUP BY p.patient_id ORDER BY p.created_at DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}",
    $params
);

app_start($user, 'Patients', [
    'active' => 'admin/patients.php',
    'subtitle' => 'Everyone registered to book appointments.',
]);
?>
<section class="card">
  <form class="filters" method="get">
    <div class="field grow">
      <label class="label" for="q">Search</label>
      <div class="input-group"><?= icon('search', 15, 'icon-lead') ?><input class="input" id="q" name="q" value="<?= e($search) ?>" placeholder="Name, matric number, email or phone"></div>
    </div>
    <div class="field">
      <label class="label" for="state">Account</label>
      <select class="select" id="state" name="state" data-autosubmit>
        <option value="">All</option>
        <option value="active" <?= $state === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $state === 'inactive' ? 'selected' : '' ?>>Deactivated</option>
      </select>
    </div>
    <button class="btn btn-secondary" type="submit" style="height:34px"><?= icon('search', 15) ?> Search</button>
  </form>
  <?php if ($rows): ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Patient</th><th class="hide-sm">Matric number</th><th class="hide-sm">Phone</th><th>Appointments</th><th>Account</th><th class="actions"><span class="sr-only">Open</span></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td>
                <div class="row">
                  <span class="avatar avatar-sm"><?= e(initials($r['name'])) ?></span>
                  <div><div class="cell-main"><?= e($r['name']) ?></div><div class="cell-sub"><?= e($r['email']) ?></div></div>
                </div>
              </td>
              <td class="hide-sm mono"><?= e($r['matric_no'] ?: '—') ?></td>
              <td class="hide-sm num"><?= e($r['phone']) ?></td>
              <td class="num"><?= (int) $r['total_appts'] ?><?php if ($r['last_visit']): ?> <span class="cell-sub">· last <?= e(fmt_date_short($r['last_visit'])) ?></span><?php endif; ?></td>
              <td><?= $r['is_active'] ? '<span class="badge badge-approved"><span class="badge-dot"></span>Active</span>' : '<span class="badge badge-cancelled"><span class="badge-dot"></span>Deactivated</span>' ?></td>
              <td class="actions"><a class="btn btn-secondary btn-sm" href="<?= e(url('admin/patients.php?id=' . $r['patient_id'])) ?>">Manage</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pagination_bar($p) ?>
  <?php else: ?>
    <?= empty_state('users', $search ? 'No matching patients' : 'No patients yet', $search ? 'Try a different search.' : 'Patients appear here once they register.') ?>
  <?php endif; ?>
</section>
<?php app_end();
