<?php
/**
 * Google Search Console 排名測試
 */

require_once 'gsc-config.php';

$results = null;
$error = null;

// 測試查詢
if (isset($_GET['test'])) {
    try {
        $keyword = $_GET['keyword'] ?? '急件名片';
        $results = getKeywordRanking($keyword);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>排名追蹤測試</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Microsoft JhengHei", sans-serif;
            background: #f5f5f5;
            padding: 40px 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin: 20px 0;
        }
        
        label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        
        input {
            width: 100%;
            max-width: 400px;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 15px;
        }
        
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 5px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .btn:hover {
            background: #5568d3;
        }
        
        .results {
            margin-top: 30px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 排名追蹤測試</h1>
        
        <form method="GET">
            <div class="form-group">
                <label>測試關鍵字:</label>
                <input type="text" name="keyword" value="<?php echo htmlspecialchars($_GET['keyword'] ?? '急件名片'); ?>" placeholder="例如:急件名片">
            </div>
            
            <button type="submit" name="test" value="1" class="btn">🚀 查詢排名</button>
        </form>
        
        <?php if ($error): ?>
            <div class="error">
                ❌ 錯誤:<?php echo htmlspecialchars($error); ?>
                <br><br>
                <a href="gsc-auth.php">回到授權頁面</a>
            </div>
        <?php endif; ?>
        
        <?php if ($results): ?>
            <div class="results">
                <?php if (empty($results['rows'])): ?>
                    <div class="error">
                        未找到「<?php echo htmlspecialchars($_GET['keyword']); ?>」的排名數據
                        <br><br>
                        可能原因:
                        <ul>
                            <li>這個關鍵字沒有排名</li>
                            <li>數據尚未更新(通常延遲 2-3 天)</li>
                            <li>關鍵字拼寫錯誤</li>
                        </ul>
                    </div>
                <?php else: ?>
                    <div class="success">
                        ✅ 找到 <?php echo count($results['rows']); ?> 筆數據
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>關鍵字</th>
                                <th>網址</th>
                                <th>點擊</th>
                                <th>曝光</th>
                                <th>點擊率</th>
                                <th>平均排名</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results['rows'] as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['keys'][0] ?? '-'); ?></td>
                                    <td style="font-size: 12px; max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars($row['keys'][1] ?? '-'); ?>
                                    </td>
                                    <td><?php echo number_format($row['clicks'] ?? 0); ?></td>
                                    <td><?php echo number_format($row['impressions'] ?? 0); ?></td>
                                    <td><?php echo number_format(($row['ctr'] ?? 0) * 100, 2); ?>%</td>
                                    <td>
                                        <span class="badge <?php echo ($row['position'] ?? 100) <= 10 ? 'badge-success' : 'badge-warning'; ?>">
                                            #<?php echo number_format($row['position'] ?? 0, 1); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
