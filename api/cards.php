<?php
/**
 * 卡片 API
 */

// 1. 只需要引入 config.php 就好，裡面已經有 session 和資料庫設定
require_once 'config.php';

// 2. 絕對不要引入 auth.php！我們直接在這裡檢查登入狀態
if (!isset($_SESSION['user_id'])) {
    errorResponse('登入已過期，請重新登入');
    exit;
}
$userId = $_SESSION['user_id'];

// 3. 獲取動作
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

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

// ============================================
// 取得所有卡片
// ============================================
function handleList($userId) {
    try {
        $db = getDB();
        // ⭐ 家庭协作模式：显示共用卡片 + 自己的私人卡片
        $stmt = $db->prepare("
            SELECT id, col, title, project, source_link, summary, next_step, body, bgcolor, textcolor, 
                   completed_at, created_at, updated_at, created_by, created_by_username, is_private, checklist
            FROM cards 
            WHERE is_private = 0 OR user_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$userId]);
        $cards = $stmt->fetchAll();
        
        $result = [
            'lib' => [],
            'week' => [],
            'focus' => [],
            'done' => []
        ];
        
        foreach ($cards as $card) {
            $col = $card['col'];
            if (isset($result[$col])) {
                // 解析 checklist JSON
                $checklist = null;
                if (!empty($card['checklist'])) {
                    $decoded = json_decode($card['checklist'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $checklist = $decoded;
                    }
                }
                
                $result[$col][] = [
                    'id' => (int)$card['id'],
                    'title' => $card['title'],
                    'project' => $card['project'],
                    'sourceLink' => $card['source_link'],
                    'summary' => $card['summary'],
                    'nextStep' => $card['next_step'],
                    'body' => $card['body'],
                    'bgcolor' => $card['bgcolor'],
                    'textcolor' => $card['textcolor'],
                    'completedAt' => $card['completed_at'],
                    'createdAt' => $card['created_at'],
                    'updatedAt' => $card['updated_at'],
                    'createdBy' => (int)($card['created_by'] ?? 0),
                    'createdByUsername' => $card['created_by_username'] ?? 'unknown',
                    'isPrivate' => (bool)($card['is_private'] ?? 0),
                    'checklist' => $checklist
                ];
            }
        }
        
        jsonResponse($result);
        
    } catch (Exception $e) {
        errorResponse('取得卡片失敗');
    }
}

// ============================================
// 清理 HTML 內容（保留安全的標籤和樣式）
// ============================================
function cleanHTML($html) {
    if (empty($html)) {
        return null;
    }
    
    // Quill 編輯器產生的 HTML 是安全的，我們只需要基本清理
    // 保留 Quill 使用的標籤和屬性
    $allowed_tags = '<p><br><strong><em><u><s><ol><ul><li><h1><h2><h3><span><a><img>';
    
    // 使用 strip_tags 保留允許的標籤
    $cleaned = strip_tags($html, $allowed_tags);
    
    // 移除潛在的 JavaScript
    $cleaned = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $cleaned);
    $cleaned = preg_replace('/on\w+="[^"]*"/i', '', $cleaned);
    $cleaned = preg_replace('/on\w+=\'[^\']*\'/i', '', $cleaned);
    
    return $cleaned;
}

// ============================================
// 新增/更新卡片
// ============================================
function handleSave($userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('僅支援 POST 請求');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = isset($input['id']) ? (int)$input['id'] : null;
    $col = cleanInput($input['col'] ?? 'lib');
    $title = cleanInput($input['title'] ?? '');
    $project = cleanInput($input['project'] ?? null);
    $sourceLink = cleanInput($input['sourceLink'] ?? null);
    $summary = cleanInput($input['summary'] ?? null);
    $nextStep = cleanInput($input['nextStep'] ?? null);
    
    // ⭐ 重點修改：body 使用 cleanHTML 而不是 cleanInput
    $body = cleanHTML($input['body'] ?? null);
    
    $bgcolor = cleanInput($input['bgcolor'] ?? null);
    $textcolor = cleanInput($input['textcolor'] ?? null);
    $completedAt = $input['completedAt'] ?? null;
    $isPrivate = isset($input['isPrivate']) ? (int)$input['isPrivate'] : 0;
    
    // 处理 checklist（JSON 格式）
    $checklist = null;
    if (isset($input['checklist']) && is_array($input['checklist'])) {
        $checklist = json_encode($input['checklist'], JSON_UNESCAPED_UNICODE);
    }
    
    if (empty($title)) {
        errorResponse('標題不能為空');
    }
    
    $validCols = ['lib', 'week', 'focus', 'done'];
    if (!in_array($col, $validCols)) {
        errorResponse('無效的欄位');
    }
    
    try {
        $db = getDB();
        
        if ($id) {
            // 更新現有卡片（家庭模式：允許更新任何卡片）
            // 先获取卡片信息，判断是否需要通知
            $cardStmt = $db->prepare("SELECT is_private, created_by FROM cards WHERE id = ?");
            $cardStmt->execute([$id]);
            $cardInfo = $cardStmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $db->prepare("
                UPDATE cards SET 
                    col = ?, title = ?, project = ?, source_link = ?, summary = ?, next_step = ?, body = ?, bgcolor = ?, textcolor = ?, completed_at = ?, is_private = ?, checklist = ?
                WHERE id = ?
            ");
            $stmt->execute([$col, $title, $project, $sourceLink, $summary, $nextStep, $body, $bgcolor, $textcolor, $completedAt, $isPrivate, $checklist, $id]);
            
            // 创建通知（仅共用卡片且不是自己）
            if ($cardInfo && !$isPrivate && $cardInfo['created_by'] != $userId) {
                createNotification($db, $cardInfo['created_by'], $userId, $id, 'updated', $title);
            }
            
            successResponse(['id' => $id], '卡片更新成功');
            
        } else {
            // 新增卡片（記錄創建者）
            // 先取得當前用戶的 username
            $userStmt = $db->prepare("SELECT username FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            $username = $userStmt->fetchColumn();
            
            $stmt = $db->prepare("
                INSERT INTO cards (user_id, col, title, project, source_link, summary, next_step, body, bgcolor, textcolor, completed_at, created_by, created_by_username, is_private, checklist) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $col, $title, $project, $sourceLink, $summary, $nextStep, $body, $bgcolor, $textcolor, $completedAt, $userId, $username, $isPrivate, $checklist]);
            $newCardId = $db->lastInsertId();
            
            // 创建通知（仅共用卡片，通知其他所有用户）
            if (!$isPrivate) {
                notifyOtherUsers($db, $userId, $newCardId, 'created', $title);
            }
            
            successResponse(['id' => $newCardId], '卡片新增成功');
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
    
    if (!$id) {
        errorResponse('缺少卡片 ID');
    }
    
    try {
        $db = getDB();
        // ⭐ 家庭模式：允許刪除任何卡片
        $stmt = $db->prepare("DELETE FROM cards WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            successResponse([], '卡片刪除成功');
        } else {
            errorResponse('卡片不存在');
        }
        
    } catch (Exception $e) {
        errorResponse('刪除卡片失敗');
    }
}

// ============================================
// 移動卡片
// ============================================
function handleMove($userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('僅支援 POST 請求');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = (int)($input['id'] ?? 0);
    $newCol = cleanInput($input['col'] ?? '');
    
    if (!$id || !$newCol) {
        errorResponse('缺少必要參數');
    }
    
    $validCols = ['lib', 'week', 'focus', 'done'];
    if (!in_array($newCol, $validCols)) {
        errorResponse('無效的欄位');
    }
    
    try {
        $db = getDB();
        
        // ⭐ 家庭模式：允許移動任何卡片
        if ($newCol === 'done') {
            $stmt = $db->prepare("UPDATE cards SET col = ?, completed_at = NOW() WHERE id = ?");
        } else {
            $stmt = $db->prepare("UPDATE cards SET col = ?, completed_at = NULL WHERE id = ?");
        }
        
        $stmt->execute([$newCol, $id]);
        
        // 获取卡片信息用于通知
        $cardStmt = $db->prepare("SELECT title, is_private, created_by FROM cards WHERE id = ?");
        $cardStmt->execute([$id]);
        $cardInfo = $cardStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($stmt->rowCount() > 0) {
            // 创建通知（移动到完成时，通知卡片创建者）
            if ($newCol === 'done' && $cardInfo && !$cardInfo['is_private'] && $cardInfo['created_by'] != $userId) {
                createNotification($db, $cardInfo['created_by'], $userId, $id, 'completed', $cardInfo['title']);
            }
            
            successResponse(['id' => $id, 'col' => $newCol], '卡片移動成功');
        } else {
            errorResponse('卡片不存在');
        }
        
    } catch (Exception $e) {
        errorResponse('移動卡片失敗');
    }
}

// ============================================
// 通知相关辅助函数
// ============================================

/**
 * 创建单个通知
 */
function createNotification($db, $receiverId, $actorId, $cardId, $action, $cardTitle) {
    // 获取操作者用户名
    $userStmt = $db->prepare("SELECT username FROM users WHERE id = ?");
    $userStmt->execute([$actorId]);
    $actorUsername = $userStmt->fetchColumn();
    
    // 生成通知消息
    $actionMessages = [
        'created' => '新增了卡片',
        'updated' => '更新了卡片',
        'completed' => '完成了卡片',
        'checklist' => '更新了待辦清單'
    ];
    
    $message = ($actionMessages[$action] ?? '操作了卡片') . '「' . $cardTitle . '」';
    
    // 插入通知
    try {
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, actor_id, actor_username, card_id, action, card_title, message) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$receiverId, $actorId, $actorUsername, $cardId, $action, $cardTitle, $message]);
    } catch (Exception $e) {
        // 通知创建失败不影响主操作
        error_log('创建通知失败: ' . $e->getMessage());
    }
}

/**
 * 通知除当前用户外的所有用户
 */
function notifyOtherUsers($db, $currentUserId, $cardId, $action, $cardTitle) {
    try {
        // 获取所有其他用户
        $stmt = $db->prepare("SELECT id FROM users WHERE id != ?");
        $stmt->execute([$currentUserId]);
        $otherUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // 为每个用户创建通知
        foreach ($otherUsers as $userId) {
            createNotification($db, $userId, $currentUserId, $cardId, $action, $cardTitle);
        }
    } catch (Exception $e) {
        error_log('批量通知失败: ' . $e->getMessage());
    }
}
