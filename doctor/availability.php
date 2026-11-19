<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_role('doctor');
$errors = [];
$form = [
    'days' => doctor_work_days($user),
    'start' => substr($user['start_time'], 0, 5),
    'end' => substr($user['end_time'], 0, 5),
    'slot' => (int) $user['slot_minutes'],
];

if (is_post()) {
    verify_csrf();
    $result = validate_schedule($_POST['days'] ?? [], post('start_time'), post('end_time'), post('slot_minutes'));
    $form = $result['form'];
    $errors = $result['errors'];
    if (!$errors) {
        q(
            'UPDATE doctors SET work_days = ?, start_time = ?, end_time = ?, slot_minutes = ? WHERE doctor_id = ?',
            [implode(',', $form['days']), $form['start'] . ':00', $form['end'] . ':00', $form['slot'], $user['id']]
        );
        flash('success', 'Your availability has been updated. Existing appointments are unchanged.');
        redirect('doctor/availability.php');
    }
}

$upcoming = (int) q_val(
    "SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status IN ('Pending','Approved','Rescheduled') AND appointment_date >= CURDATE()",
    [$user['id']]
);
$preview = doctor_slot_times(['start_time' => $form['start'] . ':00', 'end_time' => $form['end'] . ':00', 'slot_minutes' => $form['slot']]);

app_start($user, 'Availability', [
    'active' => 'doctor/availability.php',
    'subtitle' => 'Set the days and hours patients can book with you.',
    'errors' => $errors,
]);
?>
<div class="grid grid-main-aside">
  <form class="card" method="post">
    <?= csrf_field() ?>
    <?= schedule_fields($form) ?>
    <div class="card-footer row-between wrap">
      <span class="hint">Changes apply to new bookings only.</span>
      <button class="btn btn-primary" type="submit">Save availability</button>
    </div>
  </form>

  <aside class="stack">
    <section class="card">
      <div class="card-header"><div><h2>Bookable times</h2><p class="hint">Per consulting day, with current settings.</p></div></div>
      <div class="card-body">
        <div class="stat-value"><?= count($preview) ?><span style="font-size:15px;color:var(--ink-3);margin-left:6px">slots</span></div>
        <div class="row wrap" style="gap:6px;margin-top:14px">
          <?php foreach (array_slice($preview, 0, 18) as $t): ?><span class="tag num"><?= e(fmt_time($t)) ?></span><?php endforeach; ?>
          <?php if (count($preview) > 18): ?><span class="tag">+<?= count($preview) - 18 ?> more</span><?php endif; ?>
        </div>
      </div>
    </section>
    <?php if ($upcoming): ?>
      <div class="alert alert-info"><?= icon('info', 18) ?><div class="alert-body">You have <?= e(plural($upcoming, 'upcoming appointment')) ?>. They keep their current times even if you change your hours, so cancel them individually if you will be away.</div></div>
    <?php endif; ?>
  </aside>
</div>
<?php app_end();
