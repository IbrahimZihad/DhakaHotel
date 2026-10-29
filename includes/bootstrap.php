<?php
/**
 * Application bootstrap: environment, configuration, session, headers and
 * shared includes. Safe to include from CLI scripts (no session/headers).
 */

declare(strict_types=1);

if (!defined('DH_ROOT')) {
    define('DH_ROOT', dirname(__DIR__));
}

require DH_ROOT . '/includes/env.php';
load_env(DH_ROOT . '/.env');

require DH_ROOT . '/includes/functions.php';
require DH_ROOT . '/includes/database_helpers.php';
require DH_ROOT . '/includes/validation.php';
require DH_ROOT . '/includes/csrf.php';
require DH_ROOT . '/includes/language.php';
require DH_ROOT . '/includes/auth.php';
require DH_ROOT . '/includes/billing.php';
require DH_ROOT . '/includes/cart.php';
require DH_ROOT . '/includes/orders.php';
require DH_ROOT . '/includes/mailer.php';

if (!defined('DH_ADMIN_CONTEXT')) {
    define('DH_ADMIN_CONTEXT', str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/'));
}

date_default_timezone_set((string) config('app.timezone', 'Asia/Dhaka'));
mb_internal_encoding('UTF-8');

$isCli = PHP_SAPI === 'cli';
$debug = (bool) config('app.debug', false);

if ($debug) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    set_exception_handler(static function (Throwable $exception): void {
        storage_log('Unhandled exception: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="bn"><meta charset="utf-8"><title>Error</title>'
            . '<div style="font-family:system-ui;max-width:520px;margin:15vh auto;padding:24px;border:1px solid #eee;border-radius:16px">'
            . '<h1 style="font-size:20px;margin:0 0 8px">Something went wrong / কিছু একটা ভুল হয়েছে</h1>'
            . '<p style="color:#666">Please try again in a moment. / একটু পরে আবার চেষ্টা করুন।</p></div></html>';
    });
}

if ($isCli) {
    return;
}

/* -------------------------------------------------------------------------
 * Session
 * ---------------------------------------------------------------------- */
$sessionSecure = config('app.session.secure', 'auto');
$sessionSecure = $sessionSecure === 'auto' ? is_https() : filter_var($sessionSecure, FILTER_VALIDATE_BOOL);

session_name((string) config('app.session.name', 'dhakahotelsessid'));
session_set_cookie_params([
    'lifetime' => (int) config('app.session.lifetime', 7200),
    'path' => config('app.base_path', '') !== '' ? config('app.base_path') : '/',
    'secure' => $sessionSecure,
    'httponly' => true,
    'samesite' => (string) config('app.session.samesite', 'Lax'),
]);
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', (string) (int) config('app.session.lifetime', 7200));
session_start();

/* -------------------------------------------------------------------------
 * Security headers
 * ---------------------------------------------------------------------- */
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header(
    "Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; "
    . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
    . "font-src 'self' https://fonts.gstatic.com data:; script-src 'self'; "
    . "connect-src 'self' https://fonts.gstatic.com; frame-ancestors 'self'; "
    . "base-uri 'self'; form-action 'self'; object-src 'none'"
);

/* -------------------------------------------------------------------------
 * Language switching (?lang=bn|en) - preserves the current page and query
 * ---------------------------------------------------------------------- */
if (isset($_GET['lang'])) {
    $requested = strtolower((string) $_GET['lang']);

    if (in_array($requested, available_languages(), true)) {
        set_language($requested);
    }

    $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';
    $basePath = (string) config('app.base_path', '');
    if ($basePath !== '' && str_starts_with($path, $basePath)) {
        $path = substr($path, strlen($basePath));
    }

    parse_str((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY), $query);
    unset($query['lang']);

    redirect(ltrim($path, '/') . ($query !== [] ? '?' . http_build_query($query) : ''));
}
