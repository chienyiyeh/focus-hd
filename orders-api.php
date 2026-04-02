<?php
/**
 * 訂單管理 API - REST API 版本
 * 使用 WooCommerce REST API 讀取訂單資料
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ============================================
// WooCommerce API 設定
// ============================================

define('WC_API_URL', 'https://apple-print.com.tw/wp-json/wc/v3');
define('WC_CONSUMER_KEY', 'ck_5b38723689f7c4a2766119f4c35c99c21310dfaf');
define('WC_CONSUMER_SECRET', 'cs_89ce3ccc538ab5de84ecf930c73aec50ef355f57');

// ============================================
// 取得請求參數
// ============================================

$action = $_GET['action'] ?? 'list';
$period = $_GET['period'] ?? 'all';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// ============================================
// WooCommerce API 請求函數
// ============================================

function wcRequest($endpoint, $params = []) {
    $url = WC_API_URL . $endpoint;
    
    // 加上認證參數
    $params['consumer_key'] = WC_CONSUMER_KEY;
    $params['consumer_secret'] = WC_CONSUMER_SECRET;
    
    // 建立完整 URL
    $fullUrl = $url . '?' . http_build_query($params);
    
    // 發送請求
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 30,
            'ignore_errors' => true
        ]
    ]);
    
    $response = @file_get_contents($fullUrl, false, $context);
    
    if ($response === false) {
        throw new Exception('無法連線到 WordPress');
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('API 回應格式錯誤');
    }
    
    return $data;
}

// ============================================
// 時間過濾
// ============================================

function getDateParams($period) {
    $params = [];
    
    switch($period) {
        case 'month':
            $params['after'] = date('c', strtotime('-1 month'));
            break;
        case 'quarter':
            $params['after'] = date('c', strtotime('-3 months'));
            break;
        case 'year':
            $params['after'] = date('c', strtotime('-1 year'));
            break;
    }
    
    return $params;
}

// ============================================
// API 路由
// ============================================

try {
    switch($action) {
        
        // ========================================
        // 1. 訂單列表
        // ========================================
        case 'list':
            $params = getDateParams($period);
            $params['per_page'] = $limit;
            $params['page'] = $page;
            $params['orderby'] = 'date';
            $params['order'] = 'desc';
            
            $orders = wcRequest('/orders', $params);
            
            // 轉換格式
            $formattedOrders = [];
            foreach ($orders as $order) {
                $formattedOrders[] = [
                    'order_id' => $order['id'],
                    'order_number' => $order['number'],
                    'order_date' => $order['date_created'],
                    'order_status' => 'wc-' . $order['status'],
                    'total' => (float)$order['total'],
                    'first_name' => $order['billing']['first_name'] ?? '',
                    'last_name' => $order['billing']['last_name'] ?? '',
                    'customer_name' => trim(($order['billing']['first_name'] ?? '') . ' ' . ($order['billing']['last_name'] ?? '')),
                    'company' => $order['billing']['company'] ?? '',
                    'customer_type' => !empty($order['billing']['company']) ? 'business' : 'personal',
                    'email' => $order['billing']['email'] ?? '',
                    'phone' => $order['billing']['phone'] ?? '',
                    'items' => array_map(function($item) {
                        return [
                            'product_name' => $item['name'],
                            'quantity' => $item['quantity'],
                            'total' => (float)$item['total']
                        ];
                    }, $order['line_items'])
                ];
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'orders' => $formattedOrders,
                    'total' => count($formattedOrders),
                    'page' => $page,
                    'limit' => $limit
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;
        
        // ========================================
        // 2. 統計分析
        // ========================================
        case 'stats':
            $params = getDateParams($period);
            $params['per_page'] = 100; // 一次拉 100 筆來分析
            
            $allOrders = [];
            $currentPage = 1;
            
            // 分頁拉取所有訂單（最多 10 頁，1000 筆）
            while ($currentPage <= 10) {
                $params['page'] = $currentPage;
                $orders = wcRequest('/orders', $params);
                
                if (empty($orders)) break;
                
                $allOrders = array_merge($allOrders, $orders);
                
                if (count($orders) < 100) break; // 最後一頁
                $currentPage++;
            }
            
            // 統計分析
            $totalOrders = count($allOrders);
            $totalRevenue = 0;
            $customerTypes = [
                'business' => ['count' => 0, 'revenue' => 0],
                'personal' => ['count' => 0, 'revenue' => 0]
            ];
            $emails = [];
            
            foreach ($allOrders as $order) {
                $total = (float)$order['total'];
                $totalRevenue += $total;
                
                // 客戶類型
                $type = !empty($order['billing']['company']) ? 'business' : 'personal';
                $customerTypes[$type]['count']++;
                $customerTypes[$type]['revenue'] += $total;
                
                // 統計 Email（重複客戶）
                $email = $order['billing']['email'] ?? '';
                if ($email) {
                    if (!isset($emails[$email])) {
                        $emails[$email] = ['count' => 0, 'revenue' => 0];
                    }
                    $emails[$email]['count']++;
                    $emails[$email]['revenue'] += $total;
                }
            }
            
            $repeatCustomers = array_filter($emails, function($data) {
                return $data['count'] > 1;
            });
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'total_orders' => $totalOrders,
                    'total_revenue' => $totalRevenue,
                    'customer_types' => $customerTypes,
                    'total_customers' => count($emails),
                    'repeat_customers' => count($repeatCustomers),
                    'repeat_rate' => count($emails) > 0 ? round(count($repeatCustomers) / count($emails) * 100, 2) : 0
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;
        
        // ========================================
        // 3. 產品排行
        // ========================================
        case 'products':
            $params = getDateParams($period);
            $params['per_page'] = 100;
            
            $allOrders = [];
            $currentPage = 1;
            
            // 拉取訂單
            while ($currentPage <= 10) {
                $params['page'] = $currentPage;
                $orders = wcRequest('/orders', $params);
                
                if (empty($orders)) break;
                
                $allOrders = array_merge($allOrders, $orders);
                
                if (count($orders) < 100) break;
                $currentPage++;
            }
            
            // 統計產品
            $products = [];
            
            foreach ($allOrders as $order) {
                foreach ($order['line_items'] as $item) {
                    $name = $item['name'];
                    
                    if (!isset($products[$name])) {
                        $products[$name] = [
                            'product_name' => $name,
                            'quantity' => 0,
                            'revenue' => 0
                        ];
                    }
                    
                    $products[$name]['quantity'] += $item['quantity'];
                    $products[$name]['revenue'] += (float)$item['total'];
                }
            }
            
            // 排序（依營收）
            usort($products, function($a, $b) {
                return $b['revenue'] <=> $a['revenue'];
            });
            
            // 取前 20 名
            $products = array_slice($products, 0, 20);
            
            echo json_encode([
                'success' => true,
                'data' => $products
            ], JSON_UNESCAPED_UNICODE);
            break;
        
        // ========================================
        // 4. 單筆訂單詳情
        // ========================================
        case 'detail':
            $orderId = $_GET['order_id'] ?? null;
            
            if (!$orderId) {
                throw new Exception('缺少訂單 ID');
            }
            
            $order = wcRequest('/orders/' . $orderId);
            
            if (isset($order['code'])) {
                throw new Exception($order['message'] ?? '訂單不存在');
            }
            
            $formattedOrder = [
                'order_id' => $order['id'],
                'order_number' => $order['number'],
                'order_date' => $order['date_created'],
                'order_status' => 'wc-' . $order['status'],
                'total' => (float)$order['total'],
                'first_name' => $order['billing']['first_name'] ?? '',
                'last_name' => $order['billing']['last_name'] ?? '',
                'customer_name' => trim(($order['billing']['first_name'] ?? '') . ' ' . ($order['billing']['last_name'] ?? '')),
                'company' => $order['billing']['company'] ?? '',
                'customer_type' => !empty($order['billing']['company']) ? 'business' : 'personal',
                'email' => $order['billing']['email'] ?? '',
                'phone' => $order['billing']['phone'] ?? '',
                'address' => $order['billing']['address_1'] ?? '',
                'city' => $order['billing']['city'] ?? '',
                'customer_note' => $order['customer_note'] ?? '',
                'items' => array_map(function($item) {
                    return [
                        'product_name' => $item['name'],
                        'quantity' => $item['quantity'],
                        'total' => (float)$item['total']
                    ];
                }, $order['line_items'])
            ];
            
            echo json_encode([
                'success' => true,
                'data' => $formattedOrder
            ], JSON_UNESCAPED_UNICODE);
            break;
        
        default:
            throw new Exception('無效的 action 參數');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
