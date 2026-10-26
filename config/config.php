<?php
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 3600);

session_start();

require_once __DIR__ . '/database.php';

define('BASE_URL', '/Drug-Checklist-System/');
define('ROOT_PATH', dirname(__DIR__) . '/');

date_default_timezone_set('Asia/Manila');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', ROOT_PATH . 'logs/error.log');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function getCsrfToken() {
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return hash_equals($_SESSION['csrf_token'], $token);
}

function regenerateCsrfToken() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
