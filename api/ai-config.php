<?php
/**
 * AI API 設定檔
 * 用途：統一管理 Gemini 和 Claude API 金鑰
 * 
 * 部署步驟：
 * 1. 取得 API Key:
 *    - Gemini: https://aistudio.google.com/apikey
 *    - Claude: https://console.anthropic.com/
 * 2. 填入下方的 API Key
 * 3. 檔案權限設為 600 (chmod 600 ai-config.php)
 */

// ============================================
// Gemini API 設定 (大量文案生成 - 快速便宜)
// ============================================

define('GEMINI_API_KEY', 'AIzaSyDduxsE5i3A7k1YP5-uryUWuMmhk6heRho');  // ✅ 已設定
define('GEMINI_MODEL', 'gemini-1.5-flash-latest');  // 修正為 latest 版本
define('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1/models/' . GEMINI_MODEL . ':generateContent');

// ============================================
// Claude API 設定 (高品質文案 - 轉單率高)
// ============================================

define('CLAUDE_API_KEY', '');  // 👈 填入你的 Claude API Key (選填，高毛利產品才用)
define('CLAUDE_MODEL', 'claude-3-5-sonnet-20241022');
define('CLAUDE_ENDPOINT', 'https://api.anthropic.com/v1/messages');

// ============================================
// AI 路由規則設定
// ============================================

// 高毛利產品清單 (使用 Claude 生成高品質文案)
define('HIGH_VALUE_PRODUCTS', [
    'A7+',           // 高端產品
    '尊爵款',         // 高端產品
    // 之後可以新增更多高毛利產品關鍵字
]);

// 文案類型設定
define('AI_CONTENT_TYPES', [
    'seo_title'         => 'SEO 標題',
    'product_desc'      => '產品描述',
    'meta_description'  => 'Meta 描述',
    'category_desc'     => '分類描述',
    'brand_story'       => '品牌故事',
    'service_intro'     => '服務介紹',
]);

// ============================================
// 輔助函數
// ============================================

/**
 * 呼叫 Gemini API
 */
function callGeminiAPI($prompt, $options = []) {
    if (empty(GEMINI_API_KEY)) {
        return ['success' => false, 'error' => '請先設定 Gemini API Key'];
    }
    
    $temperature = $options['temperature'] ?? 0.9;  // 創意度
    $maxTokens = $options['max_tokens'] ?? 2048;
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => $temperature,
            'maxOutputTokens' => $maxTokens,
        ]
    ];
    
    $ch = curl_init(GEMINI_ENDPOINT . '?key=' . GEMINI_API_KEY);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['success' => false, 'error' => 'API 呼叫失敗: HTTP ' . $httpCode, 'response' => $response];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
        return [
            'success' => true, 
            'content' => $result['candidates'][0]['content']['parts'][0]['text']
        ];
    }
    
    return ['success' => false, 'error' => '無法解析 API 回應', 'response' => $result];
}

/**
 * 呼叫 Claude API (選填，高毛利產品才用)
 */
function callClaudeAPI($prompt, $options = []) {
    if (empty(CLAUDE_API_KEY)) {
        return ['success' => false, 'error' => '請先設定 Claude API Key'];
    }
    
    $temperature = $options['temperature'] ?? 1.0;
    $maxTokens = $options['max_tokens'] ?? 4096;
    
    $data = [
        'model' => CLAUDE_MODEL,
        'max_tokens' => $maxTokens,
        'temperature' => $temperature,
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ]
    ];
    
    $ch = curl_init(CLAUDE_ENDPOINT);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-key: ' . CLAUDE_API_KEY,
        'anthropic-version: 2023-06-01',
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['success' => false, 'error' => 'API 呼叫失敗: HTTP ' . $httpCode, 'response' => $response];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['content'][0]['text'])) {
        return [
            'success' => true, 
            'content' => $result['content'][0]['text']
        ];
    }
    
    return ['success' => false, 'error' => '無法解析 API 回應', 'response' => $result];
}

/**
 * AI 路由：根據產品類型選擇 API
 */
function routeAI($productName, $prompt, $options = []) {
    // 檢查是否為高毛利產品
    $isHighValue = false;
    foreach (HIGH_VALUE_PRODUCTS as $keyword) {
        if (stripos($productName, $keyword) !== false) {
            $isHighValue = true;
            break;
        }
    }
    
    // 高毛利產品用 Claude，其他用 Gemini
    if ($isHighValue && !empty(CLAUDE_API_KEY)) {
        $result = callClaudeAPI($prompt, $options);
        $result['ai_used'] = 'Claude (高品質)';
        return $result;
    } else {
        $result = callGeminiAPI($prompt, $options);
        $result['ai_used'] = 'Gemini (快速)';
        return $result;
    }
}

/**
 * 記錄 AI 使用日誌 (選填功能)
 */
function logAIUsage($productName, $contentType, $aiUsed, $success) {
    // 可以之後串接資料庫記錄
    $logFile = __DIR__ . '/ai-usage.log';
    $logEntry = sprintf(
        "[%s] 產品: %s | 類型: %s | AI: %s | 狀態: %s\n",
        date('Y-m-d H:i:s'),
        $productName,
        $contentType,
        $aiUsed,
        $success ? '成功' : '失敗'
    );
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}
