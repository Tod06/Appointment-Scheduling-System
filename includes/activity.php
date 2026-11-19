<?php
defined('APP') || exit;

/* ---------- In-app notifications ---------- */

function notify(string $role, int $userId, string $title, string $body = '', string $link = ''): void
{
    q(
        'INSERT INTO notifications (user_role, user_id, title, body, link) VALUES (?, ?, ?, ?, ?)',
        [$role, $userId, mb_substr($title, 0, 160), mb_substr($body, 0, 500), $link]
    );
}

function unread_notifications(array $user): int
{
    return (int) q_val(
        'SELECT COUNT(*) FROM notifications WHERE user_role = ? AND user_id = ? AND is_read = 0',
        [$user['role'], $user['id']]
    );
}

/* ---------- Administrator audit trail ---------- */

function audit(?int $adminId, string $action, string $entity, ?int $entityId = null, string $details = ''): void
{
    q(
        'INSERT INTO audit_log (admin_id, action, entity, entity_id, details, ip) VALUES (?, ?, ?, ?, ?, ?)',
        [$adminId, $action, $entity, $entityId, mb_substr($details, 0, 500), client_ip()]
    );
}
