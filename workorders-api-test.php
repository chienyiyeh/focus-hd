<?php
/**
 * 工單管理 API - 測試版
 */

session_start();

header('Content-Type: application/json; charset=utf-8');

// 步驟 1: 測試是否登入
if (!isset($_SESSION['username'])) {
    echo json_encode([
        'success' => false, 
        'error' => '未登入',
        'debug' => 'Please login first'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$username = $_SESSION['username'];

// 步驟 2: 測試資料庫連線
try {
    // 使用你的資料庫資訊
    $db_host = 'localhost';
    $db_name = 'zeyjsvrczr';
    $db_user = 'zeyjsvrczr';
    $db_pass = 'nrPBsleknr';
    
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo json_encode([
        'success' => true,
        'message' => '資料庫連線成功',
        'username' => $username,
        'database' => $db_name
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => '資料庫連線失敗',
        'debug' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}