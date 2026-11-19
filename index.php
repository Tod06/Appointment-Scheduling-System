<?php
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$clinics = q_all(
    'SELECT c.name, c.description, COUNT(d.id) AS doctors,
            MIN(d.start_time) AS opens, MAX(d.end_time) AS closes
     FROM clinics c LEFT JOIN doctors d ON d.clinic_id = c.id AND d.is_active = 1
     WHERE c.is_active = 1 GROUP BY c.id ORDER BY c.name'
);
$doctorCount = array_sum(array_map(fn ($c) => (int) $c['doctors'], $clinics));

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
        <p class="hero-lead">Choose a clinic, pick a doctor and a free time that suits you, and follow your request until it is confirmed. No early-morning lines, no lost cards.</p>
        <div class="hero-actions">
          <?php if ($user): ?>
            <a class="btn btn-primary btn-lg" href="<?= e(url(home_for($user['role']))) ?>">Go to your dashboard <?= icon('arrow-right', 17) ?></a>
          <?php else: ?>
            <a class="btn btn-primary btn-lg" href="<?= e(url('register.php')) ?>">Book an appointment <?= icon('arrow-right', 17) ?></a>
            <a class="btn btn-secondary btn-lg" href="<?= e(url('login.php')) ?>">Sign in</a>
          <?php endif; ?>
        </div>
        <div class="hero-facts">
          <span><?= icon('building', 16) ?> <?= e(plural(count($clinics), 'clinic')) ?></span>
          <span><?= icon('stethoscope', 16) ?> <?= e(plural($doctorCount, 'doctor')) ?> taking bookings</span>
          <span><?= icon('shield-check', 16) ?> Your details stay private</span>
        </div>
      </div>

      <div class="preview" aria-hidden="true">
        <div class="preview-card">
          <div class="preview-top">
            <div class="row">
              <span class="avatar">AO</span>
              <div>
                <div style="font-weight:500">Dr. Adaeze Okonkwo</div>
                <div class="muted" style="font-size:12.5px">General Outpatient</div>
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
              <div class="muted" style="font-size:12px">Routine check-up</div>
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
          <h3>Choose a clinic and doctor</h3>
          <p>Browse the clinics and the doctors who consult there, with the days and hours each one is available.</p>
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

  <section class="section" id="clinics">
    <div class="container">
      <div class="section-head">
        <h2>Clinics taking appointments.</h2>
        <p>Consultation hours vary by doctor. You will see each doctor's exact times when you book.</p>
      </div>
      <?php if ($clinics): ?>
      <div class="clinic-grid">
        <?php foreach ($clinics as $c): ?>
          <div class="clinic">
            <h3><?= e($c['name']) ?></h3>
            <p><?= e($c['description']) ?></p>
            <div class="meta">
              <?= icon('stethoscope', 14) ?> <?= e(plural((int) $c['doctors'], 'doctor')) ?>
              <?php if ($c['opens']): ?><span>·</span> <?= e(fmt_time($c['opens'])) ?> to <?= e(fmt_time($c['closes'])) ?><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
        <div class="card"><?= empty_state('building', 'No clinics yet', 'An administrator needs to add clinics before bookings open.') ?></div>
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
            <li><?= icon('check', 15) ?> Manage patients, doctors and clinics</li>
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
