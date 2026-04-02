<?php
/**
 * 附件管理 API
 * 作用：上傳訂單檔案、下載檔案、刪除檔案
 * 
 * 使用方法：
 * 1. 複製到 api/attachments.php
 * 2. 確保 uploads/ 資料夾存在且可寫入
 * 3. 完成！
 */

require_once '../config.php';

// ============================================
// 設定
// ============================================
$uploadDir = '../uploads/'; // 檔案存放位置
$maxFileSize = 50 * 1024 * 1024; // 最大檔案大小（50MB）
$allowedTypes = ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx', 'zip', 'txt']; // 允許的檔案類型

// ============================================
// 初始化
// ============================================
if (!isset($_SESSION['user_id'])) {
    errorResponse('請先登入');
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;

// ============================================
// 路由（選擇做什麼）
// ============================================
switch ($action) {
    case 'upload':   handleUpload($userId);        break;
    case 'download': handleDownload($userId);      break;
    case 'delete':   handleDelete($userId);        break;
    case 'list':     handleList($userId);          break;
    default:         errorResponse('不支援的操作');
}

// ============================================
// 1. 上傳檔案
// ============================================
function handleUpload($userId) {
    global $uploadDir, $maxFileSize, $allowedTypes;
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('只支援 POST 請求');
    }
    
    // 檢查有沒有檔案
    if (!isset($_FILES['file'])) {
        errorResponse('沒有提供檔案');
    }
    
    $file = $_FILES['file'];
    $cardId = (int)($_POST['card_id'] ?? 0);
    
    if (!$cardId) {
        errorResponse('缺少卡片 ID');
    }
    
    // ----------------------------------------
    // 驗證檔案
    // ----------------------------------------
    
    // 檢查上傳有沒有出錯
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => '檔案超過伺服器限制',
            UPLOAD_ERR_FORM_SIZE  => '檔案超過表單限制',
            UPLOAD_ERR_PARTIAL    => '檔案只上傳了一部分',
            UPLOAD_ERR_NO_FILE    => '沒有上傳檔案',
            UPLOAD_ERR_NO_TMP_DIR => '伺服器缺少臨時資料夾',
            UPLOAD_ERR_CANT_WRITE => '無法寫入檔案',
            UPLOAD_ERR_EXTENSION  => '上傳被擴充程式中止',
        ];
        errorResponse($errors[$file['error']] ?? '上傳出錯');
    }
    
    // 檢查檔案大小
    if ($file['size'] > $maxFileSize) {
        errorResponse('檔案超過 50MB 限制');
    }
    
    // 檢查檔案類型
    $fileName = $file['name'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    if (!in_array($fileExt, $allowedTypes)) {
        errorResponse('不支援的檔案類型。允許：' . implode(', ', $allowedTypes));
    }
    
    // ----------------------------------------
    // 生成新檔案名（安全起見）
    // ----------------------------------------
    // 例如：card_123_2026-04-02_abc123.pdf
    $timestamp = date('Y-m-d_His');
    $randomStr = substr(bin2hex(random_bytes(4)), 0, 8);
    $newFileName = "card_{$cardId}_{$timestamp}_{$randomStr}.{$fileExt}";
    $filePath = $uploadDir . $newFileName;
    
    // ----------------------------------------
    // 建立 uploads 資料夾（如果不存在）
    // ----------------------------------------
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            errorResponse('無法建立上傳資料夾');
        }
    }
    
    // ----------------------------------------
    // 移動檔案到伺服器
    // ----------------------------------------
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        errorResponse('無法儲存檔案，請檢查伺服器權限');
    }
    
    // ----------------------------------------
    // 儲存到資料庫
    // ----------------------------------------
    try {
        $db = getDB();
        
        // 檢查 card_attachments 表有沒有（沒有的話建立）
        ensureAttachmentTable($db);
        
        // 存檔案資訊
        $stmt = $db->prepare("
            INSERT INTO card_attachments
            (card_id, user_id, file_name, file_path, file_size, uploaded_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $cardId,
            $userId,
            $fileName,        // 原始檔案名
            $newFileName,     // 伺服器上的檔案名
            $file['size']     // 檔案大小
        ]);
        
        successResponse([
            'file_id' => $db->lastInsertId(),
            'file_name' => $fileName,
            'file_size' => $file['size']
        ], '檔案上傳成功');
        
    } catch (Exception $e) {
        // 上傳失敗，刪除已存的檔案
        @unlink($filePath);
        errorResponse('儲存檔案失敗：' . $e->getMessage());
    }
}

// ============================================
// 2. 下載檔案
// ============================================
function handleDownload($userId) {
    $fileId = (int)($_GET['id'] ?? 0);
    
    if (!$fileId) {
        errorResponse('缺少檔案 ID');
    }
    
    try {
        $db = getDB();
        
        // 取得檔案資訊
        $stmt = $db->prepare("
            SELECT * FROM card_attachments
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$fileId, $userId]);
        $file = $stmt->fetch();
        
        if (!$file) {
            errorResponse('檔案不存在或無權限');
        }
        
        $filePath = '../uploads/' . $file['file_path'];
        
        // 檢查檔案是否真的存在
        if (!file_exists($filePath)) {
            errorResponse('檔案已被刪除');
        }
        
        // 下載檔案
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file['file_name'] . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
        
    } catch (Exception $e) {
        errorResponse('下載失敗：' . $e->getMessage());
    }
}

// ============================================
// 3. 刪除檔案
// ============================================
function handleDelete($userId) {
    $fileId = (int)($_POST['id'] ?? 0);
    
    if (!$fileId) {
        errorResponse('缺少檔案 ID');
    }
    
    try {
        $db = getDB();
        
        // 取得檔案資訊
        $stmt = $db->prepare("
            SELECT * FROM card_attachments
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$fileId, $userId]);
        $file = $stmt->fetch();
        
        if (!$file) {
            errorResponse('檔案不存在或無權限');
        }
        
        $filePath = '../uploads/' . $file['file_path'];
        
        // 刪除伺服器上的檔案
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
        
        // 刪除資料庫記錄
        $deleteStmt = $db->prepare("DELETE FROM card_attachments WHERE id = ?");
        $deleteStmt->execute([$fileId]);
        
        successResponse([], '檔案已刪除');
        
    } catch (Exception $e) {
        errorResponse('刪除失敗：' . $e->getMessage());
    }
}

// ============================================
// 4. 列出某張卡片的所有檔案
// ============================================
function handleList($userId) {
    $cardId = (int)($_GET['card_id'] ?? 0);
    
    if (!$cardId) {
        errorResponse('缺少卡片 ID');
    }
    
    try {
        $db = getDB();
        
        // 檢查卡片是否存在且有權限
        $cardStmt = $db->prepare("SELECT id FROM cards WHERE id = ? AND user_id = ?");
        $cardStmt->execute([$cardId, $userId]);
        if (!$cardStmt->fetch()) {
            errorResponse('卡片不存在或無權限');
        }
        
        // 取得檔案列表
        $stmt = $db->prepare("
            SELECT id, file_name, file_size, uploaded_at
            FROM card_attachments
            WHERE card_id = ?
            ORDER BY uploaded_at DESC
        ");
        $stmt->execute([$cardId]);
        $files = $stmt->fetchAll();
        
        $result = [];
        foreach ($files as $file) {
            $result[] = [
                'id' => (int)$file['id'],
                'file_name' => $file['file_name'],
                'file_size' => (int)$file['file_size'],
                'uploaded_at' => $file['uploaded_at']
            ];
        }
        
        successResponse(['files' => $result], '取得檔案列表成功');
        
    } catch (Exception $e) {
        errorResponse('取得檔案失敗：' . $e->getMessage());
    }
}

// ============================================
// 輔助函數：自動建立 card_attachments 表
// ============================================
function ensureAttachmentTable($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS card_attachments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            card_id INT NOT NULL COMMENT '卡片ID',
            user_id INT NOT NULL COMMENT '上傳者ID',
            file_name VARCHAR(255) NOT NULL COMMENT '原始檔案名',
            file_path VARCHAR(255) NOT NULL COMMENT '伺服器檔案路徑',
            file_size INT NOT NULL COMMENT '檔案大小（位元組）',
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '上傳時間',
            
            INDEX idx_card (card_id),
            INDEX idx_user (user_id),
            FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}
