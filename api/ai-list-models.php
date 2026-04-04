<?php
/**
 * Gemini 可用模型查詢工具
 */

require_once 'ai-config.php';

header('Content-Type: text/html; charset=utf-8');

$apiKey = GEMINI_API_KEY;

// 列出所有可用模型
$url = "https://generativelanguage.googleapis.com/v1/models?key={$apiKey}";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gemini 可用模型列表</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            padding: 20px;
            background: #1e1e1e;
            color: #d4d4d4;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #252526;
            padding: 20px;
            border-radius: 5px;
        }
        h1 {
            color: #4ec9b0;
        }
        pre {
            background: #1e1e1e;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border: 1px solid #3c3c3c;
        }
        .status {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .success {
            background: #0e5a0e;
            color: #4fc3f7;
        }
        .error {
            background: #5a0e0e;
            color: #f48771;
        }
        .model {
            background: #2d2d30;
            padding: 10px;
            margin: 10px 0;
            border-left: 3px solid #4ec9b0;
        }
        .model-name {
            color: #4ec9b0;
            font-weight: bold;
        }
        .method {
            color: #dcdcaa;
            margin-left: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Gemini API 可用模型查詢</h1>
        
        <?php if ($httpCode === 200): ?>
            <div class="status success">✅ API 連線成功 (HTTP <?php echo $httpCode; ?>)</div>
            
            <?php
            $data = json_decode($response, true);
            
            if (isset($data['models']) && is_array($data['models'])):
                echo "<h2>可用模型列表:</h2>";
                
                foreach ($data['models'] as $model):
                    $name = $model['name'] ?? 'Unknown';
                    $displayName = $model['displayName'] ?? '';
                    $methods = $model['supportedGenerationMethods'] ?? [];
                    
                    // 只顯示支援 generateContent 的模型
                    if (in_array('generateContent', $methods)):
            ?>
                        <div class="model">
                            <div class="model-name"><?php echo htmlspecialchars($name); ?></div>
                            <?php if ($displayName): ?>
                                <div style="color: #9cdcfe; margin-left: 20px;">顯示名稱: <?php echo htmlspecialchars($displayName); ?></div>
                            <?php endif; ?>
                            <div class="method">支援方法: <?php echo implode(', ', $methods); ?></div>
                        </div>
            <?php
                    endif;
                endforeach;
                
                echo "<h2>完整回應:</h2>";
                echo "<pre>" . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
            else:
                echo "<div class='status error'>❌ 無法解析模型列表</div>";
                echo "<pre>" . htmlspecialchars($response) . "</pre>";
            endif;
            ?>
            
        <?php else: ?>
            <div class="status error">❌ API 呼叫失敗 (HTTP <?php echo $httpCode; ?>)</div>
            <h2>錯誤回應:</h2>
            <pre><?php echo htmlspecialchars($response); ?></pre>
        <?php endif; ?>
        
        <hr style="margin: 30px 0; border-color: #3c3c3c;">
        
        <h2>💡 使用說明</h2>
        <p>上面列出的模型名稱可以直接用在 API 呼叫中。</p>
        <p>例如看到 <code style="color: #ce9178;">models/gemini-1.5-flash</code>，就可以這樣用:</p>
        <pre>https://generativelanguage.googleapis.com/v1/models/gemini-1.5-flash:generateContent</pre>
    </div>
</body>
</html>
