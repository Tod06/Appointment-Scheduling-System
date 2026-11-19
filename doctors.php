<?php
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$clinic = query('clinic');

$clinics = q_all('SELECT clinic, COUNT(*) AS n FROM doctors WHERE is_active = 1 GROUP BY clinic ORDER BY clinic');
$total = array_sum(array_map(fn ($c) => (int) $c['n'], $clinics));
if ($clinic !== '' && !in_array($clinic, array_column($clinics, 'clinic'), true)) {
    $clinic = '';
}

$doctors = q_all(
    'SELECT doctor_id, name, clinic, specialty, work_days, start_time, end_time, slot_minutes
     FROM doctors WHERE is_active = 1' . ($clinic !== '' ? ' AND clinic = ?' : '') . ' ORDER BY clinic, name',
    $clinic !== '' ? [$clinic] : []
);

// Only patients (or visitors, who are asked to sign in first) can book.
$canBook = !$user || $user['role'] === 'patient';

render_head('Doctors');
site_header($user, 'doctors.php');
?>
<main id="main">
  <div class="container">
    <section class="page-intro">
      <span class="eyebrow">Doctors</span>
      <h1>Doctors and consultation hours.</h1>
      <p>Each doctor's clinic, specialty and the days and hours they consult. You will see the exact free times when you book.</p>
    </section>
  </div>

  <section class="section tight">
    <div class="container">
      <?php if (count($clinics) > 1): ?>
        <nav class="chips" aria-label="Filter by clinic type">
          <a class="chip<?= $clinic === '' ? ' is-active' : '' ?>" href="<?= e(url('doctors.php')) ?>">All <span class="count"><?= $total ?></span></a>
          <?php foreach ($clinics as $c): ?>
            <a class="chip<?= $clinic === $c['clinic'] ? ' is-active' : '' ?>" href="<?= e(url('doctors.php?clinic=' . rawurlencode($c['clinic']))) ?>"><?= e($c['clinic']) ?> <span class="count"><?= (int) $c['n'] ?></span></a>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>

      <?php if ($doctors): ?>
        <div class="doctor-grid">
          <?php foreach ($doctors as $d): ?>
            <article class="card doctor-card">
              <div class="head">
                <span class="avatar avatar-lg"><?= e(initials($d['name'])) ?></span>
                <div>
                  <h3><?= e($d['name']) ?></h3>
                  <div class="muted" style="font-size:13px"><?= e($d['specialty'] !== '' ? $d['specialty'] : 'Doctor') ?></div>
                </div>
              </div>
              <div class="meta">
                <span><?= icon('building', 15) ?> <?= e($d['clinic']) ?></span>
                <span><?= icon('calendar', 15) ?> <?= e(describe_work_days($d['work_days'])) ?></span>
                <span><?= icon('clock', 15) ?> <?= e(fmt_time($d['start_time'])) ?> to <?= e(fmt_time($d['end_time'])) ?> · <?= (int) $d['slot_minutes'] ?> min appointments</span>
              </div>
              <?php if ($canBook): ?>
                <div class="foot">
                  <a class="btn btn-primary btn-block" href="<?= e(url('patient/book.php?doctor=' . $d['doctor_id'])) ?>"><?= icon('calendar-plus', 16) ?> Book with this doctor</a>
                </div>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
        <?php if (!$user): ?>
          <p class="muted" style="margin-top:16px;font-size:13px">You will be asked to sign in or create an account before booking.</p>
        <?php endif; ?>
      <?php else: ?>
        <div class="card"><?= empty_state('stethoscope', 'No doctors yet', 'Bookings open once the administrator adds doctors.') ?></div>
      <?php endif; ?>
    </div>
  </section>
</main>
<?php
site_footer();
render_foot();
