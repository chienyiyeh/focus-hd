<?php
/**
 * WordPress 訂單 Webhook 接收器
 * 作用：WordPress 有新訂單 → 自動建立一張卡片
 * 
 * 使用方法：
 * 1. 複製到 api/webhook.php
 * 2. WordPress 設定 webhook 指向這個網址
 * 3. 完成！
 */

require_once '../config.php';

// ============================================
// 步驟 1：檢查密碼（安全機制）
// ============================================
// WordPress 傳來的密鑰（要跟 config.php 一致）
define('WEBHOOK_SECRET', 'your-secret-key-from-wordpress');

// 取得 WordPress 傳來的密鑰
$wpSecret = $_GET['secret'] ?? $_POST['secret'] ?? null;

// 如果密鑰不對，拒絕
if ($wpSecret !== WEBHOOK_SECRET) {
    errorResponse('密鑰錯誤，拒絕請求');
    exit;
}

// ============================================
// 步驟 2：取得 WordPress 傳來的訂單資訊
// ============================================
$input = json_decode(file_get_contents('php://input'), true);

// 檢查必要欄位
if (!isset($input['order_id']) || !isset($input['customer_name'])) {
    errorResponse('缺少必要的訂單資訊（order_id、customer_name）');
    exit;
}

// 把資訊變成易用的變數
$orderId       = (int)$input['order_id'];           // 訂單編號，例：12345
$customerName  = cleanInput($input['customer_name']); // 客戶名字
$orderAmount   = (float)($input['amount'] ?? 0);     // 訂單金額
$orderDate     = $input['order_date'] ?? date('Y-m-d H:i:s'); // 訂單日期
$orderStatus   = cleanInput($input['status'] ?? 'pending');    // 訂單狀態
$deliveryDate  = $input['delivery_date'] ?? null;   // 交貨日期
$notes         = cleanInput($input['notes'] ?? '');  // 備註

// ============================================
// 步驟 3：建立卡片標題和內容
// ============================================
$cardTitle = "【訂單 #{$orderId}】{$customerName}"; // 例：【訂單 #12345】王小明
$cardBody  = "訂單編號：$orderId\n";
$cardBody .= "客戶：$customerName\n";
$cardBody .= "金額：NT\${$orderAmount}\n";
$cardBody .= "訂單日期：$orderDate\n";
$cardBody .= "交貨日期：$deliveryDate\n";
$cardBody .= "狀態：$orderStatus\n";
if (!empty($notes)) $cardBody .= "備註：$notes\n";

// ============================================
// 步驟 4：存進資料庫
// ============================================
try {
    $db = getDB();
    
    // 檢查這個訂單有沒有已經在系統裡
    $checkStmt = $db->prepare("
        SELECT id FROM cards 
        WHERE source_link LIKE ? AND col='lib'
        LIMIT 1
    ");
    $checkStmt->execute(["%order_id=$orderId%"]);
    $existing = $checkStmt->fetch();
    
    if ($existing) {
        // 訂單已存在，更新內容
        $updateStmt = $db->prepare("
            UPDATE cards SET
                title = ?,
                body = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([
            $cardTitle,
            $cardBody,
            $existing['id']
        ]);
        $cardId = $existing['id'];
        $message = '訂單已更新';
    } else {
        // 新訂單，建立卡片
        $insertStmt = $db->prepare("
            INSERT INTO cards
            (user_id, col, title, body, source_link, project, priority, created_at)
            VALUES
            (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $insertStmt->execute([
            1,                      // user_id（chienyi）
            'lib',                  // 放在策略筆記區
            $cardTitle,             // 標題
            $cardBody,              // 內容
            "order_id=$orderId",    // 來源連結（方便查詢）
            'client',               // 專案類型
            'urgent_important',     // 優先度（訂單都是重要）
        ]);
        $cardId = $db->lastInsertId();
        $message = '訂單卡片已建立';
    }
    
    // 成功！
    successResponse([
        'card_id' => $cardId,
        'order_id' => $orderId,
        'customer_name' => $customerName
    ], $message);
    
} catch (Exception $e) {
    errorResponse('建立卡片失敗：' . $e->getMessage());
}
