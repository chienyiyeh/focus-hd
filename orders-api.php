<?php
/**
 * 訂單管理 API (跨資料庫版本)
 * 使用看板系統的資料庫帳號，跨資料庫查詢 WordPress
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ============================================
// 使用看板系統的資料庫帳號（有權限讀取所有資料庫）
// ============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'zeyjsvrczr');      // 看板系統帳號
define('DB_PASS', 'nrPBsleknr');      // 看板系統密碼

// 資料庫名稱
define('WP_DB', 'jdrnmynced');        // WordPress 資料庫
define('KANBAN_DB', 'zeyjsvrczr');    // 看板資料庫

// ============================================
// 資料庫連線
// ============================================

try {
    // 使用看板系統帳號連線（不指定資料庫）
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => '資料庫連線失敗',
        'message' => $e->getMessage()
    ]);
    exit;
}

// ============================================
// 取得請求參數
// ============================================

$action = $_GET['action'] ?? 'list';
$period = $_GET['period'] ?? 'all';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

// ============================================
// 時間過濾
// ============================================

function getDateFilter($period) {
    $where = "";
    switch($period) {
        case 'month':
            $where = "AND p.post_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
            break;
        case 'quarter':
            $where = "AND p.post_date >= DATE_SUB(NOW(), INTERVAL 3 MONTH)";
            break;
        case 'year':
            $where = "AND p.post_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
            break;
        case 'all':
        default:
            $where = "";
    }
    return $where;
}

// ============================================
// API 路由
// ============================================

switch($action) {
    
    // ========================================
    // 1. 訂單列表
    // ========================================
    case 'list':
        try {
            $dateFilter = getDateFilter($period);
            
            // 使用完整資料庫名稱：jdrnmynced.wp_posts
            $sql = "
                SELECT 
                    p.ID as order_id,
                    p.post_date as order_date,
                    p.post_status as order_status,
                    MAX(CASE WHEN pm.meta_key = '_order_total' THEN pm.meta_value END) as total,
                    MAX(CASE WHEN pm.meta_key = '_billing_first_name' THEN pm.meta_value END) as first_name,
                    MAX(CASE WHEN pm.meta_key = '_billing_last_name' THEN pm.meta_value END) as last_name,
                    MAX(CASE WHEN pm.meta_key = '_billing_company' THEN pm.meta_value END) as company,
                    MAX(CASE WHEN pm.meta_key = '_billing_email' THEN pm.meta_value END) as email,
                    MAX(CASE WHEN pm.meta_key = '_billing_phone' THEN pm.meta_value END) as phone
                FROM " . WP_DB . ".wp_posts p
                LEFT JOIN " . WP_DB . ".wp_postmeta pm ON p.ID = pm.post_id
                WHERE p.post_type = 'shop_order'
                $dateFilter
                GROUP BY p.ID
                ORDER BY p.post_date DESC
                LIMIT :limit OFFSET :offset
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $orders = $stmt->fetchAll();
            
            // 整理訂單資料
            foreach($orders as &$order) {
                $order['customer_name'] = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
                $order['customer_type'] = !empty($order['company']) ? 'business' : 'personal';
                $order['total'] = (float)($order['total'] ?? 0);
                
                // 取得訂單項目
                $order['items'] = getOrderItems($pdo, $order['order_id']);
            }
            
            // 取得總數
            $countSql = "SELECT COUNT(DISTINCT p.ID) as total FROM " . WP_DB . ".wp_posts p WHERE p.post_type = 'shop_order' $dateFilter";
            $total = $pdo->query($countSql)->fetch()['total'];
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'orders' => $orders,
                    'total' => (int)$total,
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ], JSON_UNESCAPED_UNICODE);
            
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;
    
    // ========================================
    // 2. 統計分析
    // ========================================
    case 'stats':
        try {
            $dateFilter = getDateFilter($period);
            
            // 總訂單數和金額
            $statsSql = "
                SELECT 
                    COUNT(DISTINCT p.ID) as total_orders,
                    SUM(CAST(pm.meta_value AS DECIMAL(10,2))) as total_revenue
                FROM " . WP_DB . ".wp_posts p
                LEFT JOIN " . WP_DB . ".wp_postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_order_total'
                WHERE p.post_type = 'shop_order'
                $dateFilter
            ";
            $stats = $pdo->query($statsSql)->fetch();
            
            // 法人 vs 個人
            $customerTypeSql = "
                SELECT 
                    CASE 
                        WHEN MAX(CASE WHEN pm.meta_key = '_billing_company' THEN pm.meta_value END) != '' 
                        THEN 'business' 
                        ELSE 'personal' 
                    END as customer_type,
                    COUNT(*) as count,
                    SUM(CAST(MAX(CASE WHEN pm.meta_key = '_order_total' THEN pm.meta_value END) AS DECIMAL(10,2))) as revenue
                FROM " . WP_DB . ".wp_posts p
                LEFT JOIN " . WP_DB . ".wp_postmeta pm ON p.ID = pm.post_id
                WHERE p.post_type = 'shop_order'
                $dateFilter
                GROUP BY p.ID
            ";
            $customerTypeData = $pdo->query($customerTypeSql)->fetchAll();
            
            $customerTypes = [
                'business' => ['count' => 0, 'revenue' => 0],
                'personal' => ['count' => 0, 'revenue' => 0]
            ];
            
            foreach($customerTypeData as $row) {
                $type = $row['customer_type'];
                $customerTypes[$type]['count']++;
                $customerTypes[$type]['revenue'] += (float)$row['revenue'];
            }
            
            // 重複客戶分析
            $repeatCustomerSql = "
                SELECT 
                    MAX(CASE WHEN pm.meta_key = '_billing_email' THEN pm.meta_value END) as email,
                    COUNT(*) as order_count,
                    SUM(CAST(MAX(CASE WHEN pm.meta_key = '_order_total' THEN pm.meta_value END) AS DECIMAL(10,2))) as total_spent
                FROM " . WP_DB . ".wp_posts p
                LEFT JOIN " . WP_DB . ".wp_postmeta pm ON p.ID = pm.post_id
                WHERE p.post_type = 'shop_order'
                $dateFilter
                GROUP BY p.ID
            ";
            $customerData = $pdo->query($repeatCustomerSql)->fetchAll();
            
            $emailStats = [];
            foreach($customerData as $row) {
                $email = $row['email'];
                if(!isset($emailStats[$email])) {
                    $emailStats[$email] = ['count' => 0, 'revenue' => 0];
                }
                $emailStats[$email]['count']++;
                $emailStats[$email]['revenue'] += (float)$row['total_spent'];
            }
            
            $repeatCustomers = array_filter($emailStats, function($data) {
                return $data['count'] > 1;
            });
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'total_orders' => (int)$stats['total_orders'],
                    'total_revenue' => (float)$stats['total_revenue'],
                    'customer_types' => $customerTypes,
                    'total_customers' => count($emailStats),
                    'repeat_customers' => count($repeatCustomers),
                    'repeat_rate' => count($emailStats) > 0 ? round(count($repeatCustomers) / count($emailStats) * 100, 2) : 0
                ]
            ], JSON_UNESCAPED_UNICODE);
            
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;
    
    // ========================================
    // 3. 產品排行
    // ========================================
    case 'products':
        try {
            $dateFilter = getDateFilter($period);
            
            $sql = "
                SELECT 
                    oi.order_item_name as product_name,
                    SUM(CAST(oim_qty.meta_value AS UNSIGNED)) as quantity,
                    SUM(CAST(oim_total.meta_value AS DECIMAL(10,2))) as revenue
                FROM " . WP_DB . ".wp_posts p
                INNER JOIN " . WP_DB . ".wp_woocommerce_order_items oi ON p.ID = oi.order_id
                LEFT JOIN " . WP_DB . ".wp_woocommerce_order_itemmeta oim_qty ON oi.order_item_id = oim_qty.order_item_id AND oim_qty.meta_key = '_qty'
                LEFT JOIN " . WP_DB . ".wp_woocommerce_order_itemmeta oim_total ON oi.order_item_id = oim_total.order_item_id AND oim_total.meta_key = '_line_total'
                WHERE p.post_type = 'shop_order'
                AND oi.order_item_type = 'line_item'
                $dateFilter
                GROUP BY oi.order_item_name
                ORDER BY revenue DESC
                LIMIT 20
            ";
            
            $products = $pdo->query($sql)->fetchAll();
            
            foreach($products as &$product) {
                $product['quantity'] = (int)$product['quantity'];
                $product['revenue'] = (float)$product['revenue'];
            }
            
            echo json_encode([
                'success' => true,
                'data' => $products
            ], JSON_UNESCAPED_UNICODE);
            
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;
    
    // ========================================
    // 4. 單筆訂單詳情
    // ========================================
    case 'detail':
        try {
            $orderId = $_GET['order_id'] ?? null;
            
            if(!$orderId) {
                throw new Exception('缺少訂單 ID');
            }
            
            $sql = "
                SELECT 
                    p.ID as order_id,
                    p.post_date as order_date,
                    p.post_status as order_status,
                    MAX(CASE WHEN pm.meta_key = '_order_total' THEN pm.meta_value END) as total,
                    MAX(CASE WHEN pm.meta_key = '_billing_first_name' THEN pm.meta_value END) as first_name,
                    MAX(CASE WHEN pm.meta_key = '_billing_last_name' THEN pm.meta_value END) as last_name,
                    MAX(CASE WHEN pm.meta_key = '_billing_company' THEN pm.meta_value END) as company,
                    MAX(CASE WHEN pm.meta_key = '_billing_email' THEN pm.meta_value END) as email,
                    MAX(CASE WHEN pm.meta_key = '_billing_phone' THEN pm.meta_value END) as phone,
                    MAX(CASE WHEN pm.meta_key = '_billing_address_1' THEN pm.meta_value END) as address,
                    MAX(CASE WHEN pm.meta_key = '_billing_city' THEN pm.meta_value END) as city,
                    MAX(CASE WHEN pm.meta_key = '_customer_note' THEN pm.meta_value END) as customer_note
                FROM " . WP_DB . ".wp_posts p
                LEFT JOIN " . WP_DB . ".wp_postmeta pm ON p.ID = pm.post_id
                WHERE p.ID = :order_id
                AND p.post_type = 'shop_order'
                GROUP BY p.ID
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':order_id' => $orderId]);
            $order = $stmt->fetch();
            
            if(!$order) {
                throw new Exception('訂單不存在');
            }
            
            $order['customer_name'] = trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? ''));
            $order['customer_type'] = !empty($order['company']) ? 'business' : 'personal';
            $order['total'] = (float)($order['total'] ?? 0);
            $order['items'] = getOrderItems($pdo, $orderId);
            
            echo json_encode([
                'success' => true,
                'data' => $order
            ], JSON_UNESCAPED_UNICODE);
            
        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        break;
    
    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => '無效的 action 參數'
        ]);
}

// ============================================
// 輔助函數
// ============================================

/**
 * 取得訂單項目
 */
function getOrderItems($pdo, $orderId) {
    $sql = "
        SELECT 
            oi.order_item_name as product_name,
            MAX(CASE WHEN oim.meta_key = '_qty' THEN oim.meta_value END) as quantity,
            MAX(CASE WHEN oim.meta_key = '_line_total' THEN oim.meta_value END) as total
        FROM " . WP_DB . ".wp_woocommerce_order_items oi
        LEFT JOIN " . WP_DB . ".wp_woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
        WHERE oi.order_id = :order_id
        AND oi.order_item_type = 'line_item'
        GROUP BY oi.order_item_id
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':order_id' => $orderId]);
    $items = $stmt->fetchAll();
    
    foreach($items as &$item) {
        $item['quantity'] = (int)($item['quantity'] ?? 0);
        $item['total'] = (float)($item['total'] ?? 0);
    }
    
    return $items;
}
