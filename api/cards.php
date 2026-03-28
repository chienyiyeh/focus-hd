<?php
/**
 * 卡片 API
 */

require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    errorResponse('登入已過期，請重新登入');
    exit;
}
$userId = $_SESSION['user_id'];

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

switch ($action) {
    case 'list':   handleList($userId);   break;
    case 'save':   handleSave($userId);   break;
    case 'delete': handleDelete($userId); break;
    case 'move':   handleMove($userId);   break;
    default:       errorResponse('無效的卡片動作');
}

// ============================================
// 自動補欄位（防呆）
// ============================================
function ensureColumns($db) {
    $columns = array_column(
        $db->query("SHOW COLUMNS FROM cards")->fetchAll(PDO::FETCH_ASSOC),
        'Field'
    );
    if (!in_array('priority', $columns))
        $db->exec("ALTER TABLE cards ADD COLUMN priority VARCHAR(32) DEFAULT NULL");
    if (!in_array('checklist', $columns))
        $db->exec("ALTER TABLE cards ADD COLUMN checklist JSON DEFAULT NULL");
    if (!in_array('is_private', $columns))
        $db->exec("ALTER TABLE cards ADD COLUMN is_private TINYINT(1) NOT NULL DEFAULT 0");
    if (!in_array('created_by', $columns))
        $db->exec("ALTER TABLE cards ADD COLUMN created_by INT DEFAULT NULL");
    if (!in_array('postponed_count', $columns))
        $db->exec("ALTER TABLE cards ADD COLUMN postponed_count INT DEFAULT 0");
}

// ============================================
// 取得所有卡片
// ============================================
function handleList($userId) {
    try {
        $db = getDB();
        ensureColumns($db);

        $stmt = $db->prepare("
            SELECT c.id, c.col, c.title, c.project, c.priority, c.source_link,
                   c.summary, c.next_step, c.body, c.checklist, c.bgcolor,
                   c.textcolor, c.is_private, c.created_by, c.completed_at,
                   c.created_at, c.updated_at,
                   u.username as created_by_username
            FROM cards c
            LEFT JOIN users u ON c.created_by = u.id
            WHERE c.user_id = ? OR c.is_private = 0
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([$userId]);
        $cards = $stmt->fetchAll();

        $result = ['lib' => [], 'week' => [], 'focus' => [], 'done' => []];

        foreach ($cards as $card) {
            $col = $card['col'];
            if (!isset($result[$col])) continue;
            $result[$col][] = [
                'id'                => (int)$card['id'],
                'title'             => $card['title'],
                'project'           => $card['project'],
                'priority'          => $card['priority'],
                'sourceLink'        => $card['source_link'],
                'summary'           => $card['summary'],
                'nextStep'          => $card['next_step'],
                'body'              => $card['body'],
                'checklist'         => $card['checklist'] ? json_decode($card['checklist'], true) : null,
                'bgcolor'           => $card['bgcolor'],
                'textcolor'         => $card['textcolor'],
                'isPrivate'         => (bool)$card['is_private'],
                'createdBy'         => $card['created_by'],
                'createdByUsername' => $card['created_by_username'],
                'completedAt'       => $card['completed_at'],
                'createdAt'         => $card['created_at'],
                'updatedAt'         => $card['updated_at'],
            ];
        }
        jsonResponse($result);
    } catch (Exception $e) {
        errorResponse('取得卡片失敗');
    }
}

// ============================================
// 清理 HTML
// ============================================
function cleanHTML($html) {
    if (empty($html)) return null;
    $allowed = '<p><br><strong><em><u><s><ol><ul><li><h1><h2><h3><span><a><img>';
    $cleaned = strip_tags($html, $allowed);
    $cleaned = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $cleaned);
    $cleaned = preg_replace('/on\w+="[^"]*"/i', '', $cleaned);
    $cleaned = preg_replace('/on\w+=\'[^\']*\'/i', '', $cleaned);
    return $cleaned;
}

// ============================================
// 新增/更新卡片（只有自己的卡片才能改）
// ============================================
function handleSave($userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') errorResponse('僅支援 POST 請求');

    $input = json_decode(file_get_contents('php://input'), true);

    $id         = isset($input['id']) ? (int)$input['id'] : null;
    $col        = cleanInput($input['col'] ?? 'lib');
    $title      = cleanInput($input['title'] ?? '');
    $project    = cleanInput($input['project'] ?? null);
    $priority   = cleanInput($input['priority'] ?? null);
    $sourceLink = cleanInput($input['sourceLink'] ?? null);
    $summary    = cleanInput($input['summary'] ?? null);
    $nextStep   = cleanInput($input['nextStep'] ?? null);
    $body       = cleanHTML($input['body'] ?? null);
    $bgcolor    = cleanInput($input['bgcolor'] ?? null);
    $textcolor  = cleanInput($input['textcolor'] ?? null);
    $isPrivate  = isset($input['isPrivate']) ? (int)$input['isPrivate'] : 0;
    $checklist  = !empty($input['checklist']) ? json_encode($input['checklist'], JSON_UNESCAPED_UNICODE) : null;
    $completedAt = $input['completedAt'] ?? null;

    if (empty($title)) errorResponse('標題不能為空');
    if (!in_array($col, ['lib', 'week', 'focus', 'done'])) errorResponse('無效的欄位');

    try {
        $db = getDB();
        ensureColumns($db);

        if ($id) {
            $stmt = $db->prepare("
                UPDATE cards SET
                    col=?, title=?, project=?, priority=?, source_link=?,
                    summary=?, next_step=?, body=?, checklist=?,
                    bgcolor=?, textcolor=?, is_private=?, completed_at=?
                WHERE id=? AND user_id=?
            ");
            $stmt->execute([
                $col, $title, $project, $priority, $sourceLink,
                $summary, $nextStep, $body, $checklist,
                $bgcolor, $textcolor, $isPrivate, $completedAt,
                $id, $userId
            ]);
            if ($stmt->rowCount() > 0) {
                successResponse(['id' => $id], '卡片更新成功');
            } else {
                errorResponse('卡片不存在或無權限編輯');
            }
        } else {
            $stmt = $db->prepare("
                INSERT INTO cards
                    (user_id, col, title, project, priority, source_link,
                     summary, next_step, body, checklist,
                     bgcolor, textcolor, is_private, created_by, completed_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $userId, $col, $title, $project, $priority, $sourceLink,
                $summary, $nextStep, $body, $checklist,
                $bgcolor, $textcolor, $isPrivate, $userId, $completedAt
            ]);
            successResponse(['id' => $db->lastInsertId()], '卡片新增成功');
        }
    } catch (Exception $e) {
        errorResponse('儲存卡片失敗: ' . $e->getMessage());
    }
}

// ============================================
// 刪除卡片（只有自己的卡片才能刪）
// ============================================
function handleDelete($userId) {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if (!$id) errorResponse('缺少卡片 ID');
    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM cards WHERE id=? AND user_id=?");
        $stmt->execute([$id, $userId]);
        if ($stmt->rowCount() > 0) successResponse([], '卡片刪除成功');
        else errorResponse('卡片不存在或無權限刪除');
    } catch (Exception $e) {
        errorResponse('刪除卡片失敗');
    }
}

// ============================================
// 移動卡片
// 規則：自己的卡片可以移到任何地方
//       別人的卡片只能移出（不能移入 focus/week）
// ============================================
function handleMove($userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') errorResponse('僅支援 POST 請求');
    $input = json_decode(file_get_contents('php://input'), true);
    $id     = (int)($input['id'] ?? 0);
    $newCol = cleanInput($input['col'] ?? '');
    if (!$id || !$newCol) errorResponse('缺少必要參數');
    if (!in_array($newCol, ['lib', 'week', 'focus', 'done'])) errorResponse('無效的欄位');

    try {
        $db = getDB();

        // 先查這張卡片是誰的
        $stmt = $db->prepare("SELECT user_id, col FROM cards WHERE id=?");
        $stmt->execute([$id]);
        $card = $stmt->fetch();

        if (!$card) errorResponse('卡片不存在');

        $isOwner = ((int)$card['user_id'] === (int)$userId);

        // 不是自己的卡片，只能退回策略庫
        if (!$isOwner && in_array($newCol, ['focus', 'week', 'done'])) {
            errorResponse('只能將別人的卡片退回策略庫');
        }

        // 移入 focus：每人各有 1 個額度
        if ($newCol === 'focus' && $card['col'] !== 'focus') {
            $checkStmt = $db->prepare("SELECT COUNT(*) FROM cards WHERE col='focus' AND user_id=?");
            $checkStmt->execute([$userId]);
            if ((int)$checkStmt->fetchColumn() >= 1) {
                errorResponse('你的今日專注已有 1 張，請先完成或移出');
            }
        }

        // 移入 week：每人最多 3 張
        if ($newCol === 'week' && $card['col'] !== 'week') {
            $checkStmt = $db->prepare("SELECT COUNT(*) FROM cards WHERE col='week' AND user_id=?");
            $checkStmt->execute([$userId]);
            if ((int)$checkStmt->fetchColumn() >= 3) {
                errorResponse('你的本週目標已有 3 張');
            }
        }

        if ($newCol === 'done') {
            $stmt = $db->prepare("UPDATE cards SET col=?, completed_at=NOW() WHERE id=?");
        } else {
            $stmt = $db->prepare("UPDATE cards SET col=?, completed_at=NULL WHERE id=?");
        }
        $stmt->execute([$newCol, $id]);

        if ($stmt->rowCount() > 0) successResponse(['id' => $id, 'col' => $newCol], '卡片移動成功');
        else errorResponse('移動失敗');

    } catch (Exception $e) {
        errorResponse('移動卡片失敗');
    }
}
