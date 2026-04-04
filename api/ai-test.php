<?php
/**
 * AI API 測試腳本
 * 用途:快速測試 Gemini API 是否正常運作
 * 
 * 使用方式:
 * http://你的網址/ai-test.php
 */

require_once __DIR__ . '/ai-config.php';   // 同層的 ai-config.php

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI API 測試</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Microsoft JhengHei", sans-serif;
            background: #f5f5f5;
            padding: 40px 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .status {
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: 600;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .test-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin: 10px 5px 10px 0;
        }
        
        .test-btn:hover {
            background: #5568d3;
        }
        
        .result {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
            display: none;
        }
        
        .result h3 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .result pre {
            background: #282c34;
            color: #abb2bf;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 13px;
            line-height: 1.5;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        
        .config-info {
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #ffc107;
        }
        
        .config-info h3 {
            color: #856404;
            margin-bottom: 10px;
        }
        
        .config-info p {
            color: #856404;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 AI API 測試工具</h1>
        <p style="color: #666; margin-bottom: 20px;">測試 Gemini API 是否正常運作</p>
        
        <?php
        // 檢查 API Key 設定
        if (empty(GEMINI_API_KEY)) {
            echo '<div class="status error">❌ 錯誤:尚未設定 Gemini API Key<br>請編輯 ai-config.php 第 15 行</div>';
        } else {
            $keyPreview = substr(GEMINI_API_KEY, 0, 10) . '...' . substr(GEMINI_API_KEY, -5);
            echo '<div class="status success">✅ API Key 已設定: ' . $keyPreview . '</div>';
        }
        ?>
        
        <div class="config-info">
            <h3>📋 當前設定</h3>
            <p>
                <strong>模型:</strong> <?php echo GEMINI_MODEL; ?><br>
                <strong>端點:</strong> <code><?php echo GEMINI_ENDPOINT; ?></code>
            </p>
        </div>
        
        <button class="test-btn" onclick="testSimple()">🚀 快速測試</button>
        <button class="test-btn" onclick="testSEO()">📝 測試產品文案生成</button>
        
        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p style="margin-top: 10px;">測試中...</p>
        </div>
        
        <div class="result" id="result">
            <h3>測試結果</h3>
            <div id="resultContent"></div>
        </div>
    </div>
    
    <script>
        async function testSimple() {
            showLoading();
            
            try {
                const response = await fetch('ai-test-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        test_type: 'simple',
                        prompt: '請用一句話介紹你自己'
                    })
                });
                
                const data = await response.json();
                hideLoading();
                showResult(data);
                
            } catch (error) {
                hideLoading();
                showError('網路錯誤: ' + error.message);
            }
        }
        
        async function testSEO() {
            showLoading();
            
            try {
                const response = await fetch('ai-test-api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        test_type: 'seo',
                        product_name: 'A7+ 透明蝴蝶結卡',
                        material: '象牙卡 260gsm',
                        size: '16x16cm',
                        price: 'NT$ 18-25/張'
                    })
                });
                
                const data = await response.json();
                hideLoading();
                showResult(data);
                
            } catch (error) {
                hideLoading();
                showError('網路錯誤: ' + error.message);
            }
        }
        
        function showLoading() {
            document.getElementById('loading').style.display = 'block';
            document.getElementById('result').style.display = 'none';
        }
        
        function hideLoading() {
            document.getElementById('loading').style.display = 'none';
        }
        
        function showResult(data) {
            const resultDiv = document.getElementById('result');
            const contentDiv = document.getElementById('resultContent');
            
            if (data.success) {
                contentDiv.innerHTML = `
                    <div class="status success">✅ 測試成功!</div>
                    <h4 style="margin-top: 20px;">AI 回應:</h4>
                    <pre>${escapeHtml(data.content)}</pre>
                    ${data.ai_used ? '<p style="margin-top: 10px;"><strong>使用的 AI:</strong> ' + data.ai_used + '</p>' : ''}
                `;
            } else {
                contentDiv.innerHTML = `
                    <div class="status error">❌ 測試失敗</div>
                    <p style="margin-top: 10px;"><strong>錯誤訊息:</strong> ${data.error || '未知錯誤'}</p>
                    ${data.response ? '<pre>' + escapeHtml(JSON.stringify(data.response, null, 2)) + '</pre>' : ''}
                `;
            }
            
            resultDiv.style.display = 'block';
        }
        
        function showError(message) {
            const resultDiv = document.getElementById('result');
            const contentDiv = document.getElementById('resultContent');
            
            contentDiv.innerHTML = `
                <div class="status error">❌ 發生錯誤</div>
                <p style="margin-top: 10px;">${message}</p>
            `;
            
            resultDiv.style.display = 'block';
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
