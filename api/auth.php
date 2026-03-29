<?php
/**
 * 認證 API
 * POST /api/auth.php?action=login
 * POST /api/auth.php?action=logout
 * GET  /api/auth.php?action=check
 */

require_once 'config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'check';

switch ($action) {
    case 'login':   handleLogin();  break;
    case 'logout':  handleLogout(); break;
    case 'check':   handleCheck();  break;
    default: jsonResponse(['success' => false, 'error' => '無效的動作'], 200);
}

// ============================================
// 處理登入
// ============================================
function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'error' => '僅支援 POST 請求'], 200);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $username   = trim($input['username'] ?? '');
    $password   = $input['password'] ?? '';
    $rememberMe = !empty($input['remember_me']);

    if (empty($username) || empty($password)) {
        jsonResponse(['success' => false, 'error' => '請輸入帳號和密碼'], 200);
    }

    try {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            jsonResponse(['success' => false, 'error' => '帳號或密碼錯誤'], 200);
        }

        // 設定 SESSION
        $_SESSION['user_id']  = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['login_time'] = time();

        // 記住我：產生 token 存 DB + cookie
        if ($rememberMe) {
            setRememberToken($db, $user['id']);
        }

        successResponse([
            'user_id'  => $user['id'],
            'username' => $user['username']
        ], '登入成功');

    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => APP_DEBUG ? $e->getMessage() : '登入失敗'], 200);
    }
}

// ============================================
// 處理登出
// ============================================
function handleLogout() {
    // 清除 remember token（但保留 cookie 讓下次自動登入）
    // 如果想要「登出後也要重新登入」就取消下面的注解
    // clearRememberToken();

    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    successResponse([], '登出成功');
}

// ============================================
// 檢查登入狀態
// ============================================
function handleCheck() {
    if (isset($_SESSION['user_id'])) {
        jsonResponse([
            'success'    => true,
            'logged_in'  => true,
            'user_id'    => $_SESSION['user_id'],
            'username'   => $_SESSION['username']
        ]);
    } else {
        jsonResponse([
            'success'   => true,
            'logged_in' => false
        ]);
    }
}

// ============================================
// Remember Token 工具函數
// ============================================
function setRememberToken($db, $userId) {
    $token     = bin2hex(random_bytes(32)); // 64 字元隨機 token
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

    // 刪除舊 token
    $db->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$userId]);

    // 插入新 token
    $db->prepare("INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)")
       ->execute([$userId, $token, $expiresAt]);

    // 設定 cookie（30 天）
    $cookieExpire = time() + (30 * 24 * 60 * 60);
    setcookie('remember_token', $token, [
        'expires'  => $cookieExpire,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function clearRememberToken() {
    if (isset($_COOKIE['remember_token'])) {
        try {
            $db    = getDB();
            $token = $_COOKIE['remember_token'];
            $db->prepare("DELETE FROM remember_tokens WHERE token = ?")->execute([$token]);
        } catch (Exception $e) {}
        setcookie('remember_token', '', time() - 3600, '/');
    }
}
