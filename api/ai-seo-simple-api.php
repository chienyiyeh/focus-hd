<?php
/**
 * AI SEO API - 獨立版本
 */

// API Key (直接寫在這裡,測試用)
define('GEMINI_API_KEY', 'AIzaSyCfg0-R3CS0Sy-_f7JKXG6-hGuoWrvL_D8');

header('Content-Type: application/json; charset=utf-8');

// 只接受 POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => '只接受 POST 請求']);
    exit;
}

// 取得 JSON 輸入
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => '無效的 JSON 格式']);
    exit;
}

// 取得參數
$productName = $input['product_name'] ?? '';
$material = $input['material'] ?? '';
$size = $input['size'] ?? '';
$price = $input['price'] ?? '';
$features = $input['features'] ?? '';
$targetAudience = $input['target_audience'] ?? '新娘';

// 驗證必填
if (empty($productName) || empty($material) || empty($size)) {
    echo json_encode(['success' => false, 'error' => '請填寫產品名稱、材質和尺寸']);
    exit;
}

// 建立 Prompt
$prompt = "
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
2. 包含關鍵字:{$productName}
3. 要吸引人點擊
4. 針對 {$targetAudience} 設計

風格要求:
- 第 1 組:強調品質和質感
- 第 2 組:強調快速服務
- 第 3 組:強調性價比
- 第 4 組:強調客製化
- 第 5 組:強調專業推薦

請直接輸出 5 行標題,不要編號,不要其他說明。
";

// 呼叫 Gemini API
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . GEMINI_API_KEY;

$data = [
    'contents' => [
        [
            'parts' => [
                ['text' => $prompt]
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.9,
        'maxOutputTokens' => 2048,
    ]
];

$ch = curl_init($url);
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
    echo json_encode([
        'success' => false, 
        'error' => 'API 呼叫失敗: HTTP ' . $httpCode,
        'response' => $response
    ]);
    exit;
}

$result = json_decode($response, true);

if (!isset($result['candidates'][0]['content']['parts'][0]['text'])) {
    echo json_encode([
        'success' => false, 
        'error' => '無法解析 API 回應',
        'response' => $result
    ]);
    exit;
}

$content = $result['candidates'][0]['content']['parts'][0]['text'];

// 解析結果
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

// 回傳結果
echo json_encode([
    'success' => true,
    'results' => $results,
    'ai_used' => 'Gemini 2.5 Flash'
], JSON_UNESCAPED_UNICODE);
