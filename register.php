<?php
require __DIR__ . '/includes/bootstrap.php';

if ($user = current_user()) {
    redirect(home_for($user['role']));
}

$old = ['name' => '', 'matric_no' => '', 'email' => '', 'phone' => ''];
$errors = [];

if (is_post()) {
    verify_csrf();
    foreach ($old as $key => $_) {
        $old[$key] = post($key);
    }
    $old['email'] = mb_strtolower($old['email']);
    $old['matric_no'] = strtoupper($old['matric_no']);
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    if (mb_strlen($old['name']) < 3 || mb_strlen($old['name']) > 120) {
        $errors['name'] = 'Enter your full name.';
    }
    if ($old['matric_no'] !== '' && !valid_matric($old['matric_no'])) {
        $errors['matric_no'] = 'Matric numbers can contain letters, numbers, / and -.';
    }
    if (!valid_email($old['email'])) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (!valid_phone($old['phone'])) {
        $errors['phone'] = 'Enter a valid phone number.';
    }
    if ($problem = password_problem($password)) {
        $errors['password'] = $problem;
    } elseif ($password !== $confirm) {
        $errors['password_confirm'] = 'The two passwords do not match.';
    }

    if (!isset($errors['email']) && q_val('SELECT COUNT(*) FROM patients WHERE email = ?', [$old['email']])) {
        $errors['email'] = 'An account with this email already exists. Sign in instead.';
    }
    if ($old['matric_no'] !== '' && !isset($errors['matric_no']) && q_val('SELECT COUNT(*) FROM patients WHERE matric_no = ?', [$old['matric_no']])) {
        $errors['matric_no'] = 'This matric number is already registered.';
    }

    if (!$errors) {
        q(
            'INSERT INTO patients (matric_no, name, email, phone, password_hash) VALUES (?, ?, ?, ?, ?)',
            [$old['matric_no'] !== '' ? $old['matric_no'] : null, $old['name'], $old['email'], $old['phone'], password_hash($password, PASSWORD_DEFAULT)]
        );
        $id = (int) db()->lastInsertId();
        login_user('patient', ['id' => $id]);
        notify('patient', $id, 'Welcome to UHS Appointments', 'You can now book appointments at the Health Service. Bring your ID card to every visit.', 'patient/book.php');
        flash('success', 'Your account is ready. Book your first appointment below.');
        redirect('patient/book.php');
    }
}

function field_error(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<span class="error-text">' . e($errors[$key]) . '</span>' : '';
}
function invalid(array $errors, string $key): string
{
    return isset($errors[$key]) ? ' is-invalid' : '';
}

render_head('Create an account');
?>
<div class="auth">
  <div class="auth-main">
    <a class="brand" href="<?= e(url('index.php')) ?>" aria-label="UHS Appointments home">
      <?= brand_logo(40) ?>
    </a>
    <main class="auth-form-wrap" id="main">
      <div class="auth-form" style="max-width:440px">
        <h1>Create your account</h1>
        <p class="lead">For students and staff of the University of Ibadan.</p>
        <form method="post" novalidate>
          <?= csrf_field() ?>
          <?= $errors ? render_flashes(['Some details need attention. Check the highlighted fields.']) : render_flashes() ?>
          <div class="form-grid cols-2">
            <div class="field span-2">
              <label class="label" for="name">Full name</label>
              <input class="input<?= invalid($errors, 'name') ?>" id="name" name="name" value="<?= e($old['name']) ?>" autocomplete="name" required>
              <?= field_error($errors, 'name') ?>
            </div>
            <div class="field">
              <label class="label" for="matric_no">Matric number <span class="optional">Optional</span></label>
              <input class="input<?= invalid($errors, 'matric_no') ?>" id="matric_no" name="matric_no" value="<?= e($old['matric_no']) ?>" placeholder="E051808">
              <?= field_error($errors, 'matric_no') ?: '<span class="hint">Staff can leave this blank.</span>' ?>
            </div>
            <div class="field">
              <label class="label" for="phone">Phone</label>
              <input class="input<?= invalid($errors, 'phone') ?>" id="phone" name="phone" type="tel" value="<?= e($old['phone']) ?>" autocomplete="tel" placeholder="080 0000 0000" required>
              <?= field_error($errors, 'phone') ?>
            </div>
            <div class="field span-2">
              <label class="label" for="email">Email</label>
              <input class="input<?= invalid($errors, 'email') ?>" id="email" name="email" type="email" value="<?= e($old['email']) ?>" autocomplete="email" required>
              <?= field_error($errors, 'email') ?>
            </div>
            <div class="field">
              <label class="label" for="password">Password</label>
              <?= password_input('password', 'new-password') ?>
              <?= field_error($errors, 'password') ?: '<span class="hint">8 or more characters, with a letter and a number.</span>' ?>
            </div>
            <div class="field">
              <label class="label" for="password_confirm">Confirm password</label>
              <?= password_input('password_confirm', 'new-password') ?>
              <?= field_error($errors, 'password_confirm') ?>
            </div>
            <div class="span-2">
              <button class="btn btn-primary btn-lg btn-block" type="submit">Create account <?= icon('arrow-right', 16) ?></button>
            </div>
          </div>
        </form>
        <p class="auth-foot">Already registered? <a href="<?= e(url('login.php')) ?>">Sign in</a></p>
      </div>
    </main>
    <p class="muted" style="font-size:12.5px"><?= e(config('org_name')) ?></p>
  </div>
  <aside class="auth-aside" aria-hidden="true" style="--aside-img: url('<?= e(asset('img/register-doctor.jpg')) ?>')">
    <div class="eyebrow" style="color:#e2c46e">University Health Service</div>
    <div>
      <p class="auth-quote">Your details are only seen by the people treating you.</p>
      <div class="auth-points">
        <div class="auth-point"><?= icon('lock', 18) ?> Passwords are stored as one-way hashes, never as plain text.</div>
        <div class="auth-point"><?= icon('shield-check', 18) ?> Doctors see only their own patients' appointments.</div>
        <div class="auth-point"><?= icon('id-card', 18) ?> Your matric number identifies you to Health Service staff.</div>
      </div>
    </div>
    <p class="small">For emergencies, go straight to the Health Service.</p>
  </aside>
</div>
<?php render_foot();
