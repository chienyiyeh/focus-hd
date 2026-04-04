<?php
/**
 * AI SEO API 後端
 * 用途:處理文案生成請求
 */

require_once __DIR__ . '/../config.php';  // 上一層的 config.php
require_once __DIR__ . '/ai-config.php';   // 同層的 ai-config.php

header('Content-Type: application/json; charset=utf-8');

// 只接受 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('只接受 POST 請求');
}

// 取得 JSON 輸入
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    errorResponse('無效的 JSON 格式');
}

// 驗證必填欄位
$required = ['product_name', 'material', 'size', 'target_audience', 'content_type'];
validateRequired($input, $required);

// 清理輸入
$productName = cleanInput($input['product_name']);
$material = cleanInput($input['material']);
$size = cleanInput($input['size']);
$price = cleanInput($input['price'] ?? '');
$features = cleanInput($input['features'] ?? '');
$targetAudience = cleanInput($input['target_audience']);
$contentType = cleanInput($input['content_type']);

// 建立 Prompt
$prompt = buildPrompt($productName, $material, $size, $price, $features, $targetAudience, $contentType);

// 呼叫 AI API (自動路由)
$result = routeAI($productName, $prompt);

if (!$result['success']) {
    errorResponse($result['error']);
}

// 解析結果
$parsedResults = parseAIResponse($result['content'], $contentType);

if (empty($parsedResults)) {
    errorResponse('AI 回應格式異常,請重試');
}

// 記錄使用日誌
logAIUsage($productName, $contentType, $result['ai_used'], true);

// 回傳結果
successResponse([
    'results' => $parsedResults,
    'ai_used' => $result['ai_used'],
]);

// ============================================
// 輔助函數
// ============================================

/**
 * 建立 Prompt
 */
function buildPrompt($productName, $material, $size, $price, $features, $targetAudience, $contentType) {
    $prompts = [
        'seo_title' => "
你是一位專業的 SEO 文案寫手,專門為婚禮喜帖、卡片產品撰寫吸引人的標題。

產品資訊:
- 產品名稱: {$productName}
- 材質: {$material}
- 尺寸: {$size}
- 價格: {$price}
- 特色: {$features}
- 目標客群: {$targetAudience}

請產出 5 組不同風格的 SEO 標題,要求:
1. 每組標題 20-30 字
2. 包含關鍵字:{$productName}、婚禮、喜帖
3. 要吸引人點擊
4. 比同業好 10 倍
5. 針對 {$targetAudience} 設計

風格要求:
- 第 1 組:強調品質和質感
- 第 2 組:強調客製化服務
- 第 3 組:強調性價比
- 第 4 組:強調時尚設計
- 第 5 組:強調限時優惠

請直接輸出 5 行標題,不要編號,不要其他說明。
",

        'product_desc' => "
你是一位專業的產品文案寫手。

產品資訊:
- 產品名稱: {$productName}
- 材質: {$material}
- 尺寸: {$size}
- 價格: {$price}
- 特色: {$features}
- 目標客群: {$targetAudience}

請產出 5 組不同角度的產品描述,每組 80-120 字。

要求:
1. 針對 {$targetAudience} 的需求
2. 凸顯產品優勢
3. 解決客戶痛點
4. 引導下單

請直接輸出 5 段描述,每段之間空一行。
",

        'meta_description' => "
你是一位 SEO 專家。

產品資訊:
- 產品名稱: {$productName}
- 材質: {$material}
- 尺寸: {$size}
- 價格: {$price}
- 特色: {$features}

請產出 5 組 Meta Description,要求:
1. 每組 120-150 字
2. 包含關鍵字
3. 吸引搜尋者點擊
4. 簡潔有力

請直接輸出 5 行,不要編號。
",
    ];
    
    return $prompts[$contentType] ?? $prompts['seo_title'];
}

/**
 * 解析 AI 回應
 */
function parseAIResponse($content, $contentType) {
    // 移除 Markdown 格式
    $content = preg_replace('/```.*?```/s', '', $content);
    $content = preg_replace('/^#+\s+.*/m', '', $content);
    
    // 分割成行
    $lines = explode("\n", $content);
    $results = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // 跳過空行、編號、標題
        if (empty($line)) continue;
        if (preg_match('/^[0-9]+[\.\)、]/', $line)) {
            $line = preg_replace('/^[0-9]+[\.\)、]\s*/', '', $line);
        }
        if (preg_match('/^[一二三四五][\.\)、]/', $line)) {
            $line = preg_replace('/^[一二三四五][\.\)、]\s*/', '', $line);
        }
        if (preg_match('/^第[一二三四五0-9]+組/', $line)) continue;
        if (preg_match('/^風格[0-9]+/', $line)) continue;
        if (strlen($line) < 10) continue;
        
        $results[] = $line;
        
        // 限制 5 組
        if (count($results) >= 5) break;
    }
    
    return $results;
}
