<?php
/**
 * 資料庫連線設定
 * 部署步驟：
 * 1. 複製此檔案為 config.php
 * 2. 修改下方的資料庫連線資訊
 * 3. 確保此檔案權限設為 644
 */

// ============================================
// 錯誤報告設定（正式環境請關閉）
// ============================================

// 開發模式開關（正式環境請設為 false）
define('APP_DEBUG', true);  // 改為 true 以便除錯，正式環境改回 false

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ============================================
// 資料庫設定（已經為您填入 Cloudways 正確設定）
// ============================================

define('DB_HOST', 'localhost');           // 資料庫主機
define('DB_NAME', 'zeyjsvrczr');          // 資料庫名稱
define('DB_USER', 'zeyjsvrczr');          // 資料庫用戶名
define('DB_PASS', 'nrPBsleknr');          // 資料庫密碼
define('DB_CHARSET', 'utf8mb4');

// ============================================
// 安全設定
// ============================================

// SESSION 設定
define('SESSION_NAME', 'KANBAN_SESSION');
define('SESSION_LIFETIME', 604800);  // 7天 (604800秒)

// CORS 設定（如需要跟 WordPress 整合）
define('ALLOW_CORS', false);  // 設為 true 啟用 CORS
define('ALLOWED_ORIGINS', [
    'https://yourdomain.com',
    // 'https://test.yourdomain.com',
]);

// ============================================
// 應用程式設定
// ============================================

define('APP_TIMEZONE', 'Asia/Taipei');

// ============================================
// SESSION 初始化（必須在輸出之前執行）
// ============================================

// 確保 Session 設定在 session_start() 之前
if (session_status() === PHP_SESSION_NONE) {
    // 檢查是否使用 HTTPS
    $isSecure = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    );
    
    // 設定 Session 名稱
    session_name(SESSION_NAME);
    
    // 設定 Session Cookie 參數
    $cookieParams = [
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',  // 自動使用當前域名
        'secure' => $isSecure,  // 只在 HTTPS 下傳送
        'httponly' => true,
        'samesite' => 'Lax'  // 防止 CSRF
    ];
    
    session_set_cookie_params($cookieParams);
    
    // 設定 Session 過期時間
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    ini_set('session.cookie_lifetime', SESSION_LIFETIME);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_only_cookies', 1);
    
    // 啟動 Session
    session_start();
    
    // 定期更新 Session ID（防止 Session Fixation）
    if (!isset($_SESSION['_session_initiated'])) {
        session_regenerate_id(true);
        $_SESSION['_session_initiated'] = true;
        $_SESSION['_session_start_time'] = time();
    }
    
    // 檢查 Session 是否過期（可選）
    if (isset($_SESSION['_session_start_time']) && (time() - $_SESSION['_session_start_time'] > SESSION_LIFETIME)) {
        // Session 過期，清除所有資料
        session_unset();
        session_destroy();
        
        // 重新啟動 Session
        session_start();
        $_SESSION['_session_initiated'] = true;
        $_SESSION['_session_start_time'] = time();
    }
}

// ============================================
// PDO 連線（自動載入）
// ============================================

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            $errorMsg = APP_DEBUG ? 'Database connection failed: ' . $e->getMessage() : 'Database connection failed';
            error_log('Database Error: ' . $e->getMessage());
            
            if (strpos($_SERVER['REQUEST_URI'], 'api/') !== false) {
                die(json_encode(['success' => false, 'error' => $errorMsg]));
            } else {
                die($errorMsg);
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}

// ============================================
// 輔助函數
// ============================================

/**
 * 獲取資料庫連線
 */
function getDB() {
    return Database::getInstance()->getConnection();
}

/**
 * 檢查是否已登入
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * 獲取當前使用者資訊
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? null,
        'email' => $_SESSION['email'] ?? null
    ];
}

/**
 * 檢查 Session 並返回 JSON 錯誤（用於 API）
 */
function requireLogin() {
    if (!isLoggedIn()) {
        http_response_code(401);
        echo json_encode([
            'success' => false, 
            'error' => '登入已過期，請重新登入',
            'redirect' => 'login.php'
        ]);
        exit;
    }
    return true;
}

/**
 * JSON 回應
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    
    // CORS 處理
    if (ALLOW_CORS) {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (in_array($origin, ALLOWED_ORIGINS)) {
            header("Access-Control-Allow-Origin: $origin");
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, Authorization");
            header("Access-Control-Allow-Credentials: true");
        }
    }
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * 錯誤回應
 */
function errorResponse($message, $statusCode = 200) {
    jsonResponse(['success' => false, 'error' => $message], $statusCode);
}

/**
 * 成功回應
 */
function successResponse($data = [], $message = null) {
    $response = ['success' => true];
    if ($message) $response['message'] = $message;
    if (!empty($data)) $response['data'] = $data;
    jsonResponse($response);
}

/**
 * 驗證必填欄位
 */
function validateRequired($data, $fields) {
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        errorResponse('缺少必填欄位: ' . implode(', ', $missing));
        return false;
    }
    return true;
}

/**
 * 清理輸入
 */
function cleanInput($data) {
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * 記錄 Session 資訊（除錯用）
 */
function debugSession() {
    if (APP_DEBUG) {
        error_log('=== Session Debug ===');
        error_log('Session ID: ' . session_id());
        error_log('Session Status: ' . session_status());
        error_log('User ID: ' . ($_SESSION['user_id'] ?? 'not set'));
        error_log('Username: ' . ($_SESSION['username'] ?? 'not set'));
        error_log('Session Data: ' . json_encode($_SESSION));
        error_log('Cookie Params: ' . json_encode(session_get_cookie_params()));
        error_log('===================');
    }
}

// ============================================
// 設定時區
// ============================================

date_default_timezone_set(APP_TIMEZONE);

// ============================================
// OPTIONS 請求處理（CORS 預檢）
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (ALLOW_CORS) {
        http_response_code(200);
        exit;
    }
}

// ============================================
// 可選：Session 健康檢查（除錯用）
// ============================================

if (APP_DEBUG && isset($_GET['session_debug']) && $_GET['session_debug'] === '1') {
    // 只有在明確請求時才輸出，避免干擾正常 API
    header('Content-Type: application/json');
    echo json_encode([
        'session_id' => session_id(),
        'session_status' => session_status(),
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'session_data' => $_SESSION,
        'cookie_params' => session_get_cookie_params(),
        'server_https' => $_SERVER['HTTPS'] ?? 'off',
        'is_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
?>
