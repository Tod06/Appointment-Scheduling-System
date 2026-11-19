<?php
require dirname(__DIR__) . '/includes/bootstrap.php';

$user = require_role('admin');

$total = (int) q_val('SELECT COUNT(*) FROM audit_log');
$p = paginate($total, 30);
$rows = q_all(
    "SELECT l.*, ad.name AS admin_name FROM audit_log l LEFT JOIN admins ad ON ad.id = l.admin_id
     ORDER BY l.created_at DESC, l.id DESC LIMIT {$p['per_page']} OFFSET {$p['offset']}"
);

$labels = [
    'appointment.status' => 'Changed appointment status',
    'patient.update' => 'Edited patient',
    'patient.activate' => 'Reactivated patient',
    'patient.deactivate' => 'Deactivated patient',
    'patient.password_reset' => 'Reset patient password',
    'doctor.create' => 'Added doctor',
    'doctor.update' => 'Edited doctor',
    'clinic.create' => 'Added clinic',
    'clinic.update' => 'Edited clinic',
    'clinic.open' => 'Reopened clinic',
    'clinic.close' => 'Closed clinic',
    'report.export' => 'Exported report',
    'account.password' => 'Changed own password',
    'system.install' => 'Installed the system',
];
$links = [
    'appointment' => 'appointment.php?id=',
    'patient' => 'admin/patients.php?id=',
    'doctor' => 'admin/doctors.php?id=',
];

app_start($user, 'Audit log', [
    'active' => 'admin/audit.php',
    'subtitle' => 'A permanent record of administrative changes, newest first.',
]);
?>
<section class="card">
  <?php if ($rows): ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>When</th><th>Administrator</th><th>Action</th><th class="hide-sm">Details</th><th class="hide-sm">IP address</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><div class="cell-main num" style="font-weight:400"><?= e(fmt_datetime($r['created_at'])) ?></div><div class="cell-sub"><?= e(time_ago($r['created_at'])) ?></div></td>
              <td><?= e($r['admin_name'] ?: 'System') ?></td>
              <td>
                <?php if ($r['entity_id'] && isset($links[$r['entity']])): ?>
                  <a class="link-accent" href="<?= e(url($links[$r['entity']] . $r['entity_id'])) ?>"><?= e($labels[$r['action']] ?? $r['action']) ?></a>
                <?php else: ?>
                  <?= e($labels[$r['action']] ?? $r['action']) ?>
                <?php endif; ?>
              </td>
              <td class="hide-sm cell-sub"><?= e($r['details']) ?></td>
              <td class="hide-sm mono cell-sub"><?= e($r['ip']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pagination_bar($p) ?>
  <?php else: ?>
    <?= empty_state('history', 'No changes recorded yet', 'Changes made by administrators will be listed here.') ?>
  <?php endif; ?>
</section>
<?php app_end();
