<?php
defined('APP') || exit;

const ROLES = ['patient', 'doctor', 'admin'];
const SESSION_IDLE_SECONDS = 7200;
const MAX_LOGIN_FAILURES = 5;

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name('uhs_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => base_path() === '' ? '/' : base_path() . '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();

    $now = time();
    if (isset($_SESSION['last_seen']) && $now - $_SESSION['last_seen'] > SESSION_IDLE_SECONDS && isset($_SESSION['uid'])) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash('info', 'You were signed out after a period of inactivity.');
    }
    $_SESSION['last_seen'] = $now;
}

function user_table(string $role): string
{
    return match ($role) {
        'patient' => 'patients',
        'doctor' => 'doctors',
        'admin' => 'admins',
    };
}

/** Primary key column of each user table, as named in the report's ER diagram. */
function user_pk(string $role): string
{
    return match ($role) {
        'patient' => 'patient_id',
        'doctor' => 'doctor_id',
        'admin' => 'admin_id',
    };
}

/** Adds a generic 'id' key so code shared by all roles can refer to the user the same way. */
function with_user_id(string $role, array $row): array
{
    $row['id'] = (int) $row[user_pk($role)];
    return $row;
}

function role_label(string $role): string
{
    return match ($role) {
        'patient' => 'Patient',
        'doctor' => 'Doctor',
        'admin' => 'Administrator',
        default => ucfirst($role),
    };
}

/** The signed-in user's row with an extra 'role' key, or null. */
function current_user(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $role = $_SESSION['role'] ?? null;
    $id = (int) ($_SESSION['uid'] ?? 0);
    if (!$id || !in_array($role, ROLES, true)) {
        return $cache = null;
    }
    $row = q_one('SELECT * FROM ' . user_table($role) . ' WHERE ' . user_pk($role) . ' = ?', [$id]);
    if (!$row || !(int) $row['is_active']) {
        unset($_SESSION['uid'], $_SESSION['role']);
        return $cache = null;
    }
    unset($row['password_hash']);
    $row = with_user_id($role, $row);
    $row['role'] = $role;
    return $cache = $row;
}

function login_user(string $role, array $row): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $row['id'];
    $_SESSION['role'] = $role;
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $p['path'],
            'domain' => $p['domain'],
            'secure' => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}

function home_for(string $role): string
{
    return match ($role) {
        'patient' => 'patient/dashboard.php',
        'doctor' => 'doctor/dashboard.php',
        'admin' => 'admin/dashboard.php',
    };
}

/** Stops the page unless a user with one of $roles is signed in. */
function require_role(string ...$roles): array
{
    $user = current_user();
    if (!$user) {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $_SESSION['intended'] = $_SERVER['REQUEST_URI'] ?? '';
        }
        flash('info', 'Sign in to continue.');
        redirect('login.php');
    }
    if ($roles && !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        render_error_page(403, 'Not available to your account', 'This page belongs to a different type of account. Use the menu to go back to your own pages.');
    }
    return $user;
}

/* ---------- Sign-in throttling ---------- */

function login_locked(string $identifier): bool
{
    $failures = (int) q_val(
        'SELECT COUNT(*) FROM login_attempts WHERE identifier = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)',
        [mb_strtolower($identifier)]
    );
    return $failures >= MAX_LOGIN_FAILURES;
}

function record_login_failure(string $identifier): void
{
    q('INSERT INTO login_attempts (identifier, ip) VALUES (?, ?)', [mb_strtolower($identifier), client_ip()]);
    // Keep the table small.
    if (random_int(1, 50) === 1) {
        q('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    }
}

function clear_login_failures(string $identifier): void
{
    q('DELETE FROM login_attempts WHERE identifier = ?', [mb_strtolower($identifier)]);
}

/**
 * Checks credentials for a role. Patients may sign in with email or matric number.
 * Returns the user row or null.
 */
function attempt_login(string $role, string $identifier, string $password): ?array
{
    $table = user_table($role);
    if ($role === 'patient') {
        $row = q_one("SELECT * FROM patients WHERE email = ? OR matric_no = ? LIMIT 1", [$identifier, strtoupper($identifier)]);
    } else {
        $row = q_one("SELECT * FROM {$table} WHERE email = ? LIMIT 1", [$identifier]);
    }

    $hash = $row['password_hash'] ?? null;
    if (!$row || !$hash) {
        // Spend the same time as a real check so response timing does not reveal accounts.
        password_verify($password, password_hash('timing-guard', PASSWORD_DEFAULT));
        return null;
    }
    if (!password_verify($password, $hash)) {
        return null;
    }
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        q("UPDATE {$table} SET password_hash = ? WHERE " . user_pk($role) . ' = ?', [password_hash($password, PASSWORD_DEFAULT), $row[user_pk($role)]]);
    }
    return with_user_id($role, $row);
}
