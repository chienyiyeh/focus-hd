<?php
/**
 * 訂單管理 API - 帶快取版本
 * 統計資料快取 10 分鐘，大幅提升載入速度
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ============================================
// WooCommerce API 設定
// ============================================

define('WC_API_URL', 'https://apple-print.com.tw/wp-json/wc/v3');
define('WC_CONSUMER_KEY', 'ck_5b38723689f7c4a2766119f4c35c99c21310dfaf');
define('WC_CONSUMER_SECRET', 'cs_89ce3ccc538ab5de84ecf930c73aec50ef355f57');

// 快取設定
define('CACHE_DIR', sys_get_temp_dir() . '/orders_cache');
define('CACHE_LIFETIME', 600); // 10 分鐘

// 建立快取目錄
if (!file_exists(CACHE_DIR)) {
    @mkdir(CACHE_DIR, 0755, true);
}

// ============================================
// 快取函數
// ============================================

function getCacheKey($action, $period) {
    return CACHE_DIR . '/' . md5($action . '_' . $period) . '.json';
}

function getCache($action, $period) {
    $cacheFile = getCacheKey($action, $period);
    
    if (file_exists($cacheFile)) {
        $cacheTime = filemtime($cacheFile);
        if (time() - $cacheTime < CACHE_LIFETIME) {
            return json_decode(file_get_contents($cacheFile), true);
        }
    }
    
    return null;
}

function setCache($action, $period, $data) {
    $cacheFile = getCacheKey($action, $period);
    file_put_contents($cacheFile, json_encode($data));
}

// ============================================
// WooCommerce API 請求
// ============================================

function wcRequest($endpoint, $params = []) {
    $url = WC_API_URL . $endpoint;
    
    $params['consumer_key'] = WC_CONSUMER_KEY;
    $params['consumer_secret'] = WC_CONSUMER_SECRET;
    
    $fullUrl = $url . '?' . http_build_query($params);
    
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
    
    return json_decode($response, true);
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
// 參數
// ============================================

$action = $_GET['action'] ?? 'list';
$period = $_GET['period'] ?? 'all';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$nocache = isset($_GET['nocache']); // 強制重新載入

// ============================================
// API 路由
// ============================================

try {
    switch($action) {
        
        // ========================================
        // 訂單列表（不快取，保持即時）
        // ========================================
        case 'list':
            $params = getDateParams($period);
            $params['per_page'] = min($limit, 50); // 限制最多 50 筆
            $params['page'] = $page;
            $params['orderby'] = 'date';
            $params['order'] = 'desc';
            
            $orders = wcRequest('/orders', $params);
            
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
                    'phone' => $order['billing']['phone'] ?? ''
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
        // 統計分析（快取 10 分鐘）
        // ========================================
        case 'stats':
            // 檢查快取
            if (!$nocache) {
                $cached = getCache('stats', $period);
                if ($cached !== null) {
                    $cached['from_cache'] = true;
                    $cached['cache_time'] = date('Y-m-d H:i:s');
                    echo json_encode($cached, JSON_UNESCAPED_UNICODE);
                    break;
                }
            }
            
            // 快取失效，重新計算
            $params = getDateParams($period);
            $params['per_page'] = 100;
            
            $allOrders = [];
            $currentPage = 1;
            
            while ($currentPage <= 5) { // 只拉 5 頁（500 筆）加快速度
                $params['page'] = $currentPage;
                $orders = wcRequest('/orders', $params);
                
                if (empty($orders)) break;
                
                $allOrders = array_merge($allOrders, $orders);
                
                if (count($orders) < 100) break;
                $currentPage++;
            }
            
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
                
                $type = !empty($order['billing']['company']) ? 'business' : 'personal';
                $customerTypes[$type]['count']++;
                $customerTypes[$type]['revenue'] += $total;
                
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
            
            $result = [
                'success' => true,
                'data' => [
                    'total_orders' => $totalOrders,
                    'total_revenue' => $totalRevenue,
                    'customer_types' => $customerTypes,
                    'total_customers' => count($emails),
                    'repeat_customers' => count($repeatCustomers),
                    'repeat_rate' => count($emails) > 0 ? round(count($repeatCustomers) / count($emails) * 100, 2) : 0
                ],
                'from_cache' => false
            ];
            
            // 存入快取
            setCache('stats', $period, $result);
            
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;
        
        // ========================================
        // 產品排行（快取 10 分鐘）
        // ========================================
        case 'products':
            // 檢查快取
            if (!$nocache) {
                $cached = getCache('products', $period);
                if ($cached !== null) {
                    $cached['from_cache'] = true;
                    echo json_encode($cached, JSON_UNESCAPED_UNICODE);
                    break;
                }
            }
            
            $params = getDateParams($period);
            $params['per_page'] = 100;
            
            $allOrders = [];
            $currentPage = 1;
            
            while ($currentPage <= 5) {
                $params['page'] = $currentPage;
                $orders = wcRequest('/orders', $params);
                
                if (empty($orders)) break;
                
                $allOrders = array_merge($allOrders, $orders);
                
                if (count($orders) < 100) break;
                $currentPage++;
            }
            
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
            
            usort($products, function($a, $b) {
                return $b['revenue'] <=> $a['revenue'];
            });
            
            $products = array_slice($products, 0, 20);
            
            $result = [
                'success' => true,
                'data' => $products,
                'from_cache' => false
            ];
            
            setCache('products', $period, $result);
            
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;
        
        // ========================================
        // 單筆訂單詳情（不快取）
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
            
            // 從 meta_data 提取發票資訊
            $invoiceCompany = '';
            $invoiceVatNumber = '';
            $invoiceNeed = '';
            
            foreach ($order['meta_data'] as $meta) {
                if ($meta['key'] === '_billing_company_title') {
                    $invoiceCompany = $meta['value'];
                }
                if ($meta['key'] === '_billing_vat_number') {
                    $invoiceVatNumber = $meta['value'];
                }
                if ($meta['key'] === '_billing_invoice_need') {
                    $invoiceNeed = $meta['value'];
                }
            }
            
            // 處理商品項目，包含完整規格
            $formattedItems = [];
            foreach ($order['line_items'] as $item) {
                $itemData = [
                    'product_name' => $item['name'],
                    'quantity' => $item['quantity'],
                    'total' => (float)$item['total'],
                    'specs' => []
                ];
                
                // 提取商品規格和上傳檔案
                foreach ($item['meta_data'] as $meta) {
                    $key = $meta['display_key'] ?? $meta['key'];
                    $value = $meta['display_value'] ?? $meta['value'];
                    
                    // 跳過內部欄位
                    if (str_starts_with($key, '_') || $key === 'product_extras') {
                        continue;
                    }
                    
                    // 處理檔案連結
                    if (strpos($value, '<a href') !== false) {
                        // 提取檔案 URL
                        preg_match('/href="([^"]+)"/', $value, $matches);
                        if (isset($matches[1])) {
                            $itemData['specs'][] = [
                                'label' => $key,
                                'value' => basename($matches[1]),
                                'file_url' => $matches[1],
                                'is_file' => true
                            ];
                        }
                    } else {
                        // 移除 HTML 標籤和價格標記
                        $cleanValue = strip_tags($value);
                        $cleanValue = preg_replace('/NT\$\d+/', '', $cleanValue);
                        $cleanValue = trim($cleanValue);
                        
                        if ($cleanValue) {
                            $itemData['specs'][] = [
                                'label' => $key,
                                'value' => $cleanValue,
                                'is_file' => false
                            ];
                        }
                    }
                }
                
                $formattedItems[] = $itemData;
            }
            
            $formattedOrder = [
                'order_id' => $order['id'],
                'order_number' => $order['number'],
                'order_date' => $order['date_created'],
                'order_status' => 'wc-' . $order['status'],
                'total' => (float)$order['total'],
                'subtotal' => (float)$order['total'] - (float)$order['total_tax'],
                'tax' => (float)$order['total_tax'],
                'shipping_method' => $order['shipping_lines'][0]['method_title'] ?? '',
                'payment_method' => $order['payment_method_title'] ?? '',
                'first_name' => $order['billing']['first_name'] ?? '',
                'last_name' => $order['billing']['last_name'] ?? '',
                'customer_name' => trim(($order['billing']['first_name'] ?? '') . ' ' . ($order['billing']['last_name'] ?? '')),
                'company' => $order['billing']['company'] ?? '',
                'customer_type' => !empty($order['billing']['company']) ? 'business' : 'personal',
                'email' => $order['billing']['email'] ?? '',
                'phone' => $order['billing']['phone'] ?? '',
                'address' => trim(($order['billing']['address_1'] ?? '') . ' ' . ($order['billing']['address_2'] ?? '')),
                'city' => $order['billing']['city'] ?? '',
                'state' => $order['billing']['state'] ?? '',
                'postcode' => $order['billing']['postcode'] ?? '',
                'customer_note' => $order['customer_note'] ?? '',
                'invoice_need' => $invoiceNeed === 'yes',
                'invoice_company' => $invoiceCompany,
                'invoice_vat_number' => $invoiceVatNumber,
                'items' => $formattedItems
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
