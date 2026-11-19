<?php
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();

// What each appointment status means (Appendix A of the project report).
$statuses = [
    'Pending' => 'You have requested the appointment. The time is held for you while the doctor reviews it.',
    'Approved' => 'The doctor has confirmed the appointment. Attend at the date and time shown.',
    'Rescheduled' => 'You asked to move the appointment to a new time. The doctor reviews the new time.',
    'Completed' => 'The consultation has taken place.',
    'Cancelled' => 'The appointment was cancelled by you or the clinic, and the time was released.',
];

render_head('How it works');
site_header($user, 'how-it-works.php');
?>
<main id="main">
  <div class="container">
    <section class="page-intro">
      <span class="eyebrow">How it works</span>
      <h1>From request to consultation in three steps.</h1>
      <p>Every request is stored in one central record, so patients and authorized staff work with the same scheduling information.</p>
    </section>
  </div>

  <section class="section tight">
    <div class="container">
      <div class="how-wrap">
        <div class="how-photo"><img src="<?= e(asset('img/consultation.jpg')) ?>" alt="A doctor at her desk consulting a young patient" width="1600" height="1067" loading="lazy"></div>
        <div class="how">
          <div class="how-step">
            <div class="how-num">01</div>
            <h3>Choose a clinic type and doctor</h3>
            <p>Sign in, then choose the clinic type and a doctor. You can see each doctor's specialty and the days and hours they consult.</p>
          </div>
          <div class="how-step">
            <div class="how-num">02</div>
            <h3>Pick a free date and time</h3>
            <p>The system checks that the period is available. If it has been taken, you see a message and choose another time.</p>
          </div>
          <div class="how-step">
            <div class="how-num">03</div>
            <h3>Confirm and track its status</h3>
            <p>Add the reason for your visit and confirm. The doctor approves the request and you are notified of every change.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="section-head">
        <h2>What your appointment status means.</h2>
        <p>You can see the current status of every appointment on your dashboard.</p>
      </div>
      <div class="status-guide">
        <?php foreach ($statuses as $status => $meaning): ?>
          <div class="card status-item">
            <?= status_badge($status) ?>
            <p><?= e($meaning) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="section-head">
        <h2>Changing or cancelling.</h2>
        <p>Plans change. You can manage your appointment yourself until shortly before it starts.</p>
      </div>
      <div class="tips">
        <div class="card tip">
          <span class="icon-box"><?= icon('refresh', 18) ?></span>
          <h3>Reschedule</h3>
          <p>Open the appointment and choose Reschedule to pick a new time. The doctor confirms the new time.</p>
        </div>
        <div class="card tip">
          <span class="icon-box"><?= icon('calendar-x', 18) ?></span>
          <h3>Cancel</h3>
          <p>Cancel from the appointment page so the time is released for other patients.</p>
        </div>
        <div class="card tip">
          <span class="icon-box"><?= icon('clock', 18) ?></span>
          <h3>Close to the time</h3>
          <p>Within <?= (int) config('change_cutoff_hours', 2) ?> hours of the appointment, changes cannot be made online. Contact the clinic instead.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="section-head">
        <h2>Before your visit.</h2>
      </div>
      <div class="tips">
        <div class="card tip">
          <span class="icon-box"><?= icon('clock', 18) ?></span>
          <h3>Arrive early</h3>
          <p>Arrive 10 minutes before your appointment time.</p>
        </div>
        <div class="card tip">
          <span class="icon-box"><?= icon('id-card', 18) ?></span>
          <h3>Bring your ID card</h3>
          <p>Bring your university identity card to every visit.</p>
        </div>
        <div class="card tip">
          <span class="icon-box"><?= icon('file-text', 18) ?></span>
          <h3>Bring your records</h3>
          <p>Bring any previous prescriptions or test results that relate to your visit.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="cta-band">
        <div>
          <h2>Ready to book?</h2>
          <p>See the doctors and their consultation hours, or go straight to booking.</p>
        </div>
        <div class="row wrap">
          <a class="btn btn-secondary btn-lg" href="<?= e(url('doctors.php')) ?>">View doctors</a>
          <a class="btn btn-light btn-lg" href="<?= e(url($user ? home_for($user['role']) : 'register.php')) ?>"><?= $user ? 'Go to your dashboard' : 'Book an appointment' ?> <?= icon('arrow-right', 17) ?></a>
        </div>
      </div>
    </div>
  </section>
</main>
<?php
site_footer();
render_foot();
