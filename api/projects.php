<?php
/**
 * 專案類型 API
 */

require_once __DIR__ . '/config.php';

// 檢查登入
if (!isset($_SESSION['user_id'])) {
    errorResponse('登入已過期，請重新登入');
    exit;
}
$userId = $_SESSION['user_id'];

// 獲取動作
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// 路由
switch ($action) {
    case 'list':
        handleList($userId);
        break;
        
    case 'add':
        handleAdd($userId);
        break;
        
    case 'delete':
        handleDelete($userId);
        break;
        
    default:
        errorResponse('無效的動作');
}

// ============================================
// 取得所有專案類型
// ============================================
function handleList($userId) {
    try {
        $db = getDB();
        
        // 取得預設專案 + 該用戶的自訂專案
        $stmt = $db->prepare("
            SELECT id, project_key, label, bg_color, text_color, is_default
            FROM projects 
            WHERE is_default = 1 OR user_id = ?
            ORDER BY is_default DESC, created_at ASC
        ");
        $stmt->execute([$userId]);
        $projects = $stmt->fetchAll();
        
        $result = [];
        foreach ($projects as $proj) {
            $result[] = [
                'id' => (int)$proj['id'],
                'key' => $proj['project_key'],
                'label' => $proj['label'],
                'bgColor' => $proj['bg_color'],
                'textColor' => $proj['text_color'],
                'isDefault' => (bool)$proj['is_default']
            ];
        }
        
        // ⭐ 統一返回格式
        successResponse($result, '');
        
    } catch (Exception $e) {
        errorResponse('取得專案失敗: ' . $e->getMessage());
    }
}

// ============================================
// 新增專案類型
// ============================================
function handleAdd($userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('僅支援 POST 請求');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $label = cleanInput($input['label'] ?? '');
    $bgColor = cleanInput($input['bgColor'] ?? '#E3F2FD');
    $textColor = cleanInput($input['textColor'] ?? '#1565C0');
    
    if (empty($label)) {
        errorResponse('專案名稱不能為空');
    }
    
    // 生成唯一 key
    $projectKey = 'custom_' . time() . '_' . substr(md5($label . $userId), 0, 8);
    
    try {
        $db = getDB();
        
        // 檢查是否已存在相同名稱
        $stmt = $db->prepare("SELECT id FROM projects WHERE user_id = ? AND label = ?");
        $stmt->execute([$userId, $label]);
        if ($stmt->fetch()) {
            errorResponse('專案名稱已存在');
        }
        
        // 新增專案
        $stmt = $db->prepare("
            INSERT INTO projects (user_id, project_key, label, bg_color, text_color, is_default) 
            VALUES (?, ?, ?, ?, ?, 0)
        ");
        $stmt->execute([$userId, $projectKey, $label, $bgColor, $textColor]);
        
        successResponse([
            'id' => (int)$db->lastInsertId(),
            'key' => $projectKey,
            'label' => $label,
            'bgColor' => $bgColor,
            'textColor' => $textColor,
            'isDefault' => false
        ], '專案新增成功');
        
    } catch (Exception $e) {
        errorResponse('新增專案失敗: ' . $e->getMessage());
    }
}

// ============================================
// 刪除專案類型
// ============================================
function handleDelete($userId) {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    
    if (!$id) {
        errorResponse('缺少專案 ID');
    }
    
    try {
        $db = getDB();
        
        // 檢查是否為預設專案
        $stmt = $db->prepare("SELECT is_default FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $project = $stmt->fetch();
        
        if (!$project) {
            errorResponse('專案不存在');
        }
        
        if ($project['is_default']) {
            errorResponse('無法刪除預設專案');
        }
        
        // 刪除專案（只能刪除自己的）
        $stmt = $db->prepare("DELETE FROM projects WHERE id = ? AND user_id = ? AND is_default = 0");
        $stmt->execute([$id, $userId]);
        
        if ($stmt->rowCount() > 0) {
            successResponse([], '專案刪除成功');
        } else {
            errorResponse('專案不存在或無權限刪除');
        }
        
    } catch (Exception $e) {
        errorResponse('刪除專案失敗: ' . $e->getMessage());
    }
}
