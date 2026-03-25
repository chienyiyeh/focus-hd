<?php
/**
 * 預覽系統專用 API
 * 不需要登入，返回示例或真實資料
 */

// CORS 設定
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../config.php';

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        getQuotations();
        break;
    case 'get':
        getQuotation();
        break;
    default:
        echo json_encode(['success' => false, 'message' => '無效的操作'], JSON_UNESCAPED_UNICODE);
}

function getQuotations() {
    try {
        $db = getDB();
        
        // 嘗試取得真實資料
        $sql = "SELECT q.*, COUNT(qi.id) as item_count
                FROM quotations q
                LEFT JOIN quotation_items qi ON q.id = qi.quotation_id
                GROUP BY q.id
                ORDER BY q.created_at DESC
                LIMIT 10";
        
        $stmt = $db->query($sql);
        $quotations = $stmt->fetchAll();
        
        // 如果沒有資料，返回示例資料
        if (empty($quotations)) {
            $quotations = getSampleQuotations();
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'quotations' => $quotations
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        // 如果資料庫錯誤，返回示例資料
        echo json_encode([
            'success' => true,
            'data' => [
                'quotations' => getSampleQuotations()
            ]
        ], JSON_UNESCAPED_UNICODE);
    }
}

function getQuotation() {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        echo json_encode(['success' => false, 'message' => '缺少報價單 ID'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    try {
        $db = getDB();
        
        $stmt = $db->prepare("SELECT * FROM quotations WHERE id = ?");
        $stmt->execute([$id]);
        $quotation = $stmt->fetch();
        
        if (!$quotation) {
            echo json_encode(['success' => false, 'message' => '報價單不存在'], JSON_UNESCAPED_UNICODE);
            return;
        }
        
        $stmt = $db->prepare("SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY item_order");
        $stmt->execute([$id]);
        $items = $stmt->fetchAll();
        
        $quotation['items'] = $items;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'quotation' => $quotation
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '取得報價單失敗'], JSON_UNESCAPED_UNICODE);
    }
}

function getSampleQuotations() {
    return [
        [
            'id' => 1,
            'quotation_number' => 'Q-2026-0324-001',
            'customer_name' => '王小姐',
            'customer_company' => 'ABC 貿易股份有限公司',
            'customer_phone' => '0912-345-678',
            'customer_email' => 'wang@abc.com.tw',
            'quote_date' => '2026-03-24',
            'valid_days' => 7,
            'subtotal' => 5000,
            'tax_amount' => 250,
            'total_amount' => 5250,
            'status' => 'draft',
            'item_count' => 2,
            'created_at' => '2026-03-24 10:30:00'
        ],
        [
            'id' => 2,
            'quotation_number' => 'Q-2026-0324-002',
            'customer_name' => '李老闆',
            'customer_company' => '美味餐廳',
            'customer_phone' => '0923-456-789',
            'customer_email' => 'lee@restaurant.com',
            'quote_date' => '2026-03-23',
            'valid_days' => 7,
            'subtotal' => 8000,
            'tax_amount' => 400,
            'total_amount' => 8400,
            'status' => 'sent',
            'item_count' => 3,
            'created_at' => '2026-03-23 14:20:00'
        ],
        [
            'id' => 3,
            'quotation_number' => 'Q-2026-0323-001',
            'customer_name' => '陳總經理',
            'customer_company' => '科技公司',
            'customer_phone' => '0934-567-890',
            'customer_email' => 'chen@tech.com.tw',
            'quote_date' => '2026-03-22',
            'valid_days' => 7,
            'subtotal' => 12000,
            'tax_amount' => 600,
            'total_amount' => 12600,
            'status' => 'draft',
            'item_count' => 5,
            'created_at' => '2026-03-22 16:45:00'
        ]
    ];
}
?>
