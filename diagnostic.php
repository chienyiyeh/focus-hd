<?php
// 診斷工具 - 檢查系統狀態
session_start();

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>系統診斷</title></head><body>";
echo "<h1>系統診斷工具</h1>";

// 1. PHP 版本
echo "<h2>1. PHP 版本</h2>";
echo "<p>PHP 版本: " . phpversion() . "</p>";

// 2. Session 狀態
echo "<h2>2. Session 狀態</h2>";
if (isset($_SESSION['user_id'])) {
    echo "<p>✅ 已登入</p>";
    echo "<p>User ID: " . $_SESSION['user_id'] . "</p>";
    echo "<p>Username: " . ($_SESSION['username'] ?? 'N/A') . "</p>";
} else {
    echo "<p>❌ 未登入</p>";
}

// 3. 資料庫連線
echo "<h2>3. 資料庫連線</h2>";
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=zeyjsvrczr;charset=utf8mb4',
        'zeyjsvrczr',
        'nrPBsleknr'
    );
    echo "<p>✅ 資料庫連線成功</p>";
    
    // 檢查 focus_logs 表
    $stmt = $pdo->query("SHOW TABLES LIKE 'focus_logs'");
    if ($stmt->rowCount() > 0) {
        echo "<p>✅ focus_logs 表存在</p>";
        
        // 檢查記錄數
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM focus_logs");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>記錄數: " . $result['count'] . "</p>";
    } else {
        echo "<p>❌ focus_logs 表不存在</p>";
    }
    
} catch (PDOException $e) {
    echo "<p>❌ 資料庫連線失敗: " . $e->getMessage() . "</p>";
}

// 4. 檔案權限
echo "<h2>4. 檔案檢查</h2>";
$files = [
    'api/config.php',
    'api/focus-logs.php',
    'stats.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<p>✅ $file 存在</p>";
    } else {
        echo "<p>❌ $file 不存在</p>";
    }
}

// 5. 錯誤報告
echo "<h2>5. PHP 錯誤設定</h2>";
echo "<p>display_errors: " . ini_get('display_errors') . "</p>";
echo "<p>error_reporting: " . error_reporting() . "</p>";

// 6. 測試導出
echo "<h2>6. 測試導出功能</h2>";
if (isset($_SESSION['user_id'])) {
    echo "<p><a href='api/focus-logs-test.php?action=export' target='_blank'>測試導出 CSV</a></p>";
} else {
    echo "<p>請先登入</p>";
}

echo "</body></html>";
