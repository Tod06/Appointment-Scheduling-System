<?php
defined('APP') || exit;

function render_head(string $title, string $bodyClass = ''): void
{
    $fullTitle = $title === '' ? config('app_name', 'UHS Appointments') : $title . ' | ' . config('app_name', 'UHS Appointments');
    $favicon = "data:image/svg+xml," . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="9" fill="#0f5c4c"/><path d="M9 11.5h14" stroke="#fff" stroke-width="1.6" stroke-linecap="round" opacity=".55"/><path d="M16 14.5v9M11.5 19h9" stroke="#fff" stroke-width="2.6" stroke-linecap="round"/></svg>');
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($fullTitle) ?></title>
  <meta name="description" content="Book, track and manage appointments at the <?= e(config('org_name')) ?>.">
  <meta name="theme-color" content="#f5f4f0" media="(prefers-color-scheme: light)">
  <meta name="theme-color" content="#0e1012" media="(prefers-color-scheme: dark)">
  <link rel="icon" href="<?= e($favicon) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&family=Instrument+Serif:ital@0;1&display=swap">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="<?= e($bodyClass) ?>">
<a class="skip-link" href="#main">Skip to content</a>
<?php
}

function render_foot(): void
{
    echo "</body>\n</html>\n";
}

function render_flashes(array $extraErrors = []): string
{
    $items = take_flashes();
    if ($extraErrors) {
        $items[] = ['type' => 'error', 'message' => $extraErrors];
    }
    if (!$items) {
        return '';
    }
    $icons = ['success' => 'check-circle', 'error' => 'circle-alert', 'info' => 'info', 'warning' => 'circle-alert'];
    $html = '<div class="alerts" role="status">';
    foreach ($items as $item) {
        $type = $item['type'];
        $message = $item['message'];
        $html .= '<div class="alert alert-' . e($type) . '" data-alert>' . icon($icons[$type] ?? 'info', 18) . '<div class="alert-body">';
        if (is_array($message)) {
            $html .= count($message) === 1 ? e(reset($message)) : 'Please fix the following:<ul><li>' . implode('</li><li>', array_map('e', $message)) . '</li></ul>';
        } else {
            $html .= e($message);
        }
        $html .= '</div><button type="button" class="alert-close" aria-label="Dismiss" data-dismiss>' . icon('x', 16) . '</button></div>';
    }
    return $html . '</div>';
}

/** Full-page error that ends the request. */
function render_error_page(int $code, string $title, string $message): void
{
    if (!headers_sent()) {
        http_response_code($code);
    }
    $user = current_user();
    render_head($title);
    ?>
<main id="main" class="error-page">
  <div>
    <div class="error-code"><?= $code ?></div>
    <h1 style="margin-top:12px;font-size:20px"><?= e($title) ?></h1>
    <p class="muted" style="margin:8px auto 0;max-width:420px"><?= e($message) ?></p>
    <div class="row" style="justify-content:center;margin-top:24px">
      <?php if ($user): ?>
        <a class="btn btn-primary" href="<?= e(url(home_for($user['role']))) ?>"><?= icon('layout-dashboard', 16) ?> Go to your dashboard</a>
      <?php else: ?>
        <a class="btn btn-primary" href="<?= e(url('index.php')) ?>"><?= icon('arrow-left', 16) ?> Back to home</a>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php
    render_foot();
    exit;
}

function nav_items(string $role): array
{
    return match ($role) {
        'patient' => [
            ['Overview', 'patient/dashboard.php', 'layout-dashboard'],
            ['Book appointment', 'patient/book.php', 'calendar-plus'],
            ['My appointments', 'patient/appointments.php', 'calendar'],
            ['Notifications', 'notifications.php', 'bell'],
            ['Account', 'account.php', 'user'],
        ],
        'doctor' => [
            ['Today', 'doctor/dashboard.php', 'layout-dashboard'],
            ['Week calendar', 'doctor/calendar.php', 'calendar'],
            ['Appointments', 'doctor/appointments.php', 'list'],
            ['Availability', 'doctor/availability.php', 'clock'],
            ['Notifications', 'notifications.php', 'bell'],
            ['Account', 'account.php', 'user'],
        ],
        'admin' => [
            ['Overview', 'admin/dashboard.php', 'layout-dashboard'],
            ['Calendar', 'admin/calendar.php', 'calendar'],
            ['Appointments', 'admin/appointments.php', 'list'],
            '-Records',
            ['Patients', 'admin/patients.php', 'users'],
            ['Doctors', 'admin/doctors.php', 'stethoscope'],
            '-Oversight',
            ['Reports', 'admin/reports.php', 'bar-chart'],
            ['Audit log', 'admin/audit.php', 'history'],
            ['Account', 'account.php', 'user'],
        ],
        default => [],
    };
}

/**
 * Opens the signed-in app layout.
 * $opts: active (nav path), subtitle, actions (HTML), back ([url, label]), errors (array)
 */
function app_start(array $user, string $title, array $opts = []): void
{
    $active = $opts['active'] ?? '';
    $unread = unread_notifications($user);
    render_head($title);
    ?>
<div class="app" data-app>
  <aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <a class="sidebar-brand" href="<?= e(url(home_for($user['role']))) ?>">
      <?= brand_mark(30) ?>
      <span>
        <span class="brand-name">UHS Appointments</span><br>
        <span class="brand-sub">University of Ibadan</span>
      </span>
    </a>
    <nav class="nav">
      <?php foreach (nav_items($user['role']) as $item): ?>
        <?php if (is_string($item)): ?>
          <div class="nav-section"><?= e(substr($item, 1)) ?></div>
          <?php continue; endif; ?>
        <?php [$label, $path, $ic] = $item; $isActive = $path === $active; ?>
        <a class="nav-link<?= $isActive ? ' is-active' : '' ?>" href="<?= e(url($path)) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
          <?= icon($ic, 17) ?><span><?= e($label) ?></span>
          <?php if ($path === 'notifications.php' && $unread > 0): ?><span class="nav-count"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-user">
      <div class="user-card">
        <span class="avatar"><?= e(initials($user['name'])) ?></span>
        <div class="main">
          <div class="user-card-name"><?= e($user['name']) ?></div>
          <div class="user-card-role"><?= e(role_label($user['role'])) ?></div>
        </div>
        <form method="post" action="<?= e(url('logout.php')) ?>">
          <?= csrf_field() ?>
          <button class="btn btn-ghost btn-icon btn-sm" type="submit" title="Sign out" aria-label="Sign out"><?= icon('log-out', 16) ?></button>
        </form>
      </div>
    </div>
  </aside>
  <div class="scrim" data-nav-close></div>

  <div class="main">
    <header class="topbar">
      <button class="btn btn-ghost btn-icon menu-toggle" type="button" aria-label="Open menu" aria-controls="sidebar" data-nav-toggle><?= icon('menu', 18) ?></button>
      <div class="topbar-title">
        <span><?= e(role_label($user['role'])) ?></span>
        <?= icon('chevron-right', 14) ?>
        <strong><?= e($title) ?></strong>
      </div>
      <div class="topbar-actions">
        <?php if ($user['role'] === 'patient'): ?>
          <a class="btn btn-primary btn-sm hide-xs" href="<?= e(url('patient/book.php')) ?>"><?= icon('plus', 15) ?> New appointment</a>
        <?php endif; ?>
        <?php if ($user['role'] !== 'admin'): ?>
          <a class="btn btn-ghost btn-icon bell" href="<?= e(url('notifications.php')) ?>" aria-label="Notifications<?= $unread ? ", $unread unread" : '' ?>">
            <?= icon('bell', 18) ?><?php if ($unread): ?><span class="bell-dot"></span><?php endif; ?>
          </a>
        <?php endif; ?>
      </div>
    </header>

    <main class="content" id="main">
      <?php if (!empty($opts['back'])): ?>
        <a class="back-link" href="<?= e($opts['back'][0]) ?>"><?= icon('arrow-left', 15) ?> <?= e($opts['back'][1]) ?></a>
      <?php endif; ?>
      <div class="page-head">
        <div>
          <h1 class="page-title"><?= e($opts['heading'] ?? $title) ?></h1>
          <?php if (!empty($opts['subtitle'])): ?><p class="page-sub"><?= e($opts['subtitle']) ?></p><?php endif; ?>
        </div>
        <?php if (!empty($opts['actions'])): ?><div class="page-actions"><?= $opts['actions'] ?></div><?php endif; ?>
      </div>
      <?= render_flashes($opts['errors'] ?? []) ?>
<?php
}

function app_end(): void
{
    echo "    </main>\n  </div>\n</div>\n";
    render_foot();
}

/** Header used on the public landing page. */
function site_header(?array $user): void
{
    ?>
<header class="site-header">
  <div class="site-header-inner">
    <a class="brand" href="<?= e(url('index.php')) ?>">
      <?= brand_mark(30) ?>
      <span>
        <span class="brand-name">UHS Appointments</span><br>
        <span class="brand-sub">University of Ibadan</span>
      </span>
    </a>
    <nav class="site-nav" aria-label="Site">
      <a class="link hide-sm" href="#how">How it works</a>
      <a class="link hide-sm" href="#doctors">Doctors</a>
      <?php if ($user): ?>
        <a class="btn btn-primary btn-sm" href="<?= e(url(home_for($user['role']))) ?>">Open dashboard <?= icon('arrow-right', 15) ?></a>
      <?php else: ?>
        <a class="link" href="<?= e(url('login.php')) ?>">Sign in</a>
        <a class="btn btn-dark btn-sm" href="<?= e(url('register.php')) ?>">Create account</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<?php
}
