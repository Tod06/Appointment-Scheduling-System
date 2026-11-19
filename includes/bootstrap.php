<?php
declare(strict_types=1);

/*
 * Loaded at the top of every page. Sets up configuration, the database
 * connection, the session and all shared helpers.
 */

define('APP', true);
define('APP_ROOT', dirname(__DIR__));

$configFile = APP_ROOT . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Setup needed: copy config.sample.php to config.php and enter your database details.");
}

$GLOBALS['config'] = require $configFile;

date_default_timezone_set($GLOBALS['config']['timezone'] ?? 'Africa/Lagos');

if (!empty($GLOBALS['config']['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

require __DIR__ . '/helpers.php';
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/icons.php';
require __DIR__ . '/activity.php';
require __DIR__ . '/appointments.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/components.php';

start_session();
