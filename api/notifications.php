<?php
// ============================================
// 通知 API
// ============================================

// 使用 config.php（就像 cards.php 一样）
require_once 'config.php';

// 检查登入
if (!isset($_SESSION['user_id'])) {
    errorResponse('登入已過期，請重新登入');
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

// ============================================
// 獲取未讀通知
// ============================================
function getUnreadNotifications($userId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT id, actor_username, action, card_title, message, created_at 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'count' => count($notifications)
    ]);
}

// ============================================
// 標記為已讀
// ============================================
function markAsRead($userId) {
    $input = json_decode(file_get_contents('php://input'), true);
    $notificationId = $input['id'] ?? null;
    
    if (!$notificationId) {
        echo json_encode(['success' => false, 'message' => '缺少通知 ID']);
        return;
    }
    
    $db = getDB();
    $stmt = $db->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$notificationId, $userId]);
    
    echo json_encode(['success' => true]);
}

// ============================================
// 全部標記為已讀
// ============================================
function markAllAsRead($userId) {
    $db = getDB();
    $stmt = $db->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$userId]);
    
    echo json_encode(['success' => true]);
}

// ============================================
// 獲取未讀數量（輕量級）
// ============================================
function getUnreadCount($userId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'count' => (int)$result['count']
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
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '無效的操作']);
}
