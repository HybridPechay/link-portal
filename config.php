<?php
/**
 * Site configuration.
 *
 * IMPORTANT: fill in your real database credentials below, then make sure
 * this file is NOT readable from the browser (it already lives outside any
 * "public html only" concern here because the whole app is PHP, but on
 * shared hosting double-check your document root points at this folder and
 * that .htaccess in this folder is respected — see README.md).
 */

// ---- Database ---------------------------------------------------------
// Reads from environment variables first (set these in docker-compose.yml /
// .env), falling back to local defaults so the app also runs outside Docker.
define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_NAME', getenv('DB_NAME') ?: 'link_portal');
define('DB_USER', getenv('DB_USER') ?: 'link_portal_user');
define('DB_PASS', getenv('DB_PASS') ?: 'CHANGE_ME');

// ---- App ----------------------------------------------------------------
define('APP_NAME', 'Accreditation Portal');
define('SESSION_NAME', 'lp_session');
define('MAX_LOGIN_ATTEMPTS', 5);      // failed attempts allowed
define('LOGIN_LOCKOUT_MINUTES', 15);  // lockout window after max attempts
define('BRAND_COLOR', '#125D32');

// ---- Error handling -------------------------------------------------
// Never display raw errors to visitors in production.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// ---- Session hardening ------------------------------------------------
// Must run before session_start() is called anywhere.
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['SERVER_PORT'] ?? '') == 443)
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_name(SESSION_NAME);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax', // Lax so following a login link from email still works; forms use CSRF tokens regardless
]);

// Every page that includes config.php gets a session ready to use. This is
// centralized here (rather than a session_start() in every entry file) so
// the hardening settings above are always applied before the session opens.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
