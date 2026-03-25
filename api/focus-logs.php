<?php
// 专注时间记录 API
require_once 'config.php';

$user_id = $_SESSION['user_id'] ?? null;
$action = $_GET['action'] ?? '';

// 導出功能需要先處理（在設置 JSON header 之前）
if ($action === 'export' && $user_id) {
    try {
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-d');
        
        $stmt = $pdo->prepare("
            SELECT 
                date as '日期',
                CASE day_of_week
                    WHEN 1 THEN '周一'
                    WHEN 2 THEN '周二'
                    WHEN 3 THEN '周三'
                    WHEN 4 THEN '周四'
                    WHEN 5 THEN '周五'
                    WHEN 6 THEN '周六'
                    WHEN 7 THEN '周日'
                END as '星期',
                TIME(start_time) as '开始时间',
                TIME(end_time) as '结束时间',
                task_title as '任务',
                task_project as '项目',
                ROUND(duration_seconds / 60) as '时长(分钟)'
            FROM focus_logs 
            WHERE user_id = ? 
            AND date BETWEEN ? AND ?
            ORDER BY start_time DESC
        ");
        $stmt->execute([$user_id, $start_date, $end_date]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 設置 CSV header
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="focus_logs_' . $start_date . '_' . $end_date . '.csv"');
        
        // UTF-8 BOM
        echo "\xEF\xBB\xBF";
        
        // 表頭
        $headers = ['日期', '星期', '开始时间', '结束时间', '任务', '项目', '时长(分钟)'];
        echo implode(',', $headers) . "\n";
        
        // 數據行
        if (count($logs) > 0) {
            foreach ($logs as $log) {
                echo implode(',', array_map(function($v) {
                    return '"' . str_replace('"', '""', $v ?? '') . '"';
                }, $log)) . "\n";
            }
        } else {
            echo '"暫無數據","","","","","",""\n';
        }
        exit;
        
    } catch (PDOException $e) {
        header('Content-Type: text/plain; charset=utf-8');
        echo '導出錯誤: ' . $e->getMessage();
        exit;
    }
}

// 其他 API 請求設置 JSON header
header('Content-Type: application/json; charset=utf-8');

// 检查登入
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '未登入']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'start':
            // 开始专注
            $data = json_decode(file_get_contents('php://input'), true);
            $card_id = $data['card_id'] ?? null;
            $task_title = $data['task_title'] ?? '';
            $task_project = $data['task_project'] ?? '';
            
            if (empty($task_title)) {
                echo json_encode(['success' => false, 'error' => '任务标题不能为空']);
                exit;
            }
            
            $start_time = date('Y-m-d H:i:s');
            $date = date('Y-m-d');
            $hour = (int)date('H');
            $day_of_week = (int)date('N'); // 1-7 (周一到周日)
            
            $stmt = $pdo->prepare("
                INSERT INTO focus_logs 
                (user_id, card_id, task_title, task_project, start_time, date, hour, day_of_week) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $card_id, $task_title, $task_project, $start_time, $date, $hour, $day_of_week]);
            
            $log_id = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'log_id' => $log_id,
                'start_time' => $start_time
            ]);
            break;
            
        case 'stop':
            // 结束专注
            $data = json_decode(file_get_contents('php://input'), true);
            $log_id = $data['log_id'] ?? 0;
            $duration_seconds = $data['duration_seconds'] ?? 0;
            
            if (!$log_id) {
                echo json_encode(['success' => false, 'error' => '记录ID不能为空']);
                exit;
            }
            
            $end_time = date('Y-m-d H:i:s');
            
            $stmt = $pdo->prepare("
                UPDATE focus_logs 
                SET end_time = ?, duration_seconds = ? 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$end_time, $duration_seconds, $log_id, $user_id]);
            
            echo json_encode([
                'success' => true,
                'end_time' => $end_time,
                'duration_seconds' => $duration_seconds
            ]);
            break;
            
        case 'stats':
            // 获取统计数据
            $period = $_GET['period'] ?? 'today'; // today, week, month
            
            $stats = [];
            
            // 今日统计
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as count,
                    SUM(duration_seconds) as total_seconds
                FROM focus_logs 
                WHERE user_id = ? AND date = CURDATE()
            ");
            $stmt->execute([$user_id]);
            $stats['today'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 本周统计
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as count,
                    SUM(duration_seconds) as total_seconds
                FROM focus_logs 
                WHERE user_id = ? 
                AND YEARWEEK(date, 1) = YEARWEEK(CURDATE(), 1)
            ");
            $stmt->execute([$user_id]);
            $stats['week'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 本月统计
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as count,
                    SUM(duration_seconds) as total_seconds
                FROM focus_logs 
                WHERE user_id = ? 
                AND YEAR(date) = YEAR(CURDATE())
                AND MONTH(date) = MONTH(CURDATE())
            ");
            $stmt->execute([$user_id]);
            $stats['month'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 本周每日趋势
            $stmt = $pdo->prepare("
                SELECT 
                    date,
                    SUM(duration_seconds) as total_seconds,
                    COUNT(*) as count
                FROM focus_logs 
                WHERE user_id = ? 
                AND YEARWEEK(date, 1) = YEARWEEK(CURDATE(), 1)
                GROUP BY date
                ORDER BY date
            ");
            $stmt->execute([$user_id]);
            $stats['week_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 任务分布（按项目）
            $stmt = $pdo->prepare("
                SELECT 
                    task_project,
                    SUM(duration_seconds) as total_seconds,
                    COUNT(*) as count
                FROM focus_logs 
                WHERE user_id = ? 
                AND YEAR(date) = YEAR(CURDATE())
                AND MONTH(date) = MONTH(CURDATE())
                GROUP BY task_project
                ORDER BY total_seconds DESC
            ");
            $stmt->execute([$user_id]);
            $stats['project_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 时段分析（按小时）
            $stmt = $pdo->prepare("
                SELECT 
                    hour,
                    SUM(duration_seconds) as total_seconds,
                    COUNT(*) as count
                FROM focus_logs 
                WHERE user_id = ? 
                AND YEAR(date) = YEAR(CURDATE())
                AND MONTH(date) = MONTH(CURDATE())
                GROUP BY hour
                ORDER BY hour
            ");
            $stmt->execute([$user_id]);
            $stats['hour_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        case 'recent':
            // 最近记录
            $limit = $_GET['limit'] ?? 10;
            
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    task_title,
                    task_project,
                    start_time,
                    end_time,
                    duration_seconds,
                    date
                FROM focus_logs 
                WHERE user_id = ?
                ORDER BY start_time DESC
                LIMIT ?
            ");
            $stmt->execute([$user_id, (int)$limit]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'logs' => $logs]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => '未知操作']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => '数据库错误: ' . $e->getMessage()]);
}
