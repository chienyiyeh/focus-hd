<?php
/**
 * AI SEO 文案生成器
 * 用途:一鍵產生 5 組吸引人的產品文案
 * 
 * 使用方式:
 * http://你的網址/ai-seo-generator.php
 * 
 * 功能:
 * 1. 輸入產品規格 → 自動產生 5 組 SEO 標題
 * 2. 自動判斷使用 Gemini 或 Claude
 * 3. 可複製文案直接貼到 WordPress
 */

require_once __DIR__ . '/../config.php';  // 上一層的 config.php
require_once __DIR__ . '/ai-config.php';   // 同層的 ai-config.php

// 檢查是否為管理員登入
// session_start(); // config.php 已經啟動 session 了
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // 先不驗證身份,方便測試
    // die('請先登入管理員帳號');
}

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI 文案生成器 - 比同業好 10 倍的秘密武器</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Microsoft JhengHei", sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .content {
            padding: 40px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            font-size: 14px;
        }
        
        input, textarea, select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .results {
            margin-top: 30px;
            display: none;
        }
        
        .result-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
            position: relative;
        }
        
        .result-number {
            position: absolute;
            top: 10px;
            right: 15px;
            background: #667eea;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }
        
        .result-text {
            font-size: 16px;
            line-height: 1.6;
            color: #333;
            margin-bottom: 10px;
            padding-right: 40px;
        }
        
        .copy-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .copy-btn:hover {
            background: #218838;
        }
        
        .copy-btn.copied {
            background: #6c757d;
        }
        
        .loading {
            text-align: center;
            padding: 30px;
            display: none;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .ai-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .ai-gemini {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .ai-claude {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
            display: none;
        }
        
        .tips {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .tips h3 {
            color: #856404;
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .tips ul {
            color: #856404;
            padding-left: 20px;
            font-size: 14px;
            line-height: 1.8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 AI 文案生成器</h1>
            <p>比同業好 10 倍的秘密武器</p>
            <span class="badge">由 Gemini & Claude 驅動</span>
        </div>
        
        <div class="content">
            <div class="tips">
                <h3>💡 快速上手</h3>
                <ul>
                    <li>填寫產品資訊 → 點擊生成 → 複製文案到 WordPress</li>
                    <li>高毛利產品會自動使用更好的 AI</li>
                </ul>
            </div>
            
            <form id="seoForm">
                <div class="form-group">
                    <label>產品名稱 *</label>
                    <input type="text" id="productName" placeholder="例如: A7+ 透明蝴蝶結卡" required>
                </div>
                
                <div class="form-group">
                    <label>材質 *</label>
                    <input type="text" id="material" placeholder="例如: 象牙卡 260gsm" required>
                </div>
                
                <div class="form-group">
                    <label>尺寸 *</label>
                    <input type="text" id="size" placeholder="例如: 16x16cm" required>
                </div>
                
                <div class="form-group">
                    <label>價格範圍</label>
                    <input type="text" id="price" placeholder="例如: NT$ 18-25/張">
                </div>
                
                <div class="form-group">
                    <label>特色賣點</label>
                    <textarea id="features" placeholder="例如: 雷射雕刻、手工組裝、客製化服務"></textarea>
                </div>
                
                <div class="form-group">
                    <label>目標客群</label>
                    <select id="targetAudience">
                        <option value="新娘">新娘</option>
                        <option value="婚禮統籌">婚禮統籌</option>
                        <option value="企業採購">企業採購</option>
                        <option value="活動主辦">活動主辦</option>
                        <option value="一般消費者">一般消費者</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>文案類型</label>
                    <select id="contentType">
                        <option value="seo_title">SEO 標題 (推薦)</option>
                        <option value="product_desc">產品描述</option>
                        <option value="meta_description">Meta 描述</option>
                    </select>
                </div>
                
                <button type="submit" class="btn">🚀 生成文案</button>
            </form>
            
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p>AI 正在為您產生文案中...</p>
                <small>通常需要 5-10 秒</small>
            </div>
            
            <div class="error" id="error"></div>
            
            <div class="results" id="results"></div>
        </div>
    </div>
    
    <script>
        const form = document.getElementById('seoForm');
        const loading = document.getElementById('loading');
        const results = document.getElementById('results');
        const errorDiv = document.getElementById('error');
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            // 清空之前的結果
            results.style.display = 'none';
            results.innerHTML = '';
            errorDiv.style.display = 'none';
            loading.style.display = 'block';
            
            // 收集表單資料
            const formData = {
                product_name: document.getElementById('productName').value,
                material: document.getElementById('material').value,
                size: document.getElementById('size').value,
                price: document.getElementById('price').value,
                features: document.getElementById('features').value,
                target_audience: document.getElementById('targetAudience').value,
                content_type: document.getElementById('contentType').value,
            };
            
            try {
                const response = await fetch('ai-seo-api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                
                loading.style.display = 'none';
                
                if (data.success) {
                    displayResults(data.results, data.ai_used);
                } else {
                    showError(data.error || '生成失敗,請稍後再試');
                }
                
            } catch (error) {
                loading.style.display = 'none';
                showError('網路錯誤: ' + error.message);
            }
        });
        
        function displayResults(resultsData, aiUsed) {
            results.innerHTML = '';
            
            // 顯示使用的 AI
            const aiBadgeClass = aiUsed.includes('Claude') ? 'ai-claude' : 'ai-gemini';
            const aiBadge = `<div class="ai-badge ${aiBadgeClass}">${aiUsed}</div>`;
            
            resultsData.forEach((text, index) => {
                const resultItem = document.createElement('div');
                resultItem.className = 'result-item';
                resultItem.innerHTML = `
                    ${index === 0 ? aiBadge : ''}
                    <div class="result-number">${index + 1}</div>
                    <div class="result-text">${text}</div>
                    <button class="copy-btn" onclick="copyText(this, \`${text}\`)">
                        📋 複製文案
                    </button>
                `;
                results.appendChild(resultItem);
            });
            
            results.style.display = 'block';
        }
        
        function copyText(button, text) {
            navigator.clipboard.writeText(text).then(() => {
                const originalText = button.textContent;
                button.textContent = '✅ 已複製';
                button.classList.add('copied');
                
                setTimeout(() => {
                    button.textContent = originalText;
                    button.classList.remove('copied');
                }, 2000);
            });
        }
        
        function showError(message) {
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
        }
    </script>
</body>
</html>
