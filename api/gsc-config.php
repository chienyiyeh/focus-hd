<?php
/**
 * Google Search Console API 設定
 * 
 * 設定步驟:
 * 1. 前往 https://console.cloud.google.com/
 * 2. 建立專案 → 啟用 Search Console API
 * 3. 建立 OAuth 2.0 憑證
 * 4. 填入下方的 CLIENT_ID 和 CLIENT_SECRET
 */

// ============================================
// Google API 設定
// ============================================

define('GSC_CLIENT_ID', '');     // 👈 填入你的 Client ID
define('GSC_CLIENT_SECRET', ''); // 👈 填入你的 Client Secret
define('GSC_REDIRECT_URI', 'https://phpstack-1553960-6296402.cloudwaysapps.com/api/gsc-callback.php');

// Search Console 網站網址
define('GSC_SITE_URL', 'https://apple-print.com.tw/');

// Token 儲存路徑
define('GSC_TOKEN_FILE', __DIR__ . '/gsc-token.json');

// ============================================
// OAuth 授權網址
// ============================================

function getGSCAuthUrl() {
    $params = [
        'client_id' => GSC_CLIENT_ID,
        'redirect_uri' => GSC_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
        'access_type' => 'offline',
        'prompt' => 'consent'
    ];
    
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

// ============================================
// 交換 Access Token
// ============================================

function exchangeGSCToken($code) {
    $data = [
        'code' => $code,
        'client_id' => GSC_CLIENT_ID,
        'client_secret' => GSC_CLIENT_SECRET,
        'redirect_uri' => GSC_REDIRECT_URI,
        'grant_type' => 'authorization_code'
    ];
    
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if (isset($result['access_token'])) {
        // 儲存 token
        file_put_contents(GSC_TOKEN_FILE, json_encode($result));
        return true;
    }
    
    return false;
}

// ============================================
// 刷新 Access Token
// ============================================

function refreshGSCToken() {
    if (!file_exists(GSC_TOKEN_FILE)) {
        return false;
    }
    
    $token = json_decode(file_get_contents(GSC_TOKEN_FILE), true);
    
    if (!isset($token['refresh_token'])) {
        return false;
    }
    
    $data = [
        'refresh_token' => $token['refresh_token'],
        'client_id' => GSC_CLIENT_ID,
        'client_secret' => GSC_CLIENT_SECRET,
        'grant_type' => 'refresh_token'
    ];
    
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if (isset($result['access_token'])) {
        // 保留 refresh_token
        $result['refresh_token'] = $token['refresh_token'];
        file_put_contents(GSC_TOKEN_FILE, json_encode($result));
        return true;
    }
    
    return false;
}

// ============================================
// 取得有效的 Access Token
// ============================================

function getGSCAccessToken() {
    if (!file_exists(GSC_TOKEN_FILE)) {
        return null;
    }
    
    $token = json_decode(file_get_contents(GSC_TOKEN_FILE), true);
    
    // 檢查是否過期 (提前 5 分鐘刷新)
    if (isset($token['expires_at']) && time() > ($token['expires_at'] - 300)) {
        refreshGSCToken();
        $token = json_decode(file_get_contents(GSC_TOKEN_FILE), true);
    }
    
    return $token['access_token'] ?? null;
}

// ============================================
// Search Console API 請求
// ============================================

function gscRequest($endpoint, $data = null) {
    $accessToken = getGSCAccessToken();
    
    if (!$accessToken) {
        throw new Exception('未授權,請先完成 Google 授權');
    }
    
    $url = 'https://www.googleapis.com/webmasters/v3/' . $endpoint;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 401) {
        // Token 過期,嘗試刷新
        if (refreshGSCToken()) {
            return gscRequest($endpoint, $data); // 重試
        }
        throw new Exception('授權已過期,請重新授權');
    }
    
    if ($httpCode !== 200) {
        throw new Exception('API 請求失敗: HTTP ' . $httpCode);
    }
    
    return json_decode($response, true);
}

// ============================================
// 查詢關鍵字排名
// ============================================

function getKeywordRanking($keyword, $startDate = null, $endDate = null) {
    if (!$startDate) {
        $startDate = date('Y-m-d', strtotime('-7 days'));
    }
    if (!$endDate) {
        $endDate = date('Y-m-d', strtotime('-1 day'));
    }
    
    $data = [
        'startDate' => $startDate,
        'endDate' => $endDate,
        'dimensions' => ['query', 'page'],
        'dimensionFilterGroups' => [
            [
                'filters' => [
                    [
                        'dimension' => 'query',
                        'expression' => $keyword
                    ]
                ]
            ]
        ],
        'rowLimit' => 100
    ];
    
    $endpoint = 'sites/' . urlencode(GSC_SITE_URL) . '/searchAnalytics/query';
    
    return gscRequest($endpoint, $data);
}

// ============================================
// 查詢網址效能
// ============================================

function getUrlPerformance($url, $startDate = null, $endDate = null) {
    if (!$startDate) {
        $startDate = date('Y-m-d', strtotime('-30 days'));
    }
    if (!$endDate) {
        $endDate = date('Y-m-d', strtotime('-1 day'));
    }
    
    $data = [
        'startDate' => $startDate,
        'endDate' => $endDate,
        'dimensions' => ['query'],
        'dimensionFilterGroups' => [
            [
                'filters' => [
                    [
                        'dimension' => 'page',
                        'expression' => $url
                    ]
                ]
            ]
        ],
        'rowLimit' => 100
    ];
    
    $endpoint = 'sites/' . urlencode(GSC_SITE_URL) . '/searchAnalytics/query';
    
    return gscRequest($endpoint, $data);
}

// ============================================
// 取得熱門關鍵字
// ============================================

function getTopQueries($limit = 50, $startDate = null, $endDate = null) {
    if (!$startDate) {
        $startDate = date('Y-m-d', strtotime('-30 days'));
    }
    if (!$endDate) {
        $endDate = date('Y-m-d', strtotime('-1 day'));
    }
    
    $data = [
        'startDate' => $startDate,
        'endDate' => $endDate,
        'dimensions' => ['query'],
        'rowLimit' => $limit
    ];
    
    $endpoint = 'sites/' . urlencode(GSC_SITE_URL) . '/searchAnalytics/query';
    
    return gscRequest($endpoint, $data);
}
