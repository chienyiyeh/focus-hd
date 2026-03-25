<?php
/**
 * 建立 Holly 帳號
 * 使用完畢後請刪除此檔案
 */

require_once 'config.php';

$username = 'holly';
$password = 'abh52580511';

try {
    $db = getDB();
    
    // 檢查帳號是否已存在
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    
    if ($stmt->fetch()) {
        echo "帳號 holly 已存在！";
        exit;
    }
    
    // 生成密碼 hash
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    
    // 建立新帳號
    $stmt = $db->prepare("INSERT INTO users (username, password_hash, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$username, $passwordHash]);
    
    $userId = $db->lastInsertId();
    
    // 為新用戶建立預設專案
    $defaultProjects = [
        ['seo', 'SEO', '#E3F2FD', '#1565C0'],
        ['product', '產品', '#E8F5E9', '#2E7D32'],
        ['client', '客戶', '#FFF3E0', '#E65100'],
        ['family', '家庭', '#FCE4EC', '#C2185B'],
        ['sop', 'SOP', '#F3E5F5', '#7B1FA2'],
        ['finance', '財務', '#E0F2F1', '#00695C'],
        ['other', '其他', '#ECEFF1', '#455A64']
    ];
    
    $stmt = $db->prepare("
        INSERT INTO projects (user_id, project_key, label, bg_color, text_color, is_default) 
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    
    foreach ($defaultProjects as $proj) {
        $stmt->execute([$userId, $proj[0], $proj[1], $proj[2], $proj[3]]);
    }
    
    echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>帳號建立成功</title>
    <style>
        body { font-family: 'Noto Sans TC', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; }
        .container { background: white; border-radius: 16px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 500px; }
        h1 { color: #667eea; text-align: center; }
        .success { background: #E8F5E9; color: #2E7D32; padding: 20px; border-radius: 8px; margin: 20px 0; font-weight: 600; text-align: center; }
        .info { background: #FFF3E0; padding: 20px; border-radius: 8px; margin: 20px 0; line-height: 1.8; }
        .warning { background: #FFEBEE; color: #C62828; padding: 15px; border-radius: 8px; margin: 20px 0; font-weight: 600; text-align: center; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 4px; font-family: monospace; }
        .accounts { display: grid; gap: 15px; margin: 20px 0; }
        .account { background: #f9f9f9; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea; }
        .btn { background: #667eea; color: white; border: none; padding: 12px 30px; border-radius: 8px; font-size: 16px; cursor: pointer; text-decoration: none; display: block; text-align: center; margin: 10px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>✅ Holly 帳號建立成功！</h1>
        
        <div class='success'>
            帳號已建立，預設專案已設定完成！
        </div>
        
        <div class='info'>
            <strong>👨 您的帳號：</strong>
            <div class='account'>
                帳號：<code>chienyi</code><br>
                密碼：<code>d421d421</code>
            </div>
            
            <strong>👩 Holly 的帳號：</strong>
            <div class='account'>
                帳號：<code>holly</code><br>
                密碼：<code>abh52580511</code>
            </div>
        </div>
        
        <div class='warning'>
            ⚠️ 請立即刪除此檔案：<code>create-holly.php</code>
        </div>
        
        <a href='index.php' class='btn'>前往登入</a>
    </div>
</body>
</html>";
    
} catch (Exception $e) {
    echo "錯誤：" . $e->getMessage();
}
?>
