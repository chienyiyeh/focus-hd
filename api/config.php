<?php
/**
 * 資料庫連線設定
 * * 部署步驟：
 * 1. 複製此檔案為 config.php
 * 2. 修改下方的資料庫連線資訊
 * 3. 確保此檔案權限設為 644
 */

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
define('SESSION_LIFETIME', 86400);  // 24 小時
define('AUTH_COOKIE_NAME', 'KANBAN_AUTH');
define('AUTH_COOKIE_LIFETIME', 2592000); // 30 天
define('AUTH_COOKIE_SECRET', hash('sha256', DB_HOST . '|' . DB_NAME . '|' . DB_USER . '|' . DB_PASS));

// CORS 設定（如需要跟 WordPress 整合）
define('ALLOW_CORS', false);  // 設為 true 啟用 CORS
define('ALLOWED_ORIGINS', [
    'https://yourdomain.com',
    // 'https://test.yourdomain.com',
]);

// ============================================
// 應用程式設定
// ============================================

define('APP_DEBUG', false);  // 正式環境請設為 false
define('APP_TIMEZONE', 'Asia/Taipei');

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
            ];
            
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                die(json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]));
            } else {
                die(json_encode(['success' => false, 'error' => 'Database connection failed']));
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
 * 錯誤回應 (修改為固定回傳 200 狀態碼，配合前端 fetch 邏輯)
 */
function errorResponse($message, $statusCode = 200) {
    jsonResponse(['success' => false, 'error' => $message], 200);
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
    }
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

function isHttpsRequest() {
    return (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) == 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    );
}

function setAuthCookie($userId, $username) {
    $expiresAt = time() + AUTH_COOKIE_LIFETIME;
    $payload = $userId . '|' . $username . '|' . $expiresAt;
    $signature = hash_hmac('sha256', $payload, AUTH_COOKIE_SECRET);
    $token = base64_encode(json_encode([
        'uid' => (int)$userId,
        'usr' => (string)$username,
        'exp' => $expiresAt,
        'sig' => $signature
    ], JSON_UNESCAPED_UNICODE));

    setcookie(AUTH_COOKIE_NAME, $token, [
        'expires' => $expiresAt,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clearAuthCookie() {
    setcookie(AUTH_COOKIE_NAME, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function restoreSessionFromAuthCookie() {
    if (isset($_SESSION['user_id'])) {
        return;
    }

    $raw = $_COOKIE[AUTH_COOKIE_NAME] ?? '';
    if ($raw === '') {
        return;
    }

    $decoded = json_decode(base64_decode($raw, true), true);
    if (!is_array($decoded)) {
        return;
    }

    $uid = (int)($decoded['uid'] ?? 0);
    $usr = (string)($decoded['usr'] ?? '');
    $exp = (int)($decoded['exp'] ?? 0);
    $sig = (string)($decoded['sig'] ?? '');
    if ($uid <= 0 || $usr === '' || $exp < time() || $sig === '') {
        return;
    }

    $payload = $uid . '|' . $usr . '|' . $exp;
    $expected = hash_hmac('sha256', $payload, AUTH_COOKIE_SECRET);
    if (!hash_equals($expected, $sig)) {
        return;
    }

    $_SESSION['user_id'] = $uid;
    $_SESSION['username'] = $usr;
    $_SESSION['login_time'] = time();
}

// ============================================
// SESSION 初始化
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = isHttpsRequest();

    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);
    ini_set('session.cookie_lifetime', (string)SESSION_LIFETIME);

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
restoreSessionFromAuthCookie();

// 設定時區
date_default_timezone_set(APP_TIMEZONE);

// OPTIONS 請求處理（CORS 預檢）
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (ALLOW_CORS) {
        http_response_code(200);
        exit;
    }
}
