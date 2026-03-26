<?php
/**
 * 臨時 Session 診斷頁
 * 使用方式：直接在瀏覽器打開 /session_check.php
 */

require_once __DIR__ . '/api/config.php';

header('Content-Type: text/plain; charset=utf-8');

$savePath = session_save_path();
if ($savePath === '' || $savePath === false) {
    $savePath = ini_get('session.save_path');
}

$firstPath = $savePath;
if (is_string($savePath) && strpos($savePath, ';') !== false) {
    $parts = explode(';', $savePath);
    $firstPath = trim(end($parts));
}

$writable = null;
$writeTestFile = null;
$writeTestError = null;
if (is_string($firstPath) && $firstPath !== '') {
    $writable = is_writable($firstPath);
    $writeTestFile = rtrim($firstPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'session_check_write_test_' . getmypid() . '.tmp';
    $writeResult = @file_put_contents($writeTestFile, 'ok:' . date('c'));
    if ($writeResult === false) {
        $writeTestError = error_get_last()['message'] ?? 'unknown error';
    } else {
        @unlink($writeTestFile);
    }
}

$before = $_SESSION['session_check_counter'] ?? null;
$_SESSION['session_check_counter'] = is_numeric($before) ? ((int)$before + 1) : 1;
$_SESSION['session_check_last_seen'] = date('c');
session_write_close();
session_start();
$after = $_SESSION['session_check_counter'] ?? null;

$cookieParams = session_get_cookie_params();

$report = [
    'time_utc' => gmdate('c'),
    'php_version' => PHP_VERSION,
    'session_status' => session_status(),
    'session_id' => session_id(),
    'session_name' => session_name(),
    'session_save_path' => $savePath,
    'session_save_path_first' => $firstPath,
    'session_save_path_exists' => is_string($firstPath) ? is_dir($firstPath) : null,
    'session_save_path_writable' => $writable,
    'session_write_test_error' => $writeTestError,
    'cookie_params' => $cookieParams,
    'incoming_cookie_session' => $_COOKIE[session_name()] ?? null,
    'session_read_write_before' => $before,
    'session_read_write_after' => $after,
    'session_data_preview' => [
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'login_time' => $_SESSION['login_time'] ?? null,
        'session_check_counter' => $_SESSION['session_check_counter'] ?? null,
        'session_check_last_seen' => $_SESSION['session_check_last_seen'] ?? null,
    ],
    'https' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'on' : 'off',
    'host' => $_SERVER['HTTP_HOST'] ?? null,
    'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
];

echo "=== Session Check Report ===\n";
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
echo "\n";
