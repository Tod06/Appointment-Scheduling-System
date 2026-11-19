<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_role('admin');
$week = query('week');
$weekStart = week_start(valid_date($week) ? $week : date('Y-m-d'));
$weekEnd = date('Y-m-d', strtotime("$weekStart +6 days"));
$clinic = query('clinic');
$doctorId = to_int(query('doctor'));

$where = ['a.appointment_date BETWEEN ? AND ?', "a.status <> 'Cancelled'"];
$params = [$weekStart, $weekEnd];
if ($clinic !== '') { $where[] = 'd.clinic = ?'; $params[] = $clinic; }
if ($doctorId) { $where[] = 'a.doctor_id = ?'; $params[] = $doctorId; }
$appointments = q_all(APPOINTMENT_SELECT . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY a.appointment_date, a.appointment_time', $params);

$clinics = clinic_types(false);
$doctors = q_all('SELECT id, name, clinic FROM doctors ORDER BY name');

app_start($user, 'Calendar', [
    'active' => 'admin/calendar.php',
    'subtitle' => plural(count($appointments), 'active appointment') . ' this week across ' . ($doctorId || $clinic !== '' ? 'the selected filter' : 'all doctors') . '.',
]);
?>
<section class="card">
  <form class="filters" method="get">
    <?php if ($week !== ''): ?><input type="hidden" name="week" value="<?= e($weekStart) ?>"><?php endif; ?>
    <div class="field">
      <label class="label" for="clinic">Clinic</label>
      <select class="select" id="clinic" name="clinic" data-autosubmit>
        <option value="">All clinics</option>
        <?php foreach ($clinics as $c): ?><option value="<?= e($c) ?>" <?= $clinic === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label class="label" for="doctor">Doctor</label>
      <select class="select" id="doctor" name="doctor" data-autosubmit>
        <option value="">All doctors</option>
        <?php foreach ($doctors as $d): ?>
          <?php if ($clinic !== '' && $d['clinic'] !== $clinic) continue; ?>
          <option value="<?= (int) $d['id'] ?>" <?= $doctorId === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="margin-left:auto"><?= week_nav($weekStart) ?></div>
  </form>
  <?= week_calendar($appointments, $weekStart, 'admin') ?>
</section>
<?php app_end();
