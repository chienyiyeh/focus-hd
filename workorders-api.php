<?php
/**
 * 工單管理 API
 * 支援：建立工單、查詢工單、更新狀態、生成出貨單/發票
 */

session_start();

// 載入設定檔
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    // 如果沒有 config.php，使用預設連線資訊
    $db_host = 'localhost';
    $db_name = 'zeyjsvrczr';
    $db_user = 'zeyjsvrczr';
    $db_pass = 'nrPBsleknr';
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 驗證登入
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'error' => '未登入'], JSON_UNESCAPED_UNICODE);
    exit;
}

$username = $_SESSION['username'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    switch ($action) {
        // ========================================
        // 建立工單（從訂單）
        // ========================================
        case 'create_from_order':
            $orderData = json_decode(file_get_contents('php://input'), true);
            
            // 生成工單編號 WO-2604-001
            $yearMonth = date('ym'); // 2604
            
            // 檢查本月序號
            $stmt = $pdo->prepare("SELECT current_number FROM wo_monthly_sequence WHERE year_month = ?");
            $stmt->execute([$yearMonth]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $nextNumber = $row['current_number'] + 1;
                $stmt = $pdo->prepare("UPDATE wo_monthly_sequence SET current_number = ? WHERE year_month = ?");
                $stmt->execute([$nextNumber, $yearMonth]);
            } else {
                $nextNumber = 1;
                $stmt = $pdo->prepare("INSERT INTO wo_monthly_sequence (year_month, current_number) VALUES (?, ?)");
                $stmt->execute([$yearMonth, $nextNumber]);
            }
            
            $woNumber = sprintf('WO-%s-%03d', $yearMonth, $nextNumber);
            
            // 提取商品規格和檔案
            $productSpecs = [];
            $fileUrls = [];
            
            if (!empty($orderData['items'][0]['specs'])) {
                foreach ($orderData['items'][0]['specs'] as $spec) {
                    if ($spec['is_file']) {
                        $fileUrls[] = [
                            'label' => $spec['label'],
                            'url' => $spec['file_url'],
                            'filename' => $spec['value']
                        ];
                    } else {
                        $productSpecs[] = [
                            'label' => $spec['label'],
                            'value' => $spec['value']
                        ];
                    }
                }
            }
            
            // 計算交期（如果規格中有交期選擇）
            $deadline = null;
            foreach ($productSpecs as $spec) {
                if (strpos($spec['label'], '交期') !== false) {
                    if (strpos($spec['value'], '明日') !== false) {
                        $deadline = date('Y-m-d', strtotime('+1 day'));
                    } elseif (preg_match('/(\d+)天/', $spec['value'], $matches)) {
                        $deadline = date('Y-m-d', strtotime('+' . $matches[1] . ' days'));
                    }
                }
            }
            
            // 建立工單
            $stmt = $pdo->prepare("
                INSERT INTO work_orders (
                    wo_number, order_id, order_number,
                    customer_name, company, phone, email, address,
                    product_name, product_specs, file_urls,
                    quantity, unit_price, total_amount,
                    status, priority, deadline,
                    invoice_need, invoice_company, invoice_vat_number,
                    created_by
                ) VALUES (
                    ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    'pending', 'normal', ?,
                    ?, ?, ?,
                    ?
                )
            ");
            
            $stmt->execute([
                $woNumber,
                $orderData['order_id'],
                $orderData['order_number'] ?? '#' . $orderData['order_id'],
                $orderData['customer_name'],
                $orderData['company'] ?? null,
                $orderData['phone'] ?? null,
                $orderData['email'] ?? null,
                $orderData['address'] ?? null,
                $orderData['items'][0]['product_name'] ?? '訂單商品',
                json_encode($productSpecs, JSON_UNESCAPED_UNICODE),
                json_encode($fileUrls, JSON_UNESCAPED_UNICODE),
                $orderData['items'][0]['quantity'] ?? 1,
                $orderData['items'][0]['total'] ?? 0,
                $orderData['total'],
                $deadline,
                $orderData['invoice_need'] ?? false,
                $orderData['invoice_company'] ?? null,
                $orderData['invoice_vat_number'] ?? null,
                $username
            ]);
            
            $woId = $pdo->lastInsertId();
            
            // 記錄歷程
            $stmt = $pdo->prepare("
                INSERT INTO work_order_logs (wo_id, action, new_status, created_by)
                VALUES (?, 'created', 'pending', ?)
            ");
            $stmt->execute([$woId, $username]);
            
            echo json_encode([
                'success' => true,
                'wo_number' => $woNumber,
                'wo_id' => $woId
            ], JSON_UNESCAPED_UNICODE);
            break;
        
        // ========================================
        // 查詢工單列表
        // ========================================
        case 'list':
            $status = $_GET['status'] ?? 'all';
            $limit = intval($_GET['limit'] ?? 50);
            
            $sql = "SELECT * FROM work_orders WHERE 1=1";
            $params = [];
            
            if ($status !== 'all') {
                $sql .= " AND status = ?";
                $params[] = $status;
            }
            
            // 權限過濾（如果不是 admin，只看自己負責的）
            if (!in_array($username, ['admin', 'chienyi'])) {
                $sql .= " AND assigned_to = ?";
                $params[] = $username;
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 解析 JSON 欄位
            foreach ($orders as &$order) {
                $order['product_specs'] = json_decode($order['product_specs'], true);
                $order['file_urls'] = json_decode($order['file_urls'], true);
            }
            
            echo json_encode([
                'success' => true,
                'data' => $orders
            ], JSON_UNESCAPED_UNICODE);
            break;
        
        // ========================================
        // 查詢單筆工單
        // ========================================
        case 'detail':
            $woId = $_GET['wo_id'] ?? null;
            
            if (!$woId) {
                throw new Exception('缺少工單 ID');
            }
            
            $stmt = $pdo->prepare("SELECT * FROM work_orders WHERE id = ?");
            $stmt->execute([$woId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                throw new Exception('工單不存在');
            }
            
            // 解析 JSON
            $order['product_specs'] = json_decode($order['product_specs'], true);
            $order['file_urls'] = json_decode($order['file_urls'], true);
            
            // 查詢歷程
            $stmt = $pdo->prepare("
                SELECT * FROM work_order_logs 
                WHERE wo_id = ? 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$woId]);
            $order['logs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $order
            ], JSON_UNESCAPED_UNICODE);
            break;
        
        // ========================================
        // 更新工單
        // ========================================
        case 'update':
            $data = json_decode(file_get_contents('php://input'), true);
            $woId = $data['wo_id'];
            
            // 取得舊資料
            $stmt = $pdo->prepare("SELECT * FROM work_orders WHERE id = ?");
            $stmt->execute([$woId]);
            $oldData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 更新欄位
            $updates = [];
            $params = [];
            
            $allowedFields = [
                'status', 'priority', 'assigned_to', 'deadline', 'production_notes',
                'shipping_method', 'shipping_date', 'tracking_number', 'shipping_notes', 'shipped_by',
                'invoice_number', 'invoice_date', 'invoiced_by'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($updates)) {
                throw new Exception('沒有要更新的欄位');
            }
            
            $params[] = $woId;
            $sql = "UPDATE work_orders SET " . implode(', ', $updates) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // 記錄歷程
            if (isset($data['status']) && $data['status'] !== $oldData['status']) {
                $stmt = $pdo->prepare("
                    INSERT INTO work_order_logs (wo_id, action, old_status, new_status, notes, created_by)
                    VALUES (?, 'status_changed', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $woId,
                    $oldData['status'],
                    $data['status'],
                    $data['notes'] ?? null,
                    $username
                ]);
            }
            
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            break;
        
        // ========================================
        // 刪除工單
        // ========================================
        case 'delete':
            $woId = $_POST['wo_id'] ?? null;
            
            if (!in_array($username, ['admin', 'chienyi'])) {
                throw new Exception('無權限刪除工單');
            }
            
            $stmt = $pdo->prepare("DELETE FROM work_orders WHERE id = ?");
            $stmt->execute([$woId]);
            
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            break;
        
        // ========================================
        // 統計資料
        // ========================================
        case 'stats':
            $stats = [];
            
            // 各狀態數量
            $stmt = $pdo->query("
                SELECT status, COUNT(*) as count
                FROM work_orders
                GROUP BY status
            ");
            $statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            $stats['pending'] = $statusCounts['pending'] ?? 0;
            $stats['production'] = $statusCounts['production'] ?? 0;
            $stats['completed'] = $statusCounts['completed'] ?? 0;
            $stats['shipped'] = $statusCounts['shipped'] ?? 0;
            $stats['invoiced'] = $statusCounts['invoiced'] ?? 0;
            $stats['total'] = array_sum($statusCounts);
            
            // 今日新增
            $stmt = $pdo->query("
                SELECT COUNT(*) FROM work_orders 
                WHERE DATE(created_at) = CURDATE()
            ");
            $stats['today_new'] = $stmt->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'data' => $stats
            ], JSON_UNESCAPED_UNICODE);
            break;
        
        default:
            throw new Exception('未知的操作: ' . $action);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
