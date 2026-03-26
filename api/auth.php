<?php
/**
 * 認證 API
 * * 端點:
 * POST /api/auth.php?action=login    - 登入
 * POST /api/auth.php?action=logout   - 登出
 * GET  /api/auth.php?action=check    - 檢查登入狀態
 */

require_once __DIR__ . '/config.php';

// 獲取請求動作
$action = $_GET['action'] ?? $_POST['action'] ?? 'check';

// 路由
switch ($action) {
    case 'login':
        handleLogin();
        break;
    
    case 'logout':
        handleLogout();
        break;
    
    case 'check':
        handleCheck();
        break;
    
    default:
        jsonResponse(['success' => false, 'error' => '無效的動作'], 200);
}

// ============================================
// 處理登入
// ============================================
function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'error' => '僅支援 POST 請求'], 200);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        jsonResponse(['success' => false, 'error' => '請輸入帳號和密碼'], 200);
    }
    
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        // 驗證密碼
        if (!$user || !password_verify($password, $user['password_hash'])) {
            // 這裡改用 jsonResponse 並自帶 success: false，確保前端能正常顯示「帳號或密碼錯誤」
            jsonResponse(['success' => false, 'error' => '帳號或密碼錯誤'], 200);
        }
        
        // 設定 SESSION
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['login_time'] = time();

        // 若目前落在舊版 session 名稱，登入成功後搬移到 KANBAN_SESSION
        if (session_name() !== SESSION_NAME) {
            $sessionData = $_SESSION;
            session_write_close();

            session_name(SESSION_NAME);
            session_start();
            $_SESSION = $sessionData;
        }
        
        // 使用 config.php 裡的 successResponse (它自帶 'success' => true)
        successResponse([
            'user_id' => $user['id'],
            'username' => $user['username']
        ], '登入成功');
        
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'error' => APP_DEBUG ? $e->getMessage() : '登入失敗，請聯絡管理員'], 200);
    }
}

// ============================================
// 處理登出
// ============================================
function handleLogout() {
    // 清除所有的 session 變數
    $_SESSION = array();
    
    // 如果有設定 session cookie，也將其銷毀
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
            'success' => true,
            'logged_in' => true,
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username']
        ]);
    } else {
        jsonResponse([
            'success' => true,
            'logged_in' => false
        ]);
    }
}
