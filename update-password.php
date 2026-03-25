<?php
/**
 * 帳號密碼更新工具
 * 使用完畢後請立即刪除此檔案！
 */

require_once 'config.php';

// 新的帳號和密碼
$newUsername = 'chienyi';
$newPassword = 'd421d421';

try {
    $db = getDB();
    
    // 生成密碼 hash
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // 更新帳號和密碼
    $stmt = $db->prepare("UPDATE users SET username = ?, password_hash = ? WHERE id = 1");
    $stmt->execute([$newUsername, $passwordHash]);
    
    echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>帳號更新成功</title>
    <style>
        body { font-family: 'Noto Sans TC', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; }
        .container { background: white; border-radius: 16px; padding: 40px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 500px; text-align: center; }
        h1 { color: #667eea; margin-bottom: 20px; }
        .success { background: #E8F5E9; color: #2E7D32; padding: 20px; border-radius: 8px; margin: 20px 0; font-weight: 600; }
        .info { background: #FFF3E0; color: #E65100; padding: 15px; border-radius: 8px; margin: 20px 0; text-align: left; line-height: 1.8; }
        .warning { background: #FFEBEE; color: #C62828; padding: 15px; border-radius: 8px; margin: 20px 0; font-weight: 600; }
        .btn { background: #667eea; color: white; border: none; padding: 12px 30px; border-radius: 8px; font-size: 16px; cursor: pointer; text-decoration: none; display: inline-block; margin: 10px; }
        .btn:hover { background: #5568d3; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 4px; font-family: monospace; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>✅ 帳號更新成功！</h1>
        
        <div class='success'>
            帳號和密碼已成功更新！
        </div>
        
        <div class='info'>
            <strong>新的登入資訊：</strong><br>
            帳號：<code>chienyi</code><br>
            密碼：<code>d421d421</code>
        </div>
        
        <div class='warning'>
            ⚠️ 重要：請立即刪除此檔案！<br>
            檔案名稱：<code>update-password.php</code>
        </div>
        
        <a href='index.php' class='btn'>前往登入</a>
    </div>
</body>
</html>";
    
} catch (Exception $e) {
    echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>更新失敗</title>
    <style>
        body { font-family: sans-serif; padding: 40px; background: #f5f5f5; }
        .error { background: #FFEBEE; color: #C62828; padding: 20px; border-radius: 8px; max-width: 600px; margin: 0 auto; }
    </style>
</head>
<body>
    <div class='error'>
        <h2>❌ 更新失敗</h2>
        <p>錯誤訊息：" . $e->getMessage() . "</p>
    </div>
</body>
</html>";
}
?>
