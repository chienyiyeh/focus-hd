<?php
// ============================================
// 工單 API
// api/work-orders.php
// ============================================

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    errorResponse('未登入');
    exit;
}

$userId   = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'unknown';
$action   = $_GET['action'] ?? $_POST['action'] ?? 'list';

switch ($action) {
    case 'list':            handleList(); break;
    case 'get':             handleGet(); break;
    case 'save':            handleSave($userId, $username); break;
    case 'convert':         handleConvertFromQuote($userId, $username); break;
    case 'update_status':   handleUpdateStatus($userId, $username); break;
    case 'delete':          handleDelete($userId, $username); break;
    case 'logs':            handleGetLogs(); break;
    default: errorResponse('無效的操作');
}

// ============================================
// 取得工單列表
// ============================================
function handleList() {
    try {
        $db = getDB();
        $status = $_GET['status'] ?? null;

        $sql = "SELECT wo.*,
                       q.quotation_number
                FROM work_orders wo
                LEFT JOIN quotations q ON wo.quotation_id = q.id
                WHERE 1=1";
        $params = [];

        if ($status) {
            $sql .= " AND wo.production_status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY wo.created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $workOrders = $stmt->fetchAll();

        jsonResponse(['work_orders' => $workOrders]);
    } catch (Exception $e) {
        errorResponse('取得工單失敗: ' . $e->getMessage());
    }
}

// ============================================
// 取得單一工單（含來源報價單資訊）
// ============================================
function handleGet() {
    try {
        $id = $_GET['id'] ?? null;
        if (!$id) { errorResponse('缺少工單 ID'); return; }

        $db = getDB();

        $stmt = $db->prepare("
            SELECT wo.*,
                   q.quotation_number, q.quote_date,
                   q.subtotal as quote_subtotal, q.tax_amount as quote_tax,
                   q.total_amount as quote_total
            FROM work_orders wo
            LEFT JOIN quotations q ON wo.quotation_id = q.id
            WHERE wo.id = ?
        ");
        $stmt->execute([$id]);
        $workOrder = $stmt->fetch();
        if (!$workOrder) { errorResponse('工單不存在'); return; }

        // 如果有來源報價單，也帶入品項
        if ($workOrder['quotation_id']) {
            $stmt = $db->prepare("SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY item_order");
            $stmt->execute([$workOrder['quotation_id']]);
            $workOrder['quote_items'] = $stmt->fetchAll();
        }

        // 流程紀錄
        $stmt = $db->prepare("SELECT * FROM job_logs WHERE doc_type='work_order' AND doc_id=? ORDER BY created_at DESC");
        $stmt->execute([$id]);
        $workOrder['logs'] = $stmt->fetchAll();

        jsonResponse(['work_order' => $workOrder]);
    } catch (Exception $e) {
        errorResponse('取得工單失敗: ' . $e->getMessage());
    }
}

// ============================================
// 新增 / 更新工單（手動建立）
// ============================================
function handleSave($userId, $username) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { errorResponse('僅支援 POST'); return; }

    $input = json_decode(file_get_contents('php://input'), true);
    $id             = $input['id'] ?? null;
    $customerName   = cleanInput($input['customer_name'] ?? '');
    $customerCompany= cleanInput($input['customer_company'] ?? '');
    $customerPhone  = cleanInput($input['customer_phone'] ?? '');
    $productSummary = cleanInput($input['product_summary'] ?? '');
    $totalAmount    = floatval($input['total_amount'] ?? 0);
    $dueDate        = $input['due_date'] ?? null;
    $productionNote = cleanInput($input['production_note'] ?? '');
    $assignedTo     = cleanInput($input['assigned_to'] ?? '');

    if (!$customerName) { errorResponse('客戶姓名不能為空'); return; }

    try {
        $db = getDB();
        $db->beginTransaction();

        if ($id) {
            // 更新
            $stmt = $db->prepare("
                UPDATE work_orders SET
                    customer_name=?, customer_company=?, customer_phone=?,
                    product_summary=?, total_amount=?, due_date=?,
                    production_note=?, assigned_to=?, updated_at=NOW()
                WHERE id=?
            ");
            $stmt->execute([$customerName, $customerCompany, $customerPhone,
                            $productSummary, $totalAmount, $dueDate,
                            $productionNote, $assignedTo, $id]);

            writeLog($db, 'work_order', $id, 'update', null, null, '編輯工單資訊', $username);
        } else {
            // 新增
            $workOrderNumber = generateDocumentNumber($db, 'work_order');
            $stmt = $db->prepare("
                INSERT INTO work_orders
                    (work_order_number, customer_name, customer_company, customer_phone,
                     product_summary, total_amount, due_date, production_note,
                     assigned_to, production_status, created_by, created_at, updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,'pending',?,NOW(),NOW())
            ");
            $stmt->execute([$workOrderNumber, $customerName, $customerCompany, $customerPhone,
                            $productSummary, $totalAmount, $dueDate, $productionNote,
                            $assignedTo, $userId]);

            $id = $db->lastInsertId();
            writeLog($db, 'work_order', $id, 'create', null, 'pending', '手動建立工單', $username);
        }

        $db->commit();
        successResponse(['id' => $id], $id ? '工單已更新' : '工單已建立');
    } catch (Exception $e) {
        $db->rollBack();
        errorResponse('儲存工單失敗: ' . $e->getMessage());
    }
}

// ============================================
// 核心功能：報價單 → 轉工單
// ============================================
function handleConvertFromQuote($userId, $username) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { errorResponse('僅支援 POST'); return; }

    $input       = json_decode(file_get_contents('php://input'), true);
    $quotationId = intval($input['quotation_id'] ?? 0);
    $dueDate     = $input['due_date'] ?? null;
    $productionNote = cleanInput($input['production_note'] ?? '');
    $assignedTo  = cleanInput($input['assigned_to'] ?? '');

    if (!$quotationId) { errorResponse('缺少報價單 ID'); return; }

    try {
        $db = getDB();

        // 確認報價單存在
        $stmt = $db->prepare("SELECT * FROM quotations WHERE id=?");
        $stmt->execute([$quotationId]);
        $quote = $stmt->fetch();
        if (!$quote) { errorResponse('報價單不存在'); return; }

        // 防止重複轉換
        $stmt = $db->prepare("SELECT id FROM work_orders WHERE quotation_id=? LIMIT 1");
        $stmt->execute([$quotationId]);
        if ($stmt->fetch()) { errorResponse('此報價單已轉換為工單，請勿重複操作'); return; }

        $db->beginTransaction();

        // 自動帶入品項摘要
        $stmt = $db->prepare("SELECT item_name, quantity, unit FROM quotation_items WHERE quotation_id=? ORDER BY item_order");
        $stmt->execute([$quotationId]);
        $items = $stmt->fetchAll();
        $summaryParts = array_map(fn($i) => "{$i['item_name']} x{$i['quantity']}{$i['unit']}", $items);
        $productSummary = implode('、', $summaryParts);

        // 建立工單
        $workOrderNumber = generateDocumentNumber($db, 'work_order');
        $stmt = $db->prepare("
            INSERT INTO work_orders
                (quotation_id, work_order_number, customer_name, customer_company,
                 customer_phone, product_summary, total_amount, due_date,
                 production_note, assigned_to, production_status, created_by, created_at, updated_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,'pending',?,NOW(),NOW())
        ");
        $stmt->execute([
            $quotationId, $workOrderNumber,
            $quote['customer_name'], $quote['customer_company'] ?? '',
            $quote['customer_phone'] ?? '', $productSummary,
            $quote['total_amount'], $dueDate,
            $productionNote, $assignedTo, $userId
        ]);
        $workOrderId = $db->lastInsertId();

        // 更新報價單狀態為已轉換
        $stmt = $db->prepare("UPDATE quotations SET status='accepted', converted_to_work_order=? WHERE id=?");
        $stmt->execute([$workOrderId, $quotationId]);

        // 寫流程紀錄（報價單）
        writeLog($db, 'quotation', $quotationId, 'convert', 'draft', 'accepted',
                 "轉換為工單 {$workOrderNumber}", $username);

        // 寫流程紀錄（工單）
        writeLog($db, 'work_order', $workOrderId, 'create', null, 'pending',
                 "由報價單 {$quote['quotation_number']} 轉入", $username);

        $db->commit();
        successResponse(
            ['work_order_id' => $workOrderId, 'work_order_number' => $workOrderNumber],
            "已成功轉換為工單 {$workOrderNumber}"
        );
    } catch (Exception $e) {
        $db->rollBack();
        errorResponse('轉換工單失敗: ' . $e->getMessage());
    }
}

// ============================================
// 更新工單狀態（pending / in_progress / completed / paused）
// ============================================
function handleUpdateStatus($userId, $username) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { errorResponse('僅支援 POST'); return; }

    $input  = json_decode(file_get_contents('php://input'), true);
    $id     = intval($input['id'] ?? 0);
    $status = $input['status'] ?? '';
    $note   = cleanInput($input['note'] ?? '');

    $validStatuses = ['pending', 'in_progress', 'completed', 'paused'];
    if (!$id || !in_array($status, $validStatuses)) {
        errorResponse('參數錯誤'); return;
    }

    try {
        $db = getDB();

        $stmt = $db->prepare("SELECT production_status FROM work_orders WHERE id=?");
        $stmt->execute([$id]);
        $wo = $stmt->fetch();
        if (!$wo) { errorResponse('工單不存在'); return; }

        $oldStatus = $wo['production_status'];

        // 特殊時間欄位
        $timeField = '';
        if ($status === 'in_progress' && $oldStatus === 'pending') {
            $timeField = ', started_at = NOW()';
        } elseif ($status === 'completed') {
            $timeField = ', completed_at = NOW()';
        }

        $stmt = $db->prepare("UPDATE work_orders SET production_status=? {$timeField}, updated_at=NOW() WHERE id=?");
        $stmt->execute([$status, $id]);

        $statusLabels = ['pending'=>'待製作','in_progress'=>'製作中','completed'=>'已完工','paused'=>'暫停'];
        $logNote = "狀態更新：{$statusLabels[$oldStatus]} → {$statusLabels[$status]}";
        if ($note) $logNote .= "（{$note}）";
        writeLog($db, 'work_order', $id, 'update_status', $oldStatus, $status, $logNote, $username);

        successResponse(['id' => $id, 'status' => $status], "已更新為「{$statusLabels[$status]}」");
    } catch (Exception $e) {
        errorResponse('更新狀態失敗: ' . $e->getMessage());
    }
}

// ============================================
// 刪除工單
// ============================================
function handleDelete($userId, $username) {
    $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
    if (!$id) { errorResponse('缺少工單 ID'); return; }

    try {
        $db = getDB();

        // 如果有來源報價單，要把報價單的 converted_to_work_order 清掉
        $stmt = $db->prepare("SELECT quotation_id, work_order_number FROM work_orders WHERE id=?");
        $stmt->execute([$id]);
        $wo = $stmt->fetch();

        if ($wo && $wo['quotation_id']) {
            $db->prepare("UPDATE quotations SET converted_to_work_order=NULL, status='draft' WHERE id=?")
               ->execute([$wo['quotation_id']]);
        }

        writeLog($db, 'work_order', $id, 'delete', null, null,
                 "刪除工單 {$wo['work_order_number']}", $username);

        $db->prepare("DELETE FROM work_orders WHERE id=?")->execute([$id]);

        successResponse([], '工單已刪除');
    } catch (Exception $e) {
        errorResponse('刪除失敗: ' . $e->getMessage());
    }
}

// ============================================
// 取得流程紀錄
// ============================================
function handleGetLogs() {
    $docType = $_GET['doc_type'] ?? 'work_order';
    $docId   = intval($_GET['doc_id'] ?? 0);
    if (!$docId) { errorResponse('缺少文件 ID'); return; }

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM job_logs WHERE doc_type=? AND doc_id=? ORDER BY created_at DESC");
        $stmt->execute([$docType, $docId]);
        jsonResponse(['logs' => $stmt->fetchAll()]);
    } catch (Exception $e) {
        errorResponse('取得紀錄失敗');
    }
}

// ============================================
// 輔助：寫流程紀錄
// ============================================
function writeLog($db, $docType, $docId, $actionType, $oldStatus, $newStatus, $note, $createdBy) {
    $stmt = $db->prepare("
        INSERT INTO job_logs (doc_type, doc_id, action_type, old_status, new_status, note, created_by)
        VALUES (?,?,?,?,?,?,?)
    ");
    $stmt->execute([$docType, $docId, $actionType, $oldStatus, $newStatus, $note, $createdBy]);
}

// ============================================
// 輔助：產生單據編號（與 quotations.php 相同邏輯）
// ============================================
function generateDocumentNumber($db, $docType) {
    $prefixes = ['quotation'=>'Q','work_order'=>'W','delivery'=>'D','invoice'=>'I'];
    $prefix   = $prefixes[$docType] ?? 'X';
    $year     = date('Y');
    $date     = date('md');
    $dateKey  = date('Ymd');

    $stmt = $db->prepare("INSERT INTO document_counters (doc_type, date_key, counter) VALUES (?,?,1)
                          ON DUPLICATE KEY UPDATE counter = counter + 1");
    $stmt->execute([$docType, $dateKey]);

    $stmt = $db->prepare("SELECT counter FROM document_counters WHERE doc_type=? AND date_key=?");
    $stmt->execute([$docType, $dateKey]);
    $counter = str_pad($stmt->fetchColumn(), 3, '0', STR_PAD_LEFT);

    return "{$prefix}-{$year}-{$date}-{$counter}";
}
?>
