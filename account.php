<?php
require __DIR__ . '/includes/bootstrap.php';

$user = require_role();
$table = user_table($user['role']);
$errors = [];
$section = '';

if (is_post()) {
    verify_csrf();
    $section = post('section');

    if ($section === 'profile') {
        $name = post('name');
        $phone = post('phone');
        if ($user['role'] !== 'doctor' && (mb_strlen($name) < 3 || mb_strlen($name) > 120)) {
            $errors[] = 'Enter your full name.';
        }
        if ($user['role'] !== 'admin' && !valid_phone($phone)) {
            $errors[] = 'Enter a valid phone number.';
        }
        if (!$errors) {
            if ($user['role'] === 'patient') {
                q('UPDATE patients SET name = ?, phone = ? WHERE patient_id = ?', [$name, $phone, $user['id']]);
            } elseif ($user['role'] === 'doctor') {
                q('UPDATE doctors SET phone = ? WHERE doctor_id = ?', [$phone, $user['id']]);
            } else {
                q('UPDATE admins SET name = ? WHERE admin_id = ?', [$name, $user['id']]);
            }
            flash('success', 'Your details have been saved.');
            redirect('account.php');
        }
    }

    if ($section === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['confirm_password'] ?? '');
        $hash = (string) q_val("SELECT password_hash FROM {$table} WHERE " . user_pk($user['role']) . ' = ?', [$user['id']]);
        if (!password_verify($current, $hash)) {
            $errors[] = 'Your current password is incorrect.';
        } elseif ($problem = password_problem($new)) {
            $errors[] = 'New password: ' . $problem;
        } elseif ($new !== $confirm) {
            $errors[] = 'The new passwords do not match.';
        }
        if (!$errors) {
            q("UPDATE {$table} SET password_hash = ? WHERE " . user_pk($user['role']) . ' = ?', [password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            session_regenerate_id(true);
            if ($user['role'] === 'admin') {
                audit((int) $user['id'], 'account.password', 'admin', (int) $user['id'], 'Changed own password');
            }
            flash('success', 'Your password has been changed.');
            redirect('account.php');
        }
    }
}

$clinic = $user['clinic'] ?? null;

app_start($user, 'Account', [
    'active' => 'account.php',
    'subtitle' => 'Your personal details and sign-in security.',
    'errors' => $errors,
]);
?>
<div class="grid grid-main-aside">
  <div class="stack">
    <form class="card" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="section" value="profile">
      <div class="card-header">
        <div>
          <h2>Profile</h2>
          <p class="hint"><?= $user['role'] === 'doctor' ? 'Your name, clinic and email are managed by the administrator.' : 'How you appear to Health Service staff.' ?></p>
        </div>
      </div>
      <div class="card-body form-grid cols-2">
        <div class="field span-2">
          <label class="label" for="name">Full name</label>
          <input class="input" id="name" name="name" value="<?= e($user['name']) ?>" <?= $user['role'] === 'doctor' ? 'readonly' : 'required' ?>>
        </div>
        <div class="field">
          <label class="label" for="email">Email</label>
          <input class="input" id="email" value="<?= e($user['email']) ?>" readonly>
        </div>
        <?php if ($user['role'] !== 'admin'): ?>
        <div class="field">
          <label class="label" for="phone">Phone</label>
          <input class="input" id="phone" name="phone" type="tel" value="<?= e($user['phone']) ?>" required>
        </div>
        <?php endif; ?>
        <?php if ($user['role'] === 'patient'): ?>
        <div class="field">
          <label class="label" for="matric">Matric number</label>
          <input class="input" id="matric" value="<?= e($user['matric_no'] ?: 'Not provided') ?>" readonly>
        </div>
        <?php endif; ?>
        <?php if ($user['role'] === 'doctor'): ?>
        <div class="field">
          <label class="label" for="clinic">Clinic</label>
          <input class="input" id="clinic" value="<?= e($clinic) ?>" readonly>
        </div>
        <?php endif; ?>
      </div>
      <div class="card-footer row" style="justify-content:flex-end">
        <button class="btn btn-primary" type="submit">Save changes</button>
      </div>
    </form>

    <form class="card" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="section" value="password">
      <div class="card-header">
        <div>
          <h2>Password</h2>
          <p class="hint">Use 8 or more characters, with at least one letter and one number.</p>
        </div>
      </div>
      <div class="card-body form-grid cols-2">
        <div class="field span-2">
          <label class="label" for="current_password">Current password</label>
          <?= password_input('current_password', 'current-password') ?>
        </div>
        <div class="field">
          <label class="label" for="new_password">New password</label>
          <?= password_input('new_password', 'new-password') ?>
        </div>
        <div class="field">
          <label class="label" for="confirm_password">Confirm new password</label>
          <?= password_input('confirm_password', 'new-password') ?>
        </div>
      </div>
      <div class="card-footer row" style="justify-content:flex-end">
        <button class="btn btn-primary" type="submit">Change password</button>
      </div>
    </form>
  </div>

  <aside class="card card-body">
    <div class="row">
      <span class="avatar avatar-lg"><?= e(initials($user['name'])) ?></span>
      <div>
        <div style="font-weight:600"><?= e($user['name']) ?></div>
        <div class="muted" style="font-size:13px"><?= e(role_label($user['role'])) ?></div>
      </div>
    </div>
    <dl class="dl" style="margin-top:16px;grid-template-columns:110px 1fr">
      <dt>Member since</dt><dd><?= e(date('j M Y', strtotime($user['created_at']))) ?></dd>
      <?php if ($user['role'] === 'doctor'): ?>
        <dt>Specialty</dt><dd><?= e($user['specialty'] ?: '—') ?></dd>
        <dt>Consults</dt><dd><?= e(describe_work_days($user['work_days'])) ?></dd>
      <?php endif; ?>
    </dl>
  </aside>
</div>
<?php app_end();
