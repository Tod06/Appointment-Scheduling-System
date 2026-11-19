<?php
require __DIR__ . '/includes/bootstrap.php';

$user = require_role('patient', 'doctor');
$owner = [$user['role'], $user['id']];

// Opening a notification marks it read and follows its link.
if ($openId = to_int(query('open'))) {
    $n = q_one('SELECT * FROM notifications WHERE id = ? AND user_role = ? AND user_id = ?', array_merge([$openId], $owner));
    if ($n) {
        q('UPDATE notifications SET is_read = 1 WHERE id = ?', [$openId]);
        $link = $n['link'];
        if ($link !== '' && !preg_match('#^([a-z]+:|//)#i', $link)) {
            redirect($link);
        }
    }
    redirect('notifications.php');
}

if (is_post()) {
    verify_csrf();
    if (post('action') === 'read_all') {
        q('UPDATE notifications SET is_read = 1 WHERE user_role = ? AND user_id = ? AND is_read = 0', $owner);
        flash('success', 'All notifications marked as read.');
    }
    redirect('notifications.php');
}

$total = (int) q_val('SELECT COUNT(*) FROM notifications WHERE user_role = ? AND user_id = ?', $owner);
$p = paginate($total, 25);
$items = q_all(
    'SELECT * FROM notifications WHERE user_role = ? AND user_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . $p['per_page'] . ' OFFSET ' . $p['offset'],
    $owner
);
$unread = unread_notifications($user);

$actions = $unread
    ? '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="read_all"><button class="btn btn-secondary" type="submit">' . icon('check', 16) . ' Mark all as read</button></form>'
    : '';

app_start($user, 'Notifications', [
    'active' => 'notifications.php',
    'subtitle' => $unread ? 'You have ' . plural($unread, 'unread notification') . '.' : 'You are all caught up.',
    'actions' => $actions,
]);
?>
<div class="card">
  <?php if (!$items): ?>
    <?= empty_state('inbox', 'No notifications yet', 'Updates about your appointments will appear here.') ?>
  <?php else: ?>
    <ul class="list">
      <?php foreach ($items as $n): ?>
        <li>
          <a class="list-item" href="<?= e(url('notifications.php?open=' . $n['id'])) ?>" style="align-items:flex-start">
            <span class="avatar avatar-sm" style="<?= $n['is_read'] ? 'background:var(--surface-3);color:var(--ink-3)' : '' ?>"><?= icon('bell', 13) ?></span>
            <div class="list-main">
              <div class="row-between">
                <div class="list-title" style="<?= $n['is_read'] ? 'font-weight:400;color:var(--ink-2)' : '' ?>"><?= e($n['title']) ?></div>
                <span class="muted" style="font-size:12px;white-space:nowrap"><?= e(time_ago($n['created_at'])) ?></span>
              </div>
              <?php if ($n['body'] !== ''): ?><p class="muted" style="font-size:13px;margin-top:2px"><?= e($n['body']) ?></p><?php endif; ?>
            </div>
            <?php if (!$n['is_read']): ?><span class="badge-dot" style="color:var(--accent);margin-top:8px" aria-label="Unread"></span><?php endif; ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
    <?= pagination_bar($p) ?>
  <?php endif; ?>
</div>
<?php app_end();
