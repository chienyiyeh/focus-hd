<?php
// ============================================
// 通知 API
// ============================================

require_once __DIR__ . '/config.php';

// 檢查登入
if (!isset($_SESSION['user_id'])) {
    errorResponse('登入已過期，請重新登入');
}

$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? '';

// 先確保資料表存在
try {
    ensureNotificationsSchema(getDB());
} catch (Exception $e) {
    errorResponse('通知資料表初始化失敗: ' . (APP_DEBUG ? $e->getMessage() : '請檢查資料庫'));
}

// ============================================
// 確保 notifications 資料表存在
// ============================================
function ensureNotificationsSchema($db) {
    static $checked = false;
    if ($checked) {
        return;
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            actor_username VARCHAR(100) NOT NULL DEFAULT '',
            action VARCHAR(50) DEFAULT NULL,
            card_title VARCHAR(255) DEFAULT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_read_created (user_id, is_read, created_at),
            INDEX idx_user_created (user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $checked = true;
}

// ============================================
// 獲取未讀通知
// ============================================
function getUnreadNotifications($userId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, actor_username, action, card_title, message, is_read, created_at
        FROM notifications
        WHERE user_id = ? AND is_read = 0
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse([
        'success' => true,
        'notifications' => $notifications,
        'count' => count($notifications)
    ]);
}

// ============================================
// 標記單筆為已讀
// ============================================
function markAsRead($userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('僅支援 POST 請求');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $notificationId = isset($input['id']) ? (int)$input['id'] : 0;

    if ($notificationId <= 0) {
        errorResponse('缺少通知 ID');
    }

    $db = getDB();
    $stmt = $db->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$notificationId, $userId]);

    jsonResponse(['success' => true]);
}

// ============================================
// 全部標記為已讀
// ============================================
function markAllAsRead($userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('僅支援 POST 請求');
    }

    $db = getDB();
    $stmt = $db->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$userId]);

    jsonResponse(['success' => true]);
}

// ============================================
// 獲取未讀數量
// ============================================
function getUnreadCount($userId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT COUNT(*) AS count
        FROM notifications
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    jsonResponse([
        'success' => true,
        'count' => (int)($result['count'] ?? 0)
    ]);
}

// ============================================
// 路由
// ============================================
switch ($action) {
    case 'list':
        getUnreadNotifications($userId);
        break;

    case 'mark_read':
        markAsRead($userId);
        break;

    case 'mark_all_read':
        markAllAsRead($userId);
        break;

    case 'count':
        getUnreadCount($userId);
        break;

    default:
        errorResponse('無效的操作');
}
