<?php
/**
 * 卡片 API（修正版）
 * - 統一回傳 SESSION_EXPIRED 代碼
 * - 補上 postpone 路由
 * - 支援 private / shared 卡片
 * - 支援 created_by / createdByUsername
 * - 支援 postponed_count
 */

require_once __DIR__ . '/config.php';

function sessionExpiredResponse() {
    jsonResponse([
        'success' => false,
        'error'   => '登入已過期，請重新登入',
        'code'    => 'SESSION_EXPIRED'
    ], 200);
}

if (!isset($_SESSION['user_id'])) {
    sessionExpiredResponse();
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    ensureCardsSchema(getDB());
} catch (Exception $e) {
    errorResponse('資料庫結構初始化失敗: ' . $e->getMessage());
}

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

    case 'postpone':
        handlePostpone($userId);
        break;

    default:
        errorResponse('無效的卡片動作');
}

/**
 * 確保 cards 資料表與必要欄位存在
 */
function ensureCardsSchema(PDO $db) {
    static $checked = false;
    if ($checked) {
        return;
    }

    $db->exec("
        CREATE TABLE IF NOT EXISTS cards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL DEFAULT 1,
            created_by INT DEFAULT NULL,
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
            postponed_count INT NOT NULL DEFAULT 0,
            completed_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_col (user_id, col),
            INDEX idx_created_by (created_by),
            INDEX idx_is_private (is_private),
            INDEX idx_project (project),
            INDEX idx_priority (priority),
            INDEX idx_completed_at (completed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $columnResult = $db->query("SHOW COLUMNS FROM cards")->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columnResult, 'Field');

    $requiredColumns = [
        'created_by'      => "ALTER TABLE cards ADD COLUMN created_by INT DEFAULT NULL AFTER user_id",
        'project'         => "ALTER TABLE cards ADD COLUMN project VARCHAR(50) DEFAULT NULL AFTER title",
        'priority'        => "ALTER TABLE cards ADD COLUMN priority VARCHAR(32) DEFAULT NULL AFTER project",
        'source_link'     => "ALTER TABLE cards ADD COLUMN source_link TEXT DEFAULT NULL AFTER priority",
        'summary'         => "ALTER TABLE cards ADD COLUMN summary TEXT DEFAULT NULL AFTER source_link",
        'next_step'       => "ALTER TABLE cards ADD COLUMN next_step TEXT DEFAULT NULL AFTER summary",
        'body'            => "ALTER TABLE cards ADD COLUMN body TEXT DEFAULT NULL AFTER next_step",
        'checklist'       => "ALTER TABLE cards ADD COLUMN checklist JSON DEFAULT NULL AFTER body",
        'bgcolor'         => "ALTER TABLE cards ADD COLUMN bgcolor VARCHAR(20) DEFAULT NULL AFTER checklist",
        'textcolor'       => "ALTER TABLE cards ADD COLUMN textcolor VARCHAR(20) DEFAULT NULL AFTER bgcolor",
        'is_private'      => "ALTER TABLE cards ADD COLUMN is_private TINYINT(1) NOT NULL DEFAULT 0 AFTER textcolor",
        'postponed_count' => "ALTER TABLE cards ADD COLUMN postponed_count INT NOT NULL DEFAULT 0 AFTER is_private",
        'completed_at'    => "ALTER TABLE cards ADD COLUMN completed_at DATETIME DEFAULT NULL AFTER postponed_count",
        'created_at'      => "ALTER TABLE cards ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER completed_at",
        'updated_at'      => "ALTER TABLE cards ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
    ];

    foreach ($requiredColumns as $columnName => $alterSql) {
        if (!in_array($columnName, $existingColumns, true)) {
            $db->exec($alterSql);
        }
    }

    // 補齊既有資料的 created_by
    $db->exec("UPDATE cards SET created_by = user_id WHERE created_by IS NULL");

    $checked = true;
}

function cleanHTML($html) {
    if ($html === null || $html === '') {
        return null;
    }

    $allowedTags = '<p><br><strong><em><u><s><ol><ul><li><h1><h2><h3><span><a><img><blockquote>';
    $cleaned = strip_tags((string)$html, $allowedTags);

    $cleaned = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $cleaned);
    $cleaned = preg_replace('/on\w+="[^"]*"/i', '', $cleaned);
    $cleaned = preg_replace("/on\w+='[^']*'/i", '', $cleaned);
    $cleaned = preg_replace('/javascript:/i', '', $cleaned);

    return trim($cleaned) === '' ? null : $cleaned;
}

function normalizeChecklist($rawChecklist) {
    if ($rawChecklist === null || $rawChecklist === '') {
        return null;
    }

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

function normalizeBoolToInt($value) {
    return !empty($value) ? 1 : 0;
}

function visibleCardsWhereSql() {
    return "(cards.is_private = 0 OR cards.created_by = ? OR cards.user_id = ?)";
}

function editableCardsWhereSql() {
    return "(created_by = ? OR user_id = ?)";
}

function handleList($userId) {
    try {
        $db = getDB();
        $sql = "
            SELECT
                cards.id,
                cards.col,
                cards.title,
                cards.project,
                cards.priority,
                cards.source_link,
                cards.summary,
                cards.next_step,
                cards.body,
                cards.checklist,
                cards.bgcolor,
                cards.textcolor,
                cards.is_private,
                cards.postponed_count,
                cards.completed_at,
                cards.created_at,
                cards.updated_at,
                COALESCE(u.username, '') AS created_by_username
            FROM cards
            LEFT JOIN users u ON u.id = cards.created_by
            WHERE " . visibleCardsWhereSql() . "
            ORDER BY cards.created_at ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([$userId, $userId]);
        $cards = $stmt->fetchAll();

        $result = [
            'lib' => [],
            'week' => [],
            'focus' => [],
            'done' => []
        ];

        foreach ($cards as $card) {
            $col = $card['col'];
            if (!isset($result[$col])) {
                continue;
            }

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
                'isPrivate' => (int)$card['is_private'] === 1,
                'postponedCount' => (int)$card['postponed_count'],
                'createdByUsername' => $card['created_by_username'],
                'completedAt' => $card['completed_at'],
                'createdAt' => $card['created_at'],
                'updatedAt' => $card['updated_at']
            ];
        }

        jsonResponse($result);

    } catch (Exception $e) {
        errorResponse('取得卡片失敗: ' . (APP_DEBUG ? $e->getMessage() : '請稍後再試'));
    }
}

function handleSave($userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('僅支援 POST 請求');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        errorResponse('資料格式錯誤');
    }

    $id = isset($input['id']) ? (int)$input['id'] : null;
    $col = cleanInput($input['col'] ?? 'lib');
    $title = cleanInput($input['title'] ?? '');
    $project = cleanInput($input['project'] ?? null);
    $priority = normalizePriority($input['priority'] ?? null);
    $sourceLink = cleanInput($input['sourceLink'] ?? null);
    $summary = cleanInput($input['summary'] ?? null);
    $nextStep = cleanInput($input['nextStep'] ?? null);
    $body = cleanHTML($input['body'] ?? null);
    $bgcolor = cleanInput($input['bgcolor'] ?? null);
    $textcolor = cleanInput($input['textcolor'] ?? null);
    $completedAt = $input['completedAt'] ?? null;
    $checklist = normalizeChecklist($input['checklist'] ?? null);
    $isPrivate = normalizeBoolToInt($input['isPrivate'] ?? 0);

    if ($title === '') {
        errorResponse('標題不能為空');
    }

    $validCols = ['lib', 'week', 'focus', 'done'];
    if (!in_array($col, $validCols, true)) {
        errorResponse('無效的欄位');
    }

    try {
        $db = getDB();

        if ($col === 'done' && empty($completedAt)) {
            $completedAt = date('Y-m-d H:i:s');
        }
        if ($col !== 'done') {
            $completedAt = null;
        }

        if ($id) {
            $stmt = $db->prepare("
                UPDATE cards SET
                    col = ?,
                    title = ?,
                    project = ?,
                    priority = ?,
                    source_link = ?,
                    summary = ?,
                    next_step = ?,
                    body = ?,
                    checklist = ?,
                    bgcolor = ?,
                    textcolor = ?,
                    is_private = ?,
                    completed_at = ?
                WHERE id = ?
                  AND " . editableCardsWhereSql() . "
            ");

            $stmt->execute([
                $col,
                $title,
                $project,
                $priority,
                $sourceLink,
                $summary,
                $nextStep,
                $body,
                $checklist,
                $bgcolor,
                $textcolor,
                $isPrivate,
                $completedAt,
                $id,
                $userId,
                $userId
            ]);

            if ($stmt->rowCount() === 0) {
                errorResponse('卡片不存在或無權限修改');
            }

            successResponse(['id' => $id], '卡片更新成功');
        } else {
            $stmt = $db->prepare("
                INSERT INTO cards (
                    user_id,
                    created_by,
                    col,
                    title,
                    project,
                    priority,
                    source_link,
                    summary,
                    next_step,
                    body,
                    checklist,
                    bgcolor,
                    textcolor,
                    is_private,
                    completed_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $userId,
                $userId,
                $col,
                $title,
                $project,
                $priority,
                $sourceLink,
                $summary,
                $nextStep,
                $body,
                $checklist,
                $bgcolor,
                $textcolor,
                $isPrivate,
                $completedAt
            ]);

            successResponse(['id' => $db->lastInsertId()], '卡片新增成功');
        }

    } catch (Exception $e) {
        errorResponse('儲存卡片失敗: ' . (APP_DEBUG ? $e->getMessage() : '請稍後再試'));
    }
}

function handleDelete($userId) {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) {
        errorResponse('缺少卡片 ID');
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("
            DELETE FROM cards
            WHERE id = ?
              AND " . editableCardsWhereSql() . "
        ");
        $stmt->execute([$id, $userId, $userId]);

        if ($stmt->rowCount() > 0) {
            successResponse([], '卡片刪除成功');
        } else {
            errorResponse('卡片不存在或無權限刪除');
        }

    } catch (Exception $e) {
        errorResponse('刪除卡片失敗: ' . (APP_DEBUG ? $e->getMessage() : '請稍後再試'));
    }
}

function handleMove($userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('僅支援 POST 請求');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        errorResponse('資料格式錯誤');
    }

    $id = (int)($input['id'] ?? 0);
    $newCol = cleanInput($input['col'] ?? '');

    if ($id <= 0 || $newCol === '') {
        errorResponse('缺少必要參數');
    }

    $validCols = ['lib', 'week', 'focus', 'done'];
    if (!in_array($newCol, $validCols, true)) {
        errorResponse('無效的欄位');
    }

    try {
        $db = getDB();

        if ($newCol === 'done') {
            $stmt = $db->prepare("
                UPDATE cards
                SET col = ?, completed_at = NOW()
                WHERE id = ?
                  AND " . editableCardsWhereSql() . "
            ");
        } else {
            $stmt = $db->prepare("
                UPDATE cards
                SET col = ?, completed_at = NULL
                WHERE id = ?
                  AND " . editableCardsWhereSql() . "
            ");
        }

        $stmt->execute([$newCol, $id, $userId, $userId]);

        if ($stmt->rowCount() > 0) {
            successResponse(['id' => $id, 'col' => $newCol], '卡片移動成功');
        } else {
            errorResponse('卡片不存在或無權限移動');
        }

    } catch (Exception $e) {
        errorResponse('移動卡片失敗: ' . (APP_DEBUG ? $e->getMessage() : '請稍後再試'));
    }
}

function handlePostpone($userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('僅支援 POST 請求');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        errorResponse('資料格式錯誤');
    }

    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) {
        errorResponse('缺少卡片 ID');
    }

    try {
        $db = getDB();
        $stmt = $db->prepare("
            UPDATE cards
            SET col = 'lib',
                completed_at = NULL,
                postponed_count = postponed_count + 1
            WHERE id = ?
              AND " . editableCardsWhereSql() . "
        ");
        $stmt->execute([$id, $userId, $userId]);

        if ($stmt->rowCount() > 0) {
            successResponse(['id' => $id, 'col' => 'lib'], '卡片已延到下週');
        } else {
            errorResponse('卡片不存在或無權限延期');
        }

    } catch (Exception $e) {
        errorResponse('延期失敗: ' . (APP_DEBUG ? $e->getMessage() : '請稍後再試'));
    }
}
