<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_role('admin');
$week = query('week');
$weekStart = week_start(valid_date($week) ? $week : date('Y-m-d'));
$weekEnd = date('Y-m-d', strtotime("$weekStart +6 days"));
$clinicId = to_int(query('clinic'));
$doctorId = to_int(query('doctor'));

$where = ['a.appointment_date BETWEEN ? AND ?', "a.status <> 'Cancelled'"];
$params = [$weekStart, $weekEnd];
if ($clinicId) { $where[] = 'd.clinic_id = ?'; $params[] = $clinicId; }
if ($doctorId) { $where[] = 'a.doctor_id = ?'; $params[] = $doctorId; }
$appointments = q_all(APPOINTMENT_SELECT . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY a.appointment_date, a.appointment_time', $params);

$clinics = q_all('SELECT id, name FROM clinics ORDER BY name');
$doctors = q_all('SELECT id, name, clinic_id FROM doctors ORDER BY name');

app_start($user, 'Calendar', [
    'active' => 'admin/calendar.php',
    'subtitle' => plural(count($appointments), 'active appointment') . ' this week across ' . ($doctorId || $clinicId ? 'the selected filter' : 'all clinics') . '.',
]);
?>
<section class="card">
  <form class="filters" method="get">
    <?php if ($week !== ''): ?><input type="hidden" name="week" value="<?= e($weekStart) ?>"><?php endif; ?>
    <div class="field">
      <label class="label" for="clinic">Clinic</label>
      <select class="select" id="clinic" name="clinic" data-autosubmit>
        <option value="">All clinics</option>
        <?php foreach ($clinics as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $clinicId === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label class="label" for="doctor">Doctor</label>
      <select class="select" id="doctor" name="doctor" data-autosubmit>
        <option value="">All doctors</option>
        <?php foreach ($doctors as $d): ?>
          <?php if ($clinicId && (int) $d['clinic_id'] !== $clinicId) continue; ?>
          <option value="<?= (int) $d['id'] ?>" <?= $doctorId === (int) $d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="margin-left:auto"><?= week_nav($weekStart) ?></div>
  </form>
  <?= week_calendar($appointments, $weekStart, 'admin') ?>
</section>
<?php app_end();
