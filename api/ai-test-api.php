<?php
/**
 * AI 測試 API 後端
 */

require_once __DIR__ . '/ai-config.php';   // 同層的 ai-config.php

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

$testType = $input['test_type'] ?? 'simple';

// 根據測試類型建立 Prompt
if ($testType === 'simple') {
    $prompt = $input['prompt'] ?? '請用一句話介紹你自己';
} else if ($testType === 'seo') {
    $productName = $input['product_name'] ?? 'A7+ 透明蝴蝶結卡';
    $material = $input['material'] ?? '象牙卡';
    $size = $input['size'] ?? '16x16cm';
    $price = $input['price'] ?? 'NT$ 20/張';
    
    $prompt = "
請為以下產品產生 3 組吸引人的 SEO 標題:

產品名稱: {$productName}
材質: {$material}
尺寸: {$size}
價格: {$price}

要求:
1. 每組標題 20-30 字
2. 包含關鍵字:婚禮、喜帖
3. 吸引人點擊

請直接輸出 3 行標題,不要編號。
";
}

// 呼叫 Gemini API
$result = callGeminiAPI($prompt);

// 回傳結果
echo json_encode($result, JSON_UNESCAPED_UNICODE);
