<?php
// 診斷工具 - 使用正確的 session 配置
require_once 'api/config.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>系統診斷 v2</title></head><body>";
echo "<h1>系統診斷工具 v2（使用正確的 session）</h1>";

// 1. PHP 版本
echo "<h2>1. PHP 版本</h2>";
echo "<p>PHP 版本: " . phpversion() . "</p>";

// 2. Session 狀態
echo "<h2>2. Session 狀態（使用 config.php）</h2>";
if (isset($_SESSION['user_id'])) {
    echo "<p style='color: green; font-weight: bold;'>✅ 已登入</p>";
    echo "<p>User ID: " . $_SESSION['user_id'] . "</p>";
    echo "<p>Username: " . ($_SESSION['username'] ?? 'N/A') . "</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ 未登入</p>";
    echo "<p>請先在看板登入，然後重新訪問此頁面</p>";
}

// 3. 資料庫連線
echo "<h2>3. 資料庫連線</h2>";
try {
    // 使用 config.php 中的 $pdo
    echo "<p style='color: green;'>✅ 資料庫連線成功（使用 config.php 的 PDO）</p>";
    
    // 檢查 focus_logs 表
    $stmt = $pdo->query("SHOW TABLES LIKE 'focus_logs'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✅ focus_logs 表存在</p>";
        
        // 檢查記錄數
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM focus_logs");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>記錄數: <strong>" . $result['count'] . "</strong></p>";
        
        // 如果有登入，顯示該用戶的記錄數
        if (isset($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM focus_logs WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "<p>您的記錄數: <strong>" . $result['count'] . "</strong></p>";
        }
    } else {
        echo "<p style='color: red;'>❌ focus_logs 表不存在</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ 資料庫錯誤: " . $e->getMessage() . "</p>";
}

// 4. 檔案檢查
echo "<h2>4. 檔案檢查</h2>";
$files = [
    'api/config.php',
    'api/focus-logs.php',
    'stats.php',
    'index.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✅ $file 存在</p>";
    } else {
        echo "<p style='color: red;'>❌ $file 不存在</p>";
    }
}

// 5. 測試導出（如果已登入）
echo "<h2>5. 測試導出功能</h2>";
if (isset($_SESSION['user_id'])) {
    echo "<p><a href='api/focus-logs.php?action=export&start_date=2026-03-01&end_date=2026-03-22' style='padding: 10px 20px; background: #1D9E75; color: white; text-decoration: none; border-radius: 8px; display: inline-block;'>📥 測試導出 CSV</a></p>";
    echo "<p style='color: #666; font-size: 14px;'>點擊上方按鈕測試導出功能</p>";
} else {
    echo "<p style='color: orange;'>⚠️ 請先登入看板，然後刷新此頁面</p>";
    echo "<p><a href='index.php'>前往看板登入</a></p>";
}

// 6. 測試 API
echo "<h2>6. 測試 API 連線</h2>";
if (isset($_SESSION['user_id'])) {
    echo "<p><a href='api/focus-logs.php?action=stats' target='_blank'>測試 stats API</a></p>";
    echo "<p><a href='api/focus-logs.php?action=recent&limit=5' target='_blank'>測試 recent API</a></p>";
}

echo "<hr>";
echo "<p><a href='index.php'>返回看板</a> | <a href='stats.php'>查看統計</a></p>";

echo "</body></html>";
