<?php
/*
 * One-time setup: creates the tables (if missing) and the first administrator.
 * It locks itself once an administrator exists. Delete this file after setup.
 */
require __DIR__ . '/includes/bootstrap.php';

$installed = table_exists('admins') && (int) q_val('SELECT COUNT(*) FROM admins') > 0;
$errors = [];
$old = ['name' => '', 'email' => ''];

function run_schema(): void
{
    $sql = (string) file_get_contents(APP_ROOT . '/database/schema.sql');
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (preg_split('/;\s*(\r?\n|$)/', (string) $sql) as $statement) {
        if (trim($statement) !== '') {
            db()->exec($statement);
        }
    }
}

if (!$installed && is_post()) {
    verify_csrf();
    $old = ['name' => post('name'), 'email' => mb_strtolower(post('email'))];
    $password = (string) ($_POST['password'] ?? '');

    if (mb_strlen($old['name']) < 3) $errors[] = 'Enter the administrator\'s full name.';
    if (!valid_email($old['email'])) $errors[] = 'Enter a valid email address.';
    if ($problem = password_problem($password)) $errors[] = 'Administrator password: ' . $problem;

    if (!$errors) {
        try {
            run_schema();
            q('INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?)', [$old['name'], $old['email'], password_hash($password, PASSWORD_DEFAULT)]);
            $adminId = (int) db()->lastInsertId();
            audit($adminId, 'system.install', 'system', null, 'Created first administrator');
            flash('success', 'Setup complete. Sign in as an administrator and add your doctors, then delete install.php from the server.');
            redirect('login.php?as=admin');
        } catch (PDOException $ex) {
            $errors[] = config('debug') ? 'Database error: ' . $ex->getMessage() : 'The database could not be set up. Check that the database user can create tables, or import database/schema.sql with phpMyAdmin first.';
        }
    }
}

$tablesExist = table_exists('appointments');
render_head('Set up');
?>
<div class="auth" style="grid-template-columns:1fr">
  <div class="auth-main">
    <div class="auth-tools"><?= theme_toggle() ?></div>
    <span class="brand"><?= brand_logo(40) ?></span>
    <main class="auth-form-wrap" id="main">
      <div class="auth-form" style="max-width:460px">
        <?php if ($installed): ?>
          <h1>Already set up</h1>
          <p class="lead">An administrator account exists, so this page is locked. For security, delete <span class="mono">install.php</span> from the server.</p>
          <a class="btn btn-primary btn-lg btn-block" style="margin-top:24px" href="<?= e(url('login.php?as=admin')) ?>">Go to sign in <?= icon('arrow-right', 16) ?></a>
        <?php else: ?>
          <h1>Set up the system</h1>
          <p class="lead">
            <?= $tablesExist ? 'The database tables are ready.' : 'The database tables will be created.' ?>
            Create the first administrator account to finish.
          </p>
          <form method="post" novalidate>
            <?= csrf_field() ?>
            <?= render_flashes($errors) ?>
            <div class="form-grid">
              <div class="field"><label class="label" for="name">Administrator name</label><input class="input" id="name" name="name" value="<?= e($old['name']) ?>" required></div>
              <div class="field"><label class="label" for="email">Administrator email</label><input class="input" type="email" id="email" name="email" value="<?= e($old['email']) ?>" autocomplete="username" required></div>
              <div class="field">
                <label class="label" for="password">Administrator password</label>
                <?= password_input('password', 'new-password') ?>
                <span class="hint">8 or more characters, with a letter and a number.</span>
              </div>
              <button class="btn btn-primary btn-lg btn-block" type="submit">Finish setup <?= icon('arrow-right', 16) ?></button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>
<?php render_foot();
