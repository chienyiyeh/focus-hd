<?php
/**
 * 卡片 API (終極相容版)
 */

// 1. 引入設定
require_once 'config.php';

// 2. 檢查登入狀態
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
        errorResponse('無效的卡片動作');
}

// ============================================
// 自動擴建保險箱 (確保新功能有格子存)
// ============================================
function ensureCardsSchema($db) {
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
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $columns = array_column($db->query("SHOW COLUMNS FROM cards")->fetchAll(PDO::FETCH_ASSOC), 'Field');
    
    // 如果缺少新欄位，自動補上
    if (!in_array('priority', $columns)) $db->exec("ALTER TABLE cards ADD COLUMN priority VARCHAR(32) DEFAULT NULL");
    if (!in_array('checklist', $columns)) $db->exec("ALTER TABLE cards ADD COLUMN checklist JSON DEFAULT NULL");
    if (!in_array('is_private', $columns)) $db->exec("ALTER TABLE cards ADD COLUMN is_private TINYINT(1) NOT NULL DEFAULT 0");
    if (!in_array('created_by', $columns)) $db->exec("ALTER TABLE cards ADD COLUMN created_by INT DEFAULT NULL");
}

// ============================================
// 取得所有卡片
// ============================================
function handleList($userId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            SELECT id, col, title, project, priority, source_link, summary, next_step, body, checklist, bgcolor, textcolor, is_private, created_by, completed_at, created_at, updated_at
            FROM cards 
            WHERE user_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$userId]);
        $cards = $stmt->fetchAll();
        
        $result = ['lib' => [], 'week' => [], 'focus' => [], 'done' => []];
        
        foreach ($cards as $card) {
            $col = $card['col'];
            if (isset($result[$col])) {
                $result[$col][] = [
                    'id' => (int)$card['id'],
                    'title' => $card['title'],
                    'project' => $card['project'],
                    'priority' => $card['priority'],
                    'sourceLink' => $card['source_link'],
                    'summary' => $card['summary'],
                    'nextStep' => $card['next_step'],
                    'body' => $card['body'],
                    'checklist' => $card['checklist'] ? json_decode($card['checklist'], true) : null,
                    'bgcolor' => $card['bgcolor'],
                    'textcolor' => $card['textcolor'],
                    'isPrivate' => (bool)$card['is_private'],
                    'createdBy' => $card['created_by'],
                    'completedAt' => $card['completed_at'],
                    'createdAt' => $card['created_at'],
                    'updatedAt' => $card['updated_at']
                ];
            }
        }
        jsonResponse($result);
    } catch (Exception $e) {
        errorResponse('取得卡片失敗');
    }
}

// ============================================
// 清理 HTML 內容
// ============================================
function cleanHTML($html) {
    if (empty($html)) return null;
    $allowed_tags = '<p><br><strong><em><u><s><ol><ul><li><h1><h2><h3><span><a><img>';
    $cleaned = strip_tags($html, $allowed_tags);
    $cleaned = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $cleaned);
    return $cleaned;
}

// ============================================
// 新增/更新卡片
// ============================================
function handleSave($userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') errorResponse('僅支援 POST 請求');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = isset($input['id']) ? (int)$input['id'] : null;
    $col = cleanInput($input['col'] ?? 'lib');
    $title = cleanInput($input['title'] ?? '');
    $project = cleanInput($input['project'] ?? null);
    $priority = cleanInput($input['priority'] ?? null);
    $sourceLink = cleanInput($input['sourceLink'] ?? null);
    $summary = cleanInput($input['summary'] ?? null);
    $nextStep = cleanInput($input['nextStep'] ?? null);
    $body = cleanHTML($input['body'] ?? null);
    $bgcolor = cleanInput($input['bgcolor'] ?? null);
    $textcolor = cleanInput($input['textcolor'] ?? null);
    $completedAt = $input['completedAt'] ?? null;
    
    // 處理新功能欄位
    $isPrivate = isset($input['isPrivate']) ? (int)$input['isPrivate'] : 0;
    $checklist = !empty($input['checklist']) ? json_encode($input['checklist'], JSON_UNESCAPED_UNICODE) : null;
    
    if (empty($title)) errorResponse('標題不能為空');
    if (!in_array($col, ['lib', 'week', 'focus', 'done'])) errorResponse('無效的欄位');
    
    try {
        $db = getDB();
        if ($id) {
            $stmt = $db->prepare("
                UPDATE cards SET 
                    col = ?, title = ?, project = ?, priority = ?, source_link = ?, summary = ?, next_step = ?, body = ?, checklist = ?, bgcolor = ?, textcolor = ?, is_private = ?, completed_at = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$col, $title, $project, $priority, $sourceLink, $summary, $nextStep, $body, $checklist, $bgcolor, $textcolor, $isPrivate, $completedAt, $id, $userId]);
            successResponse(['id' => $id], '卡片更新成功');
        } else {
            $stmt = $db->prepare("
                INSERT INTO cards (user_id, col, title, project, priority, source_link, summary, next_step, body, checklist, bgcolor, textcolor, is_private, created_by, completed_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $col, $title, $project, $priority, $sourceLink, $summary, $nextStep, $body, $checklist, $bgcolor, $textcolor, $isPrivate, $userId, $completedAt]);
            successResponse(['id' => $db->lastInsertId()], '卡片新增成功');
        }
    } catch (Exception $e) {
        errorResponse('儲存卡片失敗: ' . $e->getMessage());
    }
}

// ============================================
// 刪除卡片
// ============================================
function handleDelete($userId) {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if (!$id) errorResponse('缺少卡片 ID');
    
    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM cards WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        if ($stmt->rowCount() > 0) successResponse([], '卡片刪除成功');
        else errorResponse('卡片不存在或無權限刪除');
    } catch (Exception $e) {
        errorResponse('刪除卡片失敗');
    }
}

// ============================================
// 移動卡片
// ============================================
function handleMove($userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') errorResponse('僅支援 POST 請求');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $newCol = cleanInput($input['col'] ?? '');
    
    if (!$id || !$newCol) errorResponse('缺少必要參數');
    if (!in_array($newCol, ['lib', 'week', 'focus', 'done'])) errorResponse('無效的欄位');
    
    try {
        $db = getDB();
        if ($newCol === 'done') {
            $stmt = $db->prepare("UPDATE cards SET col = ?, completed_at = NOW() WHERE id = ? AND user_id = ?");
        } else {
            $stmt = $db->prepare("UPDATE cards SET col = ?, completed_at = NULL WHERE id = ? AND user_id = ?");
        }
        $stmt->execute([$newCol, $id, $userId]);
        
        if ($stmt->rowCount() > 0) successResponse(['id' => $id, 'col' => $newCol], '卡片移動成功');
        else errorResponse('卡片不存在或無權限移動');
    } catch (Exception $e) {
        errorResponse('移動卡片失敗');
    }
}
