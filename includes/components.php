<?php
defined('APP') || exit;

/* Reusable pieces of interface shared by several pages. */

function status_badge(string $status): string
{
    return '<span class="badge badge-' . e(strtolower($status)) . '"><span class="badge-dot"></span>' . e($status) . '</span>';
}

function empty_state(string $iconName, string $title, string $text, string $action = ''): string
{
    return '<div class="empty"><div class="empty-icon">' . icon($iconName, 20) . '</div><h3>' . e($title) . '</h3><p>' . e($text) . '</p>' . $action . '</div>';
}

function date_chip(string $date): string
{
    return '<div class="date-chip" aria-hidden="true"><div class="date-chip-month">' . e(date('M', strtotime($date))) . '</div><div class="date-chip-day">' . e(date('j', strtotime($date))) . '</div></div>';
}

function password_input(string $name, string $autocomplete, string $id = '', string $placeholder = ''): string
{
    $id = $id ?: $name;
    return '<div class="input-group">' . icon('lock', 16, 'icon-lead')
        . '<input class="input" type="password" id="' . e($id) . '" name="' . e($name) . '" autocomplete="' . e($autocomplete) . '" placeholder="' . e($placeholder) . '" required>'
        . '<button type="button" class="input-action" data-toggle-password="' . e($id) . '" aria-label="Show password">' . icon('eye', 16) . '</button></div>';
}

/** One appointment as a row in a card list. $perspective decides which person is named. */
function appointment_list_item(array $a, string $perspective): string
{
    $who = $perspective === 'patient'
        ? e($a['doctor_name']) . ' <span class="muted">· ' . e($a['clinic_name']) . '</span>'
        : e($a['patient_name']) . ' <span class="muted">· ' . e($perspective === 'admin' ? $a['doctor_name'] : ($a['reason'] ?: 'No reason given')) . '</span>';
    return '<a class="list-item" href="' . e(url('appointment.php?id=' . $a['id'])) . '">'
        . date_chip($a['appointment_date'])
        . '<div class="list-main"><div class="list-title">' . $who . '</div>'
        . '<div class="list-sub">' . e(relative_day($a['appointment_date'])) . ', ' . e(fmt_time($a['appointment_time'])) . '</div></div>'
        . status_badge($a['status'])
        . icon('chevron-right', 16, 'muted')
        . '</a>';
}

/** Table of appointments. Columns adapt to who is looking. */
function appointments_table(array $rows, string $perspective): string
{
    ob_start();
    ?>
<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th>Date and time</th>
        <?php if ($perspective !== 'patient'): ?><th>Patient</th><?php endif; ?>
        <?php if ($perspective !== 'doctor'): ?><th>Doctor</th><?php endif; ?>
        <th class="hide-sm">Reason</th>
        <th>Status</th>
        <th class="actions"><span class="sr-only">Open</span></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $a): ?>
      <tr>
        <td>
          <div class="cell-main num"><?= e(fmt_date($a['appointment_date'])) ?></div>
          <div class="cell-sub num"><?= e(fmt_time($a['appointment_time'])) ?></div>
        </td>
        <?php if ($perspective !== 'patient'): ?>
        <td>
          <div class="cell-main"><?= e($a['patient_name']) ?></div>
          <div class="cell-sub"><?= e($a['matric_no'] ?: $a['patient_email']) ?></div>
        </td>
        <?php endif; ?>
        <?php if ($perspective !== 'doctor'): ?>
        <td>
          <div class="cell-main"><?= e($a['doctor_name']) ?></div>
          <div class="cell-sub"><?= e($a['clinic_name']) ?></div>
        </td>
        <?php endif; ?>
        <td class="hide-sm" style="max-width:260px"><div class="cell-sub" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($a['reason'] ?: '—') ?></div></td>
        <td><?= status_badge($a['status']) ?></td>
        <td class="actions"><a class="btn btn-secondary btn-sm" href="<?= e(url('appointment.php?id=' . $a['id'])) ?>">View</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
    return (string) ob_get_clean();
}

function pagination_bar(array $p): string
{
    if ($p['pages'] <= 1) {
        return $p['total'] ? '<div class="pagination"><span>' . e(plural($p['total'], 'record')) . '</span></div>' : '';
    }
    $from = $p['offset'] + 1;
    $to = min($p['total'], $p['offset'] + $p['per_page']);
    $prev = $p['page'] > 1 ? '<a class="btn btn-secondary btn-sm" href="' . e(query_with(['page' => $p['page'] - 1])) . '">' . icon('chevron-left', 15) . ' Previous</a>' : '<span class="btn btn-secondary btn-sm is-disabled">' . icon('chevron-left', 15) . ' Previous</span>';
    $next = $p['page'] < $p['pages'] ? '<a class="btn btn-secondary btn-sm" href="' . e(query_with(['page' => $p['page'] + 1])) . '">Next ' . icon('chevron-right', 15) . '</a>' : '<span class="btn btn-secondary btn-sm is-disabled">Next ' . icon('chevron-right', 15) . '</span>';
    return '<div class="pagination"><span class="num">' . $from . '–' . $to . ' of ' . $p['total'] . '</span><div class="row">' . $prev . $next . '</div></div>';
}

/**
 * Seven-day agenda. $appointments must include appointment_date, appointment_time,
 * status, patient_name, doctor_name and clinic_name.
 */
function week_calendar(array $appointments, string $weekStart, string $perspective): string
{
    $byDay = [];
    foreach ($appointments as $a) {
        $byDay[$a['appointment_date']][] = $a;
    }
    $today = date('Y-m-d');
    ob_start();
    echo '<div class="week">';
    for ($i = 0; $i < 7; $i++) {
        $date = date('Y-m-d', strtotime("$weekStart +$i days"));
        $dow = (int) date('N', strtotime($date));
        $classes = 'week-day' . ($date === $today ? ' is-today' : '') . ($dow >= 6 ? ' is-weekend' : '');
        echo '<section class="' . $classes . '" aria-label="' . e(fmt_date($date)) . '">';
        echo '<div class="week-day-head"><div class="w">' . e(date('D', strtotime($date))) . '</div><div class="d">' . e(date('j', strtotime($date))) . '</div></div>';
        echo '<div class="week-day-body">';
        if (empty($byDay[$date])) {
            echo '<div class="week-empty">No appointments</div>';
        } else {
            foreach ($byDay[$date] as $a) {
                $sub = $perspective === 'doctor' ? ($a['reason'] ?: $a['clinic_name']) : $a['doctor_name'];
                echo '<a class="week-event ' . e(strtolower($a['status'])) . '" href="' . e(url('appointment.php?id=' . $a['id'])) . '" title="' . e($a['status']) . '">'
                    . '<div class="t">' . e(fmt_time($a['appointment_time'])) . '</div>'
                    . '<div class="n">' . e($a['patient_name']) . '</div>'
                    . '<div class="s">' . e($sub) . '</div></a>';
            }
        }
        echo '</div></section>';
    }
    echo '</div>';
    return (string) ob_get_clean();
}

function week_nav(string $weekStart, array $extra = []): string
{
    $prev = date('Y-m-d', strtotime("$weekStart -7 days"));
    $next = date('Y-m-d', strtotime("$weekStart +7 days"));
    $end = date('Y-m-d', strtotime("$weekStart +6 days"));
    $label = date('j M', strtotime($weekStart)) . ' – ' . date('j M Y', strtotime($end));
    return '<div class="row wrap">'
        . '<a class="btn btn-secondary btn-sm btn-icon" href="' . e(query_with(array_merge($extra, ['week' => $prev]))) . '" aria-label="Previous week">' . icon('chevron-left', 16) . '</a>'
        . '<a class="btn btn-secondary btn-sm" href="' . e(query_with(array_merge($extra, ['week' => null]))) . '">This week</a>'
        . '<a class="btn btn-secondary btn-sm btn-icon" href="' . e(query_with(array_merge($extra, ['week' => $next]))) . '" aria-label="Next week">' . icon('chevron-right', 16) . '</a>'
        . '<span class="num" style="font-weight:500;margin-left:6px">' . e($label) . '</span></div>';
}

const SLOT_LENGTHS = [15, 20, 30, 45, 60];

/** Checks a consulting schedule submitted by a doctor or administrator. */
function validate_schedule($days, string $start, string $end, string $slot): array
{
    $days = is_array($days) ? array_values(array_unique(array_filter(array_map('intval', $days), fn ($d) => $d >= 1 && $d <= 7))) : [];
    sort($days);
    $form = ['days' => $days, 'start' => $start, 'end' => $end, 'slot' => (int) $slot];
    $errors = [];
    if (!$days) {
        $errors[] = 'Choose at least one consulting day.';
    }
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $end)) {
        $errors[] = 'Enter valid start and end times.';
    } elseif ($end <= $start) {
        $errors[] = 'The end time must be after the start time.';
    }
    if (!in_array($form['slot'], SLOT_LENGTHS, true)) {
        $errors[] = 'Choose a slot length from the list.';
    } elseif (!$errors && (strtotime("2000-01-01 $end") - strtotime("2000-01-01 $start")) < $form['slot'] * 60) {
        $errors[] = 'The consulting hours are shorter than one slot.';
    }
    return ['form' => $form, 'errors' => $errors];
}

/** Form fields for a consulting schedule. */
function schedule_fields(array $form): string
{
    ob_start();
    ?>
<div class="card-header"><div><h2>Consulting schedule</h2><p class="hint">Patients can only pick times inside these hours.</p></div></div>
<div class="card-body form-grid cols-2">
  <div class="field span-2">
    <span class="label">Consulting days</span>
    <div class="day-toggles">
      <?php foreach (WEEKDAY_NAMES as $num => $label): ?>
        <input type="checkbox" name="days[]" id="day-<?= $num ?>" value="<?= $num ?>" <?= in_array($num, $form['days'], true) ? 'checked' : '' ?>>
        <label for="day-<?= $num ?>"><?= e($label) ?></label>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="field">
    <label class="label" for="start_time">Starts at</label>
    <input class="input" type="time" id="start_time" name="start_time" value="<?= e($form['start']) ?>" step="300" required>
  </div>
  <div class="field">
    <label class="label" for="end_time">Ends at</label>
    <input class="input" type="time" id="end_time" name="end_time" value="<?= e($form['end']) ?>" step="300" required>
  </div>
  <div class="field">
    <label class="label" for="slot_minutes">Length of each appointment</label>
    <select class="select" id="slot_minutes" name="slot_minutes">
      <?php foreach (SLOT_LENGTHS as $m): ?>
        <option value="<?= $m ?>" <?= (int) $form['slot'] === $m ? 'selected' : '' ?>><?= $m ?> minutes</option>
      <?php endforeach; ?>
    </select>
  </div>
</div>
<?php
    return (string) ob_get_clean();
}

/** Horizontal stacked bar of status counts with a legend. */
function status_breakdown(array $counts): string
{
    $total = array_sum($counts);
    $bar = '<div class="status-bar" role="img" aria-label="Appointments by status">';
    $legend = '<div class="legend">';
    foreach ($counts as $status => $n) {
        $key = strtolower($status);
        if ($total > 0 && $n > 0) {
            $bar .= '<span class="c-' . e($key) . '" style="width:' . round($n / $total * 100, 2) . '%"></span>';
        }
        $legend .= '<span><i class="c-' . e($key) . '"></i>' . e($status) . ' <b class="num">' . $n . '</b></span>';
    }
    return $bar . '</div>' . $legend . '</div>';
}
