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

/*
 * Last line of defence for unexpected errors. Before setup has run the tables
 * do not exist yet, so send visitors to the installer instead of a blank 500.
 */
set_exception_handler(function (Throwable $ex): void {
    $missingTables = $ex instanceof PDOException && (string) $ex->getCode() === '42S02';
    if ($missingTables && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php' && !headers_sent()) {
        header('Location: ' . url('install.php'));
        exit;
    }
    error_log('UHS Appointments: ' . $ex);
    if (headers_sent()) {
        exit;
    }
    try {
        render_error_page(500, 'Something went wrong', config('debug')
            ? $ex->getMessage()
            : 'The page could not be loaded. Please try again in a moment. If it keeps happening, contact the Health Service.');
    } catch (Throwable $inner) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'The page could not be loaded. Please try again in a moment.';
    }
});

start_session();
