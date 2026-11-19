<?php
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$doctors = q_all('SELECT name, clinic, specialty, work_days, start_time, end_time FROM doctors WHERE is_active = 1 ORDER BY clinic, name');
$sample = $doctors[0] ?? null;

// Sample week for the preview card, starting from the next weekday.
$previewDays = [];
for ($i = 0, $d = strtotime('today'); count($previewDays) < 5; $i++) {
    $day = strtotime("+$i days", $d);
    if ((int) date('N', $day) <= 5) {
        $previewDays[] = $day;
    }
}

render_head('Book a consultation');
site_header($user);
?>
<main id="main">
  <div class="container">
    <section class="hero">
      <div>
        <span class="hero-kicker"><span class="pill">New</span> Online booking for students and staff</span>
        <h1>See a doctor at the Health Service <em>without the queue.</em></h1>
        <p class="hero-lead">Choose the clinic type, pick a doctor and a free time that suits you, and follow your request until it is confirmed. No early-morning lines, no lost cards.</p>
        <div class="hero-actions">
          <?php if ($user): ?>
            <a class="btn btn-primary btn-lg" href="<?= e(url(home_for($user['role']))) ?>">Go to your dashboard <?= icon('arrow-right', 17) ?></a>
          <?php else: ?>
            <a class="btn btn-primary btn-lg" href="<?= e(url('register.php')) ?>">Book an appointment <?= icon('arrow-right', 17) ?></a>
            <a class="btn btn-secondary btn-lg" href="<?= e(url('login.php')) ?>">Sign in</a>
          <?php endif; ?>
        </div>
        <div class="hero-facts">
          <span><?= icon('stethoscope', 16) ?> <?= e(plural(count($doctors), 'doctor')) ?> taking bookings</span>
          <span><?= icon('calendar-check', 16) ?> Cancel or reschedule online</span>
          <span><?= icon('shield-check', 16) ?> Your details stay private</span>
        </div>
      </div>

      <div class="preview" aria-hidden="true">
        <div class="preview-card">
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
        <div class="preview-float">
          <span class="ok"><?= icon('check', 16) ?></span>
          <div>
            <div style="font-weight:500;font-size:13px">Your appointment is confirmed</div>
            <div class="muted" style="font-size:12px;margin-top:2px">We will remind you the day before.</div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <section class="section" id="how">
    <div class="container">
      <div class="section-head">
        <h2>Three steps from request to consultation.</h2>
        <p>Every request is recorded in one place, so you, your doctor and the clinic staff always see the same information.</p>
      </div>
      <div class="how">
        <div class="how-step">
          <div class="how-num">01</div>
          <h3>Choose a clinic type and doctor</h3>
          <p>See each doctor's clinic, specialty and the days and hours they consult before you book.</p>
        </div>
        <div class="how-step">
          <div class="how-num">02</div>
          <h3>Pick a free time</h3>
          <p>Only open slots can be selected. A slot is held for you the moment you submit, so nobody else can take it.</p>
        </div>
        <div class="how-step">
          <div class="how-num">03</div>
          <h3>Get confirmed</h3>
          <p>Your doctor approves the request and you are notified. Cancel or move it from your dashboard if plans change.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="section" id="doctors">
    <div class="container">
      <div class="section-head">
        <h2>Doctors taking appointments.</h2>
        <p>Consultation days and hours for each doctor. You will see the exact free times when you book.</p>
      </div>
      <?php if ($doctors): ?>
      <div class="clinic-grid">
        <?php foreach ($doctors as $d): ?>
          <div class="clinic">
            <h3><?= e($d['name']) ?></h3>
            <p><?= e($d['clinic']) ?><?= $d['specialty'] !== '' ? ' · ' . e($d['specialty']) : '' ?></p>
            <div class="meta">
              <?= icon('clock', 14) ?> <?= e(describe_work_days($d['work_days'])) ?>, <?= e(fmt_time($d['start_time'])) ?> to <?= e(fmt_time($d['end_time'])) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
        <div class="card"><?= empty_state('stethoscope', 'No doctors yet', 'Bookings open once the administrator adds doctors.') ?></div>
      <?php endif; ?>
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

<footer class="site-footer">
  <div class="container">
    <span><?= e(config('org_name')) ?></span>
    <span>For emergencies, go straight to the Health Service. Do not wait for an appointment.</span>
  </div>
</footer>
<?php render_foot();
