<?php
/**
 * 認證 API
 * POST /api/auth.php?action=login   - 登入
 * GET  /api/auth.php?action=logout  - 登出（跳轉）
 * GET  /api/auth.php?action=check   - 檢查登入狀態
 */

require_once 'config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'check';

switch ($action) {
    case 'login':  handleLogin();  break;
    case 'logout': handleLogout(); break;
    case 'check':  handleCheck();  break;
    default: jsonResponse(['success' => false, 'error' => '無效的動作'], 200);
}

// ============================================
// 登入
// ============================================
function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'error' => '僅支援 POST 請求'], 200);
    }

    $input    = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

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

        $_SESSION['user_id']    = $user['id'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['login_time'] = time();

        successResponse([
            'user_id'  => $user['id'],
            'username' => $user['username']
        ], '登入成功');

    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => APP_DEBUG ? $e->getMessage() : '登入失敗'], 200);
    }
}

// ============================================
// 登出（直接跳轉，不回傳 JSON）
// ============================================
function handleLogout() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    setcookie('remember_token', '', time() - 3600, '/');
    session_destroy();
    header('Location: ../login.php');
    exit;
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
        jsonResponse(['success' => true, 'logged_in' => false]);
    }
}
