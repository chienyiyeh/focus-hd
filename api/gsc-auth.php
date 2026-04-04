<?php
/**
 * Google Search Console 授權頁面
 */

require_once 'gsc-config.php';

// 檢查是否已授權
$isAuthorized = file_exists(GSC_TOKEN_FILE);

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Search Console 授權</title>
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
            padding: 40px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            max-width: 600px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        
        .status {
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            font-weight: 600;
        }
        
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
        }
        
        .steps {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }
        
        .steps h3 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .steps ol {
            padding-left: 20px;
        }
        
        .steps li {
            margin: 10px 0;
            line-height: 1.6;
        }
        
        .steps code {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        
        .info-box strong {
            color: #0d47a1;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Google Search Console</h1>
        <p class="subtitle">SEO 排名追蹤系統</p>
        
        <?php if ($isAuthorized): ?>
            <div class="status success">
                ✅ 已授權!可以開始追蹤排名了
            </div>
            
            <div class="info-box">
                <strong>授權狀態:</strong> 已連接到 Google Search Console<br>
                <strong>網站:</strong> <?php echo GSC_SITE_URL; ?>
            </div>
            
            <a href="gsc-test.php" class="btn">🧪 測試排名追蹤</a>
            <a href="?revoke=1" class="btn btn-secondary" style="margin-left: 10px;">🔓 取消授權</a>
            
            <?php
            if (isset($_GET['revoke'])) {
                unlink(GSC_TOKEN_FILE);
                echo '<script>location.href="gsc-auth.php";</script>';
            }
            ?>
            
        <?php else: ?>
            <div class="status warning">
                ⚠️ 尚未授權 Google Search Console
            </div>
            
            <div class="steps">
                <h3>📋 設定步驟:</h3>
                <ol>
                    <li>前往 <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                    <li>建立專案(或使用現有專案)</li>
                    <li>啟用「<strong>Search Console API</strong>」</li>
                    <li>建立「<strong>OAuth 2.0 用戶端 ID</strong>」</li>
                    <li>應用程式類型選「<strong>網頁應用程式</strong>」</li>
                    <li>已授權的重新導向 URI 填入:<br>
                        <code><?php echo GSC_REDIRECT_URI; ?></code>
                    </li>
                    <li>複製「用戶端 ID」和「用戶端密鑰」</li>
                    <li>編輯 <code>gsc-config.php</code> 填入 ID 和密鑰</li>
                </ol>
            </div>
            
            <?php if (empty(GSC_CLIENT_ID)): ?>
                <div class="status warning">
                    ⚠️ 請先編輯 <code>gsc-config.php</code> 填入 API 憑證
                </div>
            <?php else: ?>
                <a href="<?php echo getGSCAuthUrl(); ?>" class="btn">
                    🔐 授權 Google Search Console
                </a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
