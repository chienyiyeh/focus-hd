<?php
/**
 * 資料庫連線設定
 */

// ============================================
// 資料庫設定
// ============================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'zeyjsvrczr');
define('DB_USER', 'zeyjsvrczr');
define('DB_PASS', 'nrPBsleknr');
define('DB_CHARSET', 'utf8mb4');

// ============================================
// 安全設定
// ============================================

define('SESSION_NAME', 'KANBAN_SESSION');
define('SESSION_LIFETIME', 86400);  // 24 小時

define('ALLOW_CORS', false);
define('ALLOWED_ORIGINS', [
    'https://yourdomain.com',
]);

// ============================================
// 應用程式設定
// ============================================

define('APP_DEBUG', false);
define('APP_TIMEZONE', 'Asia/Taipei');

// ============================================
// PDO 連線
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
                die(json_encode(['success' => false, 'error' => 'DB Error: ' . $e->getMessage()]));
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

function getDB() {
    return Database::getInstance()->getConnection();
}

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

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

function errorResponse($message, $statusCode = 200) {
    jsonResponse(['success' => false, 'error' => $message], 200);
}

function successResponse($data = [], $message = null) {
    $response = ['success' => true];
    if ($message) $response['message'] = $message;
    if (!empty($data)) $response['data'] = $data;
    jsonResponse($response);
}

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

function cleanInput($data) {
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// ============================================
// SESSION 初始化（最簡單版本）
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

date_default_timezone_set(APP_TIMEZONE);

// OPTIONS 請求處理（CORS 預檢）
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (ALLOW_CORS) {
        http_response_code(200);
        exit;
    }
}
