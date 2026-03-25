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
        $stmt = $db->prepare("
            SELECT id, col, title, project, priority, source_link, summary, next_step, body, checklist, bgcolor, textcolor, completed_at, created_at, updated_at
            FROM cards 
            WHERE user_id = ?
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
    $priority = cleanInput($input['priority'] ?? null);
    $sourceLink = cleanInput($input['sourceLink'] ?? null);
    $summary = cleanInput($input['summary'] ?? null);
    $nextStep = cleanInput($input['nextStep'] ?? null);
    
    // ⭐ 重點修改：body 使用 cleanHTML 而不是 cleanInput
    $body = cleanHTML($input['body'] ?? null);
    
    $bgcolor = cleanInput($input['bgcolor'] ?? null);
    $textcolor = cleanInput($input['textcolor'] ?? null);
    $completedAt = $input['completedAt'] ?? null;
    $checklist = isset($input['checklist']) && is_array($input['checklist']) ? json_encode($input['checklist'], JSON_UNESCAPED_UNICODE) : null;
    
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
            // 更新現有卡片
            $stmt = $db->prepare("
                UPDATE cards SET 
                    col = ?, title = ?, project = ?, priority = ?, source_link = ?, summary = ?, next_step = ?, body = ?, checklist = ?, bgcolor = ?, textcolor = ?, completed_at = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$col, $title, $project, $priority, $sourceLink, $summary, $nextStep, $body, $checklist, $bgcolor, $textcolor, $completedAt, $id, $userId]);
            successResponse(['id' => $id], '卡片更新成功');
            
        } else {
            // 新增卡片
            $stmt = $db->prepare("
                INSERT INTO cards (user_id, col, title, project, priority, source_link, summary, next_step, body, checklist, bgcolor, textcolor, completed_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $col, $title, $project, $priority, $sourceLink, $summary, $nextStep, $body, $checklist, $bgcolor, $textcolor, $completedAt]);
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
    
    if (!$id) {
        errorResponse('缺少卡片 ID');
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM cards WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        
        if ($stmt->rowCount() > 0) {
            successResponse([], '卡片刪除成功');
        } else {
            errorResponse('卡片不存在或無權限刪除');
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
        
        if ($newCol === 'done') {
            $stmt = $db->prepare("UPDATE cards SET col = ?, completed_at = NOW() WHERE id = ? AND user_id = ?");
        } else {
            $stmt = $db->prepare("UPDATE cards SET col = ?, completed_at = NULL WHERE id = ? AND user_id = ?");
        }
        
        $stmt->execute([$newCol, $id, $userId]);
        
        if ($stmt->rowCount() > 0) {
            successResponse(['id' => $id, 'col' => $newCol], '卡片移動成功');
        } else {
            errorResponse('卡片不存在或無權限移動');
        }
        
    } catch (Exception $e) {
        errorResponse('移動卡片失敗');
    }
}
