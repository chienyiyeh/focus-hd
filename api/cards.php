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
        'body' => "ALTER TABLE cards ADD COLUMN body TEXT DEFAULT NULL AFTER next_step",
        'checklist' => "ALTER TABLE cards ADD COLUMN checklist JSON DEFAULT NULL AFTER body",
        'bgcolor' => "ALTER TABLE cards ADD COLUMN bgcolor VARCHAR(20) DEFAULT NULL AFTER checklist",
        'textcolor' => "ALTER TABLE cards ADD COLUMN textcolor VARCHAR(20) DEFAULT NULL AFTER bgcolor",
        'completed_at' => "ALTER TABLE cards ADD COLUMN completed_at DATETIME DEFAULT NULL AFTER textcolor",
        'created_at' => "ALTER TABLE cards ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER completed_at",
        'updated_at' => "ALTER TABLE cards ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
    ];

    foreach ($requiredColumns as $columnName => $alterSql) {
        if (!in_array($columnName, $existingColumns, true)) {
            $db->exec($alterSql);
        }
    }

    $checked = true;
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

/**
 * 正規化 checklist 欄位，確保可安全轉為 JSON 儲存
 */
function normalizeChecklist($rawChecklist) {
    if ($rawChecklist === null || $rawChecklist === '') {
        return null;
    }

    // 兼容前端傳字串 JSON 的情況
    if (is_string($rawChecklist)) {
        $decoded = json_decode($rawChecklist, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $rawChecklist = $decoded;
        }
    }

    if (!is_array($rawChecklist)) {
        return null;
    }

    $normalized = [];
    foreach ($rawChecklist as $item) {
        if (!is_array($item)) {
            continue;
        }

        $text = trim((string)($item['text'] ?? ''));
        if ($text === '') {
            continue;
        }

        $normalized[] = [
            'text' => $text,
            'checked' => !empty($item['checked'])
        ];
    }

    return !empty($normalized) ? json_encode($normalized, JSON_UNESCAPED_UNICODE) : null;
}

/**
 * 正規化 priority 欄位，僅允許四象限值
 */
function normalizePriority($rawPriority) {
    if ($rawPriority === null || $rawPriority === '') {
        return null;
    }

    $priority = cleanInput($rawPriority);
    $allowedPriorities = [
        'urgent_important',
        'important_not_urgent',
        'urgent_not_important',
        'not_urgent_not_important'
    ];

    return in_array($priority, $allowedPriorities, true) ? $priority : null;
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
    $priority = normalizePriority($input['priority'] ?? null);
    $sourceLink = cleanInput($input['sourceLink'] ?? null);
    $summary = cleanInput($input['summary'] ?? null);
    $nextStep = cleanInput($input['nextStep'] ?? null);
    
    // ⭐ 重點修改：body 使用 cleanHTML 而不是 cleanInput
    $body = cleanHTML($input['body'] ?? null);
    
    $bgcolor = cleanInput($input['bgcolor'] ?? null);
    $textcolor = cleanInput($input['textcolor'] ?? null);
    $completedAt = $input['completedAt'] ?? null;
    $checklist = normalizeChecklist($input['checklist'] ?? null);
    
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
