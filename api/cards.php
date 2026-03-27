<?php
/**
 * 卡片 API
 */

// 1. 只需要引入 config.php 就好，裡面已經有 session 和資料庫設定
require_once __DIR__ . '/config.php';

// 2. 絕對不要引入 auth.php！我們直接在這裡檢查登入狀態
if (!isset($_SESSION['user_id'])) {
    errorResponse('登入已過期，請重新登入');
    exit;
}
$userId = $_SESSION['user_id'];

// 3. 獲取動作
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    ensureCardsSchema(getDB());
} catch (Exception $e) {
    errorResponse('資料庫結構初始化失敗: ' . $e->getMessage());
}

// 4. 路由
switch ($action) {
    case 'list':
        handleList($userId);
        break;
        
    case 'save':
        handleSave($userId);
        break;
        
    case 'delete':
        handleDelete($userId);
        break;
        
    case 'move':
        handleMove($userId);
        break;
        
    default:
        // 為了區別，這裡改成「無效的卡片動作」
        errorResponse('無效的卡片動作');
}

/**
 * 確保 cards 資料表與必要欄位存在
 * - 解決部署時「API 200 但資料未寫入」的情況（常見原因是欄位遺失）
 */
function ensureCardsSchema($db) {
    static $checked = false;
    if ($checked) {
        return;
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS cards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL DEFAULT 1,
            col VARCHAR(20) NOT NULL DEFAULT 'lib',
            title VARCHAR(255) NOT NULL,
            project VARCHAR(50) DEFAULT NULL,
            priority VARCHAR(32) DEFAULT NULL,
            source_link TEXT DEFAULT NULL,
            summary TEXT DEFAULT NULL,
            next_step TEXT DEFAULT NULL,
            body TEXT DEFAULT NULL,
            checklist JSON DEFAULT NULL,
            bgcolor VARCHAR(20) DEFAULT NULL,
            textcolor VARCHAR(20) DEFAULT NULL,
            is_private TINYINT(1) NOT NULL DEFAULT 0,
            created_by INT DEFAULT NULL,
            completed_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_col (user_id, col),
            INDEX idx_project (project),
            INDEX idx_priority (priority),
            INDEX idx_completed_at (completed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $columnResult = $db->query("SHOW COLUMNS FROM cards")->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columnResult, 'Field');

    $requiredColumns = [
        'project' => "ALTER TABLE cards ADD COLUMN project VARCHAR(50) DEFAULT NULL AFTER title",
        'priority' => "ALTER TABLE cards ADD COLUMN priority VARCHAR(32) DEFAULT NULL AFTER project",
        'source_link' => "ALTER TABLE cards ADD COLUMN source_link TEXT DEFAULT NULL AFTER priority",
        'summary' => "ALTER TABLE cards ADD COLUMN summary TEXT DEFAULT NULL AFTER source_link",
        'next_step' => "ALTER TABLE cards ADD COLUMN next_step TEXT DEFAULT NULL AFTER summary",
        'body' => "ALTER TABLE cards ADD COLUMN body TEXT DEFAULT
