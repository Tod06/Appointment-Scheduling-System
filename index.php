<?php
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$doctorCount = (int) q_val('SELECT COUNT(*) FROM doctors WHERE is_active = 1');
$sample = q_one('SELECT name, clinic FROM doctors WHERE is_active = 1 ORDER BY clinic, name LIMIT 1');

// Sample week for the preview card, starting from today and skipping weekends.
$previewDays = [];
for ($i = 0, $d = strtotime('today'); count($previewDays) < 5; $i++) {
    $day = strtotime("+$i days", $d);
    if ((int) date('N', $day) <= 5) {
        $previewDays[] = $day;
    }
}

render_head('Book a consultation');
site_header($user, 'index.php');
?>
<main id="main">
  <div class="container">
    <section class="hero">
      <div>
        <span class="hero-kicker"><span class="pill">UHS</span> University of Ibadan</span>
        <h1>Appointment scheduling for the <em>University Health Service.</em></h1>
        <p class="hero-lead">Register, view available consultation periods, request an appointment with a doctor, cancel or reschedule it, and receive its status, all in one place.</p>
        <div class="hero-actions">
          <?php if ($user): ?>
            <a class="btn btn-primary btn-lg" href="<?= e(url(home_for($user['role']))) ?>">Go to your dashboard <?= icon('arrow-right', 17) ?></a>
          <?php else: ?>
            <a class="btn btn-primary btn-lg" href="<?= e(url('register.php')) ?>">Book an appointment <?= icon('arrow-right', 17) ?></a>
            <a class="btn btn-secondary btn-lg" href="<?= e(url('login.php')) ?>">Sign in</a>
          <?php endif; ?>
        </div>
        <div class="hero-facts">
          <span><?= icon('stethoscope', 16) ?> <?= e(plural($doctorCount, 'doctor')) ?> taking bookings</span>
          <span><?= icon('calendar-check', 16) ?> Cancel or reschedule online</span>
          <span><?= icon('bell', 16) ?> Status updates and reminders</span>
        </div>
      </div>

      <div class="preview">
        <img class="hero-photo" src="<?= e(asset('img/hero-doctor.jpg')) ?>" alt="A smiling doctor in a white coat holding a stethoscope" width="1600" height="1679" fetchpriority="high">
        <div class="preview-card" aria-hidden="true">
          <div class="preview-top">
            <div class="row">
              <span class="avatar"><?= $sample ? e(initials($sample['name'])) : icon('stethoscope', 15) ?></span>
              <div>
                <div style="font-weight:500"><?= e($sample['name'] ?? 'Your doctor') ?></div>
                <div class="muted" style="font-size:12.5px"><?= e($sample['clinic'] ?? 'University Health Service') ?></div>
              </div>
            </div>
            <span class="badge badge-approved"><span class="badge-dot"></span>Available</span>
          </div>
          <div class="preview-days">
            <?php foreach ($previewDays as $i => $day): ?>
              <div class="preview-day<?= $i === 1 ? ' on' : '' ?>"><?= e(date('D', $day)) ?><b><?= e(date('j', $day)) ?></b></div>
            <?php endforeach; ?>
          </div>
          <div class="preview-slots">
            <?php foreach (['8:00', '8:30', '9:00', '9:30', '10:00', '10:30', '11:00', '11:30'] as $i => $t): ?>
              <div class="preview-slot<?= in_array($i, [1, 4], true) ? ' off' : ($i === 5 ? ' on' : '') ?>"><?= e($t) ?></div>
            <?php endforeach; ?>
          </div>
          <div class="preview-foot">
            <?= icon('calendar-clock', 18, 'muted') ?>
            <div style="flex:1">
              <div style="font-weight:500;font-size:13px"><?= e(date('l, j F', $previewDays[1])) ?> at 10:30 AM</div>
              <div class="muted" style="font-size:12px">Reason for visit</div>
            </div>
            <span class="btn btn-primary btn-sm">Confirm</span>
          </div>
        </div>
        <div class="preview-float" aria-hidden="true">
          <span class="ok"><?= icon('check', 16) ?></span>
          <div>
            <div style="font-weight:500;font-size:13px">Your appointment is confirmed</div>
            <div class="muted" style="font-size:12px;margin-top:2px">We will remind you the day before.</div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <section class="section">
    <div class="container">
      <div class="explore">
        <a class="card explore-card" href="<?= e(url('how-it-works.php')) ?>">
          <img src="<?= e(asset('img/consultation.jpg')) ?>" alt="" width="1600" height="1067" loading="lazy">
          <div class="explore-body">
            <span class="eyebrow">Step by step</span>
            <h3>How it works</h3>
            <p>From choosing a clinic type and doctor to tracking your appointment's status.</p>
            <span class="go">Read how booking works <?= icon('arrow-right', 15) ?></span>
          </div>
        </a>
        <a class="card explore-card" href="<?= e(url('doctors.php')) ?>">
          <span class="icon-panel"><?= icon('stethoscope', 36) ?></span>
          <div class="explore-body">
            <span class="eyebrow"><?= e(plural($doctorCount, 'doctor')) ?></span>
            <h3>Doctors and consultation hours</h3>
            <p>See each doctor's clinic, specialty and the days and hours they consult.</p>
            <span class="go">View doctors <?= icon('arrow-right', 15) ?></span>
          </div>
        </a>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="section-head">
        <h2>Built for everyone involved in a visit.</h2>
      </div>
      <div class="roles">
        <div class="card role-card">
          <div class="icon-box"><?= icon('user', 19) ?></div>
          <h3>Patients</h3>
          <ul>
            <li><?= icon('check', 15) ?> Register with your matric number</li>
            <li><?= icon('check', 15) ?> Book, cancel or reschedule online</li>
            <li><?= icon('check', 15) ?> Track status and get reminders</li>
          </ul>
        </div>
        <div class="card role-card">
          <div class="icon-box"><?= icon('stethoscope', 19) ?></div>
          <h3>Doctors</h3>
          <ul>
            <li><?= icon('check', 15) ?> See today's list and the week ahead</li>
            <li><?= icon('check', 15) ?> Approve, decline or complete visits</li>
            <li><?= icon('check', 15) ?> Set consulting days and hours</li>
          </ul>
        </div>
        <div class="card role-card">
          <div class="icon-box"><?= icon('shield-check', 19) ?></div>
          <h3>Administrators</h3>
          <ul>
            <li><?= icon('check', 15) ?> Manage patients, doctors and appointments</li>
            <li><?= icon('check', 15) ?> Oversee every appointment</li>
            <li><?= icon('check', 15) ?> Export reports and review the audit log</li>
          </ul>
        </div>
      </div>
    </div>
  </section>
</main>
<?php
site_footer();
render_foot();
