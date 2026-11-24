<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

if (!defined('APP_NAME')) {
    define('APP_NAME', 'B-TRACK');
}

if (!defined('APP_ENV')) {
    define('APP_ENV', 'development');
}

date_default_timezone_set('Asia/Manila');

if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

if (session_status() === PHP_SESSION_NONE) {
    $isSecureRequest = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_name('btrack_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isSecureRequest,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!isset($_SESSION['_session_started_at'])) {
    session_regenerate_id(true);
    $_SESSION['_session_started_at'] = time();
} elseif (time() - (int) $_SESSION['_session_started_at'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['_session_started_at'] = time();
}

require_once BASE_PATH . '/includes/functions.php';

$appConfig = BASE_PATH . '/config/app.php';
$databaseConfig = BASE_PATH . '/config/database.php';

if (is_file($appConfig)) {
    require_once $appConfig;
}

if (is_file($databaseConfig)) {
    require_once $databaseConfig;
}

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
