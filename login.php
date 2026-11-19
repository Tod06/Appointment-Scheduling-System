<?php
require __DIR__ . '/includes/bootstrap.php';

if ($user = current_user()) {
    redirect(home_for($user['role']));
}

$role = in_array(query('as'), ROLES, true) ? query('as') : 'patient';
$identifier = '';
$errors = [];

if (is_post()) {
    verify_csrf();
    $role = in_array(post('role'), ROLES, true) ? post('role') : 'patient';
    $identifier = post('identifier');
    $password = (string) ($_POST['password'] ?? '');
    $throttleKey = $role . ':' . $identifier;

    if ($identifier === '' || $password === '') {
        $errors[] = 'Enter your ' . ($role === 'patient' ? 'email or matric number' : 'email') . ' and password.';
    } elseif (login_locked($throttleKey)) {
        $errors[] = 'Too many failed attempts. Wait 15 minutes and try again.';
    } else {
        $row = attempt_login($role, $identifier, $password);
        if (!$row) {
            record_login_failure($throttleKey);
            $errors[] = 'Those details do not match an account. Check that you chose the right account type.';
        } elseif (!(int) $row['is_active']) {
            $errors[] = 'This account has been deactivated. Contact the Health Service administrator.';
        } else {
            clear_login_failures($throttleKey);
            $intended = safe_local_path((string) ($_SESSION['intended'] ?? ''));
            unset($_SESSION['intended']);
            login_user($role, $row);
            if ($role === 'patient') {
                send_due_reminders((int) $row['id']);
            }
            flash('success', 'Welcome back, ' . first_name($row['name']) . '.');
            redirect($intended ?? home_for($role));
        }
    }
}

render_head('Sign in');
?>
<div class="auth">
  <div class="auth-main">
    <div class="auth-tools"><?= theme_toggle() ?></div>
    <a class="brand" href="<?= e(url('index.php')) ?>" aria-label="UHS Appointments home">
      <?= brand_logo(40) ?>
    </a>
    <main class="auth-form-wrap" id="main">
      <div class="auth-form">
        <h1>Sign in</h1>
        <p class="lead">Choose your account type, then enter your details.</p>
        <form method="post" novalidate>
          <?= csrf_field() ?>
          <?= render_flashes($errors) ?>
          <div class="form-grid">
            <div class="segmented" role="radiogroup" aria-label="Account type">
              <?php foreach (['patient' => ['Patient', 'user'], 'doctor' => ['Doctor', 'stethoscope'], 'admin' => ['Admin', 'shield-check']] as $value => [$label, $ic]): ?>
                <input type="radio" name="role" id="role-<?= $value ?>" value="<?= $value ?>" <?= $role === $value ? 'checked' : '' ?> data-role-input>
                <label for="role-<?= $value ?>"><?= icon($ic, 15) ?> <?= $label ?></label>
              <?php endforeach; ?>
            </div>
            <div class="field">
              <label class="label" for="identifier" data-identifier-label><?= $role === 'patient' ? 'Email or matric number' : 'Email' ?></label>
              <div class="input-group">
                <?= icon('mail', 16, 'icon-lead') ?>
                <input class="input" id="identifier" name="identifier" value="<?= e($identifier) ?>" autocomplete="username" required autofocus>
              </div>
            </div>
            <div class="field">
              <label class="label" for="password">Password</label>
              <?= password_input('password', 'current-password') ?>
            </div>
            <button class="btn btn-primary btn-lg btn-block" type="submit">Sign in <?= icon('arrow-right', 16) ?></button>
          </div>
        </form>
        <p class="auth-foot" data-register-hint <?= $role !== 'patient' ? 'hidden' : '' ?>>New to the Health Service? <a href="<?= e(url('register.php')) ?>">Create a patient account</a></p>
        <p class="auth-foot" data-staff-hint <?= $role === 'patient' ? 'hidden' : '' ?>>Staff accounts are created by the Health Service administrator.</p>
      </div>
    </main>
    <p class="muted" style="font-size:12.5px"><?= e(config('org_name')) ?></p>
  </div>
  <aside class="auth-aside" aria-hidden="true" style="--aside-img: url('<?= e(asset('img/signin-doctor.jpg')) ?>')">
    <div class="eyebrow" style="color:#e2c46e">University Health Service</div>
    <div>
      <p class="auth-quote">One place for every appointment, from request to consultation.</p>
      <div class="auth-points">
        <div class="auth-point"><?= icon('calendar-check', 18) ?> See open times before you book and hold a slot instantly.</div>
        <div class="auth-point"><?= icon('bell', 18) ?> Get notified when your doctor confirms, and reminded the day before.</div>
        <div class="auth-point"><?= icon('refresh', 18) ?> Cancel or move an appointment yourself, any time up to two hours before.</div>
      </div>
    </div>
    <p class="small">For emergencies, go straight to the Health Service.</p>
  </aside>
</div>
<?php render_foot();
