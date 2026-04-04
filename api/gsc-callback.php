<?php
/**
 * Google Search Console OAuth 回調
 */

require_once 'gsc-config.php';

// 檢查是否有授權碼
if (!isset($_GET['code'])) {
    die('授權失敗:缺少授權碼');
}

// 交換 Access Token
$success = exchangeGSCToken($_GET['code']);

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>授權結果</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Microsoft JhengHei", sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            max-width: 500px;
            background: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        p {
            color: #666;
            margin-bottom: 30px;
        }
        
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 40px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($success): ?>
            <div class="icon">✅</div>
            <h1>授權成功!</h1>
            <p>已成功連接 Google Search Console</p>
            <a href="gsc-test.php" class="btn">開始測試</a>
        <?php else: ?>
            <div class="icon">❌</div>
            <h1>授權失敗</h1>
            <p>請檢查 API 憑證設定</p>
            <a href="gsc-auth.php" class="btn">重試</a>
        <?php endif; ?>
    </div>
</body>
</html>
