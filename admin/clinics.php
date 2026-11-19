<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_role('admin');
$errors = [];
$editId = to_int(query('edit'));
$form = ['name' => '', 'description' => ''];

if (is_post()) {
    verify_csrf();
    $action = post('action');
    $id = to_int(post('id'));

    if ($action === 'save') {
        $form = ['name' => post('name'), 'description' => post('description')];
        if (mb_strlen($form['name']) < 3 || mb_strlen($form['name']) > 120) $errors[] = 'Enter a clinic name between 3 and 120 characters.';
        if (mb_strlen($form['description']) > 255) $errors[] = 'Keep the description under 255 characters.';
        if (!$errors && q_val('SELECT COUNT(*) FROM clinics WHERE name = ? AND id <> ?', [$form['name'], $id])) $errors[] = 'A clinic with this name already exists.';
        if (!$errors) {
            if ($id) {
                q('UPDATE clinics SET name = ?, description = ? WHERE id = ?', [$form['name'], $form['description'], $id]);
                audit((int) $user['id'], 'clinic.update', 'clinic', $id, $form['name']);
                flash('success', 'Clinic updated.');
            } else {
                q('INSERT INTO clinics (name, description) VALUES (?, ?)', [$form['name'], $form['description']]);
                audit((int) $user['id'], 'clinic.create', 'clinic', (int) db()->lastInsertId(), $form['name']);
                flash('success', 'Clinic added. Add a doctor to it so patients can book.');
            }
            redirect('admin/clinics.php');
        }
        $editId = $id;
    }

    if ($action === 'toggle' && $id) {
        $clinic = q_one('SELECT * FROM clinics WHERE id = ?', [$id]);
        if ($clinic) {
            $newState = (int) !$clinic['is_active'];
            q('UPDATE clinics SET is_active = ? WHERE id = ?', [$newState, $id]);
            audit((int) $user['id'], $newState ? 'clinic.open' : 'clinic.close', 'clinic', $id, $clinic['name']);
            flash('success', $newState ? $clinic['name'] . ' is taking bookings again.' : $clinic['name'] . ' is closed to new bookings.');
        }
        redirect('admin/clinics.php');
    }
}

if ($editId && !$errors) {
    $existing = q_one('SELECT * FROM clinics WHERE id = ?', [$editId]);
    if ($existing) {
        $form = ['name' => $existing['name'], 'description' => $existing['description']];
    } else {
        $editId = 0;
    }
}

$clinics = q_all(
    'SELECT c.*, COUNT(DISTINCT d.id) AS doctors, COUNT(DISTINCT a.id) AS appointments
     FROM clinics c LEFT JOIN doctors d ON d.clinic_id = c.id LEFT JOIN appointments a ON a.doctor_id = d.id
     GROUP BY c.id ORDER BY c.is_active DESC, c.name'
);

app_start($user, 'Clinics', [
    'active' => 'admin/clinics.php',
    'subtitle' => 'The services patients can book. Closing a clinic stops new bookings but keeps its history.',
    'errors' => $errors,
]);
?>
<div class="grid grid-main-aside">
  <section class="card">
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Clinic</th><th>Doctors</th><th class="hide-sm">Appointments</th><th>Status</th><th class="actions"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
          <?php foreach ($clinics as $c): ?>
            <tr>
              <td><div class="cell-main"><?= e($c['name']) ?></div><div class="cell-sub"><?= e($c['description'] ?: 'No description') ?></div></td>
              <td class="num"><a class="link-accent" href="<?= e(url('admin/doctors.php?clinic=' . $c['id'])) ?>"><?= (int) $c['doctors'] ?></a></td>
              <td class="hide-sm num"><?= (int) $c['appointments'] ?></td>
              <td><?= $c['is_active'] ? '<span class="badge badge-approved"><span class="badge-dot"></span>Open</span>' : '<span class="badge badge-completed"><span class="badge-dot"></span>Closed</span>' ?></td>
              <td class="actions">
                <div class="row" style="justify-content:flex-end;gap:6px">
                  <a class="btn btn-ghost btn-sm" href="?edit=<?= (int) $c['id'] ?>"><?= icon('pencil', 14) ?> Edit</a>
                  <form method="post" data-confirm="<?= $c['is_active'] ? 'Close this clinic to new bookings?' : 'Reopen this clinic for bookings?' ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <button class="btn btn-secondary btn-sm" type="submit"><?= $c['is_active'] ? 'Close' : 'Reopen' ?></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if (!$clinics): ?><?= empty_state('building', 'No clinics yet', 'Add your first clinic using the form.') ?><?php endif; ?>
  </section>

  <form class="card summary" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= (int) $editId ?>">
    <div class="card-header"><h2><?= $editId ? 'Edit clinic' : 'Add a clinic' ?></h2><?php if ($editId): ?><a class="btn btn-ghost btn-sm" href="?">Cancel</a><?php endif; ?></div>
    <div class="card-body form-grid">
      <div class="field"><label class="label" for="name">Name</label><input class="input" id="name" name="name" value="<?= e($form['name']) ?>" required maxlength="120"></div>
      <div class="field"><label class="label" for="description">Description <span class="optional">Optional</span></label><textarea class="textarea" id="description" name="description" maxlength="255" placeholder="What patients can be seen for"><?= e($form['description']) ?></textarea></div>
    </div>
    <div class="card-footer row" style="justify-content:flex-end"><button class="btn btn-primary" type="submit"><?= $editId ? 'Save clinic' : 'Add clinic' ?></button></div>
  </form>
</div>
<?php app_end();
