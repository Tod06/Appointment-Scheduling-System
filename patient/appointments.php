<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_role('patient');

$tabs = [
    'upcoming' => 'Upcoming',
    'past' => 'Past',
    'cancelled' => 'Cancelled',
];
$tab = array_key_exists(query('tab'), $tabs) ? query('tab') : 'upcoming';

$active = placeholders(ACTIVE_STATUSES);
$conditions = [
    'upcoming' => ["a.status IN ($active) AND TIMESTAMP(a.appointment_date, a.appointment_time) >= NOW()", ACTIVE_STATUSES, 'ASC'],
    'past' => ["(a.status = 'Completed' OR (a.status IN ($active) AND TIMESTAMP(a.appointment_date, a.appointment_time) < NOW()))", ACTIVE_STATUSES, 'DESC'],
    'cancelled' => ["a.status = 'Cancelled'", [], 'DESC'],
];

$counts = [];
foreach ($conditions as $key => [$where, $params]) {
    $counts[$key] = (int) q_val("SELECT COUNT(*) FROM appointments a WHERE a.patient_id = ? AND $where", array_merge([$user['id']], $params));
}

[$where, $params, $order] = $conditions[$tab];
$p = paginate($counts[$tab], 15);
$rows = q_all(
    APPOINTMENT_SELECT . " WHERE a.patient_id = ? AND $where ORDER BY a.appointment_date $order, a.appointment_time $order LIMIT {$p['per_page']} OFFSET {$p['offset']}",
    array_merge([$user['id']], $params)
);

app_start($user, 'My appointments', [
    'active' => 'patient/appointments.php',
    'subtitle' => 'Everything you have booked with the Health Service.',
    'actions' => '<a class="btn btn-primary" href="' . e(url('patient/book.php')) . '">' . icon('calendar-plus', 16) . ' Book appointment</a>',
]);
?>
<section class="card">
  <nav class="tabs" aria-label="Filter appointments">
    <?php foreach ($tabs as $key => $label): ?>
      <a class="tab<?= $tab === $key ? ' is-active' : '' ?>" href="?tab=<?= $key ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>>
        <?= e($label) ?> <span class="count"><?= $counts[$key] ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <?php if ($rows): ?>
    <?= appointments_table($rows, 'patient') ?>
    <?= pagination_bar($p) ?>
  <?php else: ?>
    <?= match ($tab) {
        'upcoming' => empty_state('calendar-plus', 'No upcoming appointments', 'When you book a visit it will appear here.', '<a class="btn btn-primary" href="' . e(url('patient/book.php')) . '">Book an appointment</a>'),
        'past' => empty_state('history', 'No past appointments', 'Visits you have attended will be listed here.'),
        default => empty_state('calendar-x', 'Nothing cancelled', 'Appointments you or the clinic cancel will be listed here.'),
    } ?>
  <?php endif; ?>
</section>
<?php app_end();
