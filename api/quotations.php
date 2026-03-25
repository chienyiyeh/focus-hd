<?php
// ========== CORS 設定（允許實時預覽訪問）==========
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);
// ==================================================

require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    errorResponse('未登入');
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

switch ($action) {
    case 'list': handleList($userId); break;
    case 'get': handleGet($userId); break;
    case 'save': handleSave($userId); break;
    case 'delete': handleDelete($userId); break;
    default: errorResponse('無效的操作');
}

function handleList($userId) {
    try {
        $db = getDB();
        $sql = "SELECT q.*, COUNT(qi.id) as item_count
                FROM quotations q
                LEFT JOIN quotation_items qi ON q.id = qi.quotation_id
                GROUP BY q.id
                ORDER BY q.created_at DESC";
        $stmt = $db->query($sql);
        $quotations = $stmt->fetchAll();
        jsonResponse(['quotations' => $quotations]);
    } catch (Exception $e) {
        errorResponse('取得報價單失敗');
    }
}

function handleGet($userId) {
    try {
        $id = $_GET['id'] ?? null;
        if (!$id) { errorResponse('缺少報價單 ID'); return; }
        
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM quotations WHERE id = ?");
        $stmt->execute([$id]);
        $quotation = $stmt->fetch();
        
        if (!$quotation) { errorResponse('報價單不存在'); return; }
        
        $stmt = $db->prepare("SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY item_order");
        $stmt->execute([$id]);
        $items = $stmt->fetchAll();
        
        $quotation['items'] = $items;
        jsonResponse(['quotation' => $quotation]);
    } catch (Exception $e) {
        errorResponse('取得報價單失敗');
    }
}

function handleSave($userId) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { errorResponse('僅支援 POST 請求'); return; }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = $input['id'] ?? null;
    $customer_name = cleanInput($input['customer_name'] ?? '');
    $customer_company = cleanInput($input['customer_company'] ?? '');
    $customer_phone = cleanInput($input['customer_phone'] ?? '');
    $customer_email = cleanInput($input['customer_email'] ?? '');
    $quote_date = $input['quote_date'] ?? date('Y-m-d');
    $items = $input['items'] ?? [];
    $notes = cleanInput($input['notes'] ?? '');
    
    if (!$customer_name) { errorResponse('客戶姓名不能為空'); return; }
    if (empty($items)) { errorResponse('至少需要一個品項'); return; }
    
    try {
        $db = getDB();
        $db->beginTransaction();
        
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += floatval($item['quantity']) * floatval($item['unit_price']);
        }
        
        $tax_amount = round($subtotal * 0.05, 2);
        $total_amount = $subtotal + $tax_amount;
        
        if ($id) {
            $stmt = $db->prepare("UPDATE quotations SET customer_name = ?, customer_company = ?, customer_phone = ?, customer_email = ?, quote_date = ?, subtotal = ?, tax_amount = ?, total_amount = ?, notes = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$customer_name, $customer_company, $customer_phone, $customer_email, $quote_date, $subtotal, $tax_amount, $total_amount, $notes, $id]);
            
            $stmt = $db->prepare("DELETE FROM quotation_items WHERE quotation_id = ?");
            $stmt->execute([$id]);
            
            $quotation_id = $id;
        } else {
            $quotation_number = generateDocumentNumber($db, 'quotation');
            
            $stmt = $db->prepare("INSERT INTO quotations (quotation_number, customer_name, customer_company, customer_phone, customer_email, quote_date, subtotal, tax_amount, total_amount, notes, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)");
            $stmt->execute([$quotation_number, $customer_name, $customer_company, $customer_phone, $customer_email, $quote_date, $subtotal, $tax_amount, $total_amount, $notes, $userId]);
            
            $quotation_id = $db->lastInsertId();
        }
        
        $stmt = $db->prepare("INSERT INTO quotation_items (quotation_id, item_order, item_name, specification, quantity, unit, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($items as $index => $item) {
            $item_subtotal = floatval($item['quantity']) * floatval($item['unit_price']);
            $stmt->execute([$quotation_id, $index + 1, $item['item_name'], $item['specification'] ?? '', $item['quantity'], $item['unit'] ?? '張', $item['unit_price'], $item_subtotal]);
        }
        
        $db->commit();
        
        successResponse(['id' => $quotation_id], $id ? '報價單已更新' : '報價單已新增');
    } catch (Exception $e) {
        $db->rollBack();
        errorResponse('儲存報價單失敗: ' . $e->getMessage());
    }
}

function handleDelete($userId) {
    try {
        $id = $_GET['id'] ?? $_POST['id'] ?? null;
        if (!$id) { errorResponse('缺少報價單 ID'); return; }
        
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM quotations WHERE id = ?");
        $stmt->execute([$id]);
        
        successResponse([], '報價單已刪除');
    } catch (Exception $e) {
        errorResponse('刪除報價單失敗');
    }
}

function generateDocumentNumber($db, $docType) {
    $prefix = ['quotation' => 'Q', 'work_order' => 'W', 'delivery' => 'D', 'invoice' => 'I'][$docType];
    $year = date('Y');
    $date = date('md');
    $dateKey = date('Ymd');
    
    $stmt = $db->prepare("INSERT INTO document_counters (doc_type, date_key, counter) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE counter = counter + 1");
    $stmt->execute([$docType, $dateKey]);
    
    $stmt = $db->prepare("SELECT counter FROM document_counters WHERE doc_type = ? AND date_key = ?");
    $stmt->execute([$docType, $dateKey]);
    $result = $stmt->fetch();
    $counter = str_pad($result['counter'], 3, '0', STR_PAD_LEFT);
    
    return "$prefix-$year-$date-$counter";
}
?>
