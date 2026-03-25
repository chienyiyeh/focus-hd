<?php
// 測試版 - 超級簡化
session_start();

// 檢查登入
if (!isset($_SESSION['user_id'])) {
    die('未登入');
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

// 資料庫連線
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=zeyjsvrczr;charset=utf8mb4',
        'zeyjsvrczr',
        'nrPBsleknr',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('資料庫連線失敗: ' . $e->getMessage());
}

// 導出功能
if ($action === 'export') {
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    
    // 設置 CSV header
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="focus_logs.csv"');
    
    // UTF-8 BOM
    echo "\xEF\xBB\xBF";
    
    // 表頭
    echo "日期,星期,任務,時長\n";
    
    // 嘗試查詢數據
    try {
        $stmt = $pdo->prepare("
            SELECT date, task_title, duration_seconds 
            FROM focus_logs 
            WHERE user_id = ? 
            AND date BETWEEN ? AND ?
            LIMIT 100
        ");
        $stmt->execute([$user_id, $start_date, $end_date]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($logs) > 0) {
            foreach ($logs as $log) {
                $minutes = round(($log['duration_seconds'] ?? 0) / 60);
                echo "\"{$log['date']}\",\"一\",\"{$log['task_title']}\",\"{$minutes}\"\n";
            }
        } else {
            echo "\"暫無數據\",\"\",\"\",\"\"\n";
        }
    } catch (Exception $e) {
        echo "\"錯誤\",\"\",\"{$e->getMessage()}\",\"\"\n";
    }
    
    exit;
}

// 其他 API
header('Content-Type: application/json');

switch ($action) {
    case 'stats':
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM focus_logs WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'count' => $result['count']]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
        
    case 'start':
        $data = json_decode(file_get_contents('php://input'), true);
        $title = $data['task_title'] ?? '測試任務';
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO focus_logs (user_id, task_title, start_time, date, hour, day_of_week) 
                VALUES (?, ?, NOW(), CURDATE(), HOUR(NOW()), DAYOFWEEK(NOW()))
            ");
            $stmt->execute([$user_id, $title]);
            echo json_encode(['success' => true, 'log_id' => $pdo->lastInsertId()]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
        
    case 'stop':
        $data = json_decode(file_get_contents('php://input'), true);
        $log_id = $data['log_id'] ?? 0;
        $duration = $data['duration_seconds'] ?? 0;
        
        try {
            $stmt = $pdo->prepare("UPDATE focus_logs SET end_time = NOW(), duration_seconds = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$duration, $log_id, $user_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => '未知操作: ' . $action]);
}
