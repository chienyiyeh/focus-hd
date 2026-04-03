<?php
session_start();

// 如果已登入，跳轉到首頁
if (isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}

// 檢查是否有記住登入的 Cookie
if (isset($_COOKIE['remember_user']) && isset($_COOKIE['remember_token'])) {
    $_SESSION['username'] = $_COOKIE['remember_user'];
    $_SESSION['user_id'] = 1; // 這裡應該從資料庫查詢
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 直接使用資料庫資訊，避免 config.php 路徑問題
    $db_host = 'localhost';
    $db_name = 'zeyjsvrczr';
    $db_user = 'zeyjsvrczr';
    $db_pass = 'nrPBsleknr';
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error = '請輸入帳號和密碼';
    } else {
        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['login_time'] = time();
                
                // 記住登入：設定 30 天 Cookie
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expiry = time() + (30 * 24 * 60 * 60); // 30 天
                    
                    setcookie('remember_token', $token, [
                        'expires' => $expiry,
                        'path' => '/',
                        'secure' => false, // Cloudways 可能沒有 HTTPS
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                    setcookie('remember_user', $user['username'], [
                        'expires' => $expiry,
                        'path' => '/',
                        'secure' => false,
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]);
                }
                
                header('Location: index.php');
                exit;
            } else {
                $error = '帳號或密碼錯誤';
            }
        } catch (PDOException $e) {
            $error = '資料庫連線失敗: ' . $e->getMessage();
        } catch (Exception $e) {
            $error = '登入失敗: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登入 - FOCUS 看板系統</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo {
            text-align: center;
            font-size: 48px;
            margin-bottom: 10px;
        }
        h1 {
            text-align: center;
            font-size: 24px;
            color: #1e293b;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 8px;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.2s;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }
        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .remember-me label {
            margin: 0;
            font-weight: 400;
            cursor: pointer;
        }
        .login-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102,126,234,0.4);
        }
        .login-btn:active {
            transform: translateY(0);
        }
        .error {
            background: #FEE2E2;
            color: #991B1B;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border: 1px solid #FCA5A5;
        }
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">🎯</div>
        <h1>FOCUS 看板系統</h1>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">帳號</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">密碼</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="remember-me">
                <input type="checkbox" id="remember" name="remember" checked>
                <label for="remember">記住我 30 天</label>
            </div>
            
            <button type="submit" class="login-btn">登入</button>
        </form>
    </div>
</body>
</html>
