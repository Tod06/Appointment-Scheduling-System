<?php
defined('APP') || exit;

/* ---------- Configuration and URLs ---------- */

function config(string $key, $default = null)
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

/** Folder the app is served from, e.g. '' or '/Appointment-Scheduling-System'. */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $configured = config('base_url');
    if ($configured !== null) {
        return $base = rtrim((string) $configured, '/');
    }
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '';
    $appRoot = realpath(APP_ROOT) ?: APP_ROOT;
    $docRoot = rtrim(str_replace('\\', '/', $docRoot), '/');
    $appRoot = str_replace('\\', '/', $appRoot);
    $base = '';
    if ($docRoot !== '' && stripos($appRoot, $docRoot) === 0) {
        $base = substr($appRoot, strlen($docRoot));
    }
    return $base = rtrim($base, '/');
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return url('assets/' . ltrim($path, '/')) . '?v=' . $version;
}

function redirect(string $path): void
{
    header('Location: ' . (str_starts_with($path, '/') ? $path : url($path)));
    exit;
}

/** Only allow redirects to paths inside this site. */
function safe_local_path(string $path): ?string
{
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//') || str_contains($path, '\\')) {
        return null;
    }
    return $path;
}

function current_path(): string
{
    return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
}

/* ---------- Output ---------- */

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function initials(string $name): string
{
    $name = preg_replace('/^(dr\.?|mr\.?|mrs\.?|ms\.?|prof\.?)\s+/i', '', trim($name));
    $parts = preg_split('/\s+/', (string) $name) ?: [];
    $out = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $out .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $out !== '' ? $out : '?';
}

function first_name(string $name): string
{
    $name = preg_replace('/^(dr\.?|mr\.?|mrs\.?|ms\.?|prof\.?)\s+/i', '', trim($name));
    return explode(' ', (string) $name)[0] ?? $name;
}

function plural(int $count, string $singular, ?string $pluralForm = null): string
{
    return $count . ' ' . ($count === 1 ? $singular : ($pluralForm ?? $singular . 's'));
}

/* ---------- Dates ---------- */

function fmt_date(string $date): string
{
    return date('D, j M Y', strtotime($date));
}

function fmt_date_short(string $date): string
{
    return date('j M', strtotime($date));
}

function fmt_time(string $time): string
{
    return date('g:i A', strtotime('2000-01-01 ' . $time));
}

function fmt_datetime(string $datetime): string
{
    return date('j M Y, g:i A', strtotime($datetime));
}

function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return plural((int) floor($diff / 60), 'minute') . ' ago';
    }
    if ($diff < 86400) {
        return plural((int) floor($diff / 3600), 'hour') . ' ago';
    }
    if ($diff < 86400 * 7) {
        return plural((int) floor($diff / 86400), 'day') . ' ago';
    }
    return date('j M Y', strtotime($datetime));
}

function relative_day(string $date): string
{
    $today = date('Y-m-d');
    if ($date === $today) {
        return 'Today';
    }
    if ($date === date('Y-m-d', strtotime('+1 day'))) {
        return 'Tomorrow';
    }
    if ($date === date('Y-m-d', strtotime('-1 day'))) {
        return 'Yesterday';
    }
    return date('l', strtotime($date));
}

function valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

function valid_time(string $time): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $time);
}

function normalize_time(string $time): string
{
    return strlen($time) === 5 ? $time . ':00' : $time;
}

/** Monday of the week containing $date. */
function week_start(string $date): string
{
    $ts = strtotime($date);
    return date('Y-m-d', strtotime('-' . (date('N', $ts) - 1) . ' days', $ts));
}

const WEEKDAY_NAMES = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

function describe_work_days(string $days): string
{
    $list = array_values(array_filter(array_map('intval', explode(',', $days))));
    sort($list);
    if ($list === [1, 2, 3, 4, 5]) {
        return 'Mon to Fri';
    }
    return implode(', ', array_map(fn ($d) => WEEKDAY_NAMES[$d] ?? '', $list));
}

/* ---------- Requests and forms ---------- */

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function query(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function to_int($value): int
{
    return (is_string($value) || is_int($value)) && ctype_digit((string) $value) ? (int) $value : 0;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        render_error_page(419, 'Page expired', 'Your session timed out or the form was opened in another tab. Go back, refresh the page and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/* ---------- Validation ---------- */

function valid_email(string $email): bool
{
    return strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function valid_phone(string $phone): bool
{
    return (bool) preg_match('/^\+?[0-9 ()-]{7,20}$/', $phone);
}

function valid_matric(string $matric): bool
{
    return (bool) preg_match('/^[A-Za-z0-9\/-]{3,30}$/', $matric);
}

/** Returns an error message, or null when the password is acceptable. */
function password_problem(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Use at least 8 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Include at least one letter and one number.';
    }
    if (strlen($password) > 72) {
        return 'Use 72 characters or fewer.';
    }
    return null;
}

/* ---------- Pagination ---------- */

function paginate(int $total, int $perPage = 20): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($pages, max(1, to_int(query('page', '1')) ?: 1));
    return [
        'page' => $page,
        'pages' => $pages,
        'per_page' => $perPage,
        'offset' => ($page - 1) * $perPage,
        'total' => $total,
    ];
}

/** Current query string with some keys replaced. */
function query_with(array $changes): string
{
    $params = array_merge($_GET, $changes);
    $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
    return $params ? '?' . http_build_query($params) : '?';
}
