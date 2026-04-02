<?php
/**
 * 資料庫檢測工具
 * 用途：檢查看板系統能否讀取 WordPress 資料庫
 * 
 * 使用方式：
 * 1. 上傳到 Cloudways 看板系統根目錄
 * 2. 訪問 https://phpstack-1553960-6296402.cloudwaysapps.com/db-check.php
 * 3. 複製檢測結果給 Claude
 */

// ============================================
// 設定資料庫連線（使用看板系統的帳密）
// ============================================
$db_host = 'localhost';
$db_user = 'zeyjsvrczr';  // 看板系統的資料庫用戶名
$db_pass = 'nrPBsleknr';  // 看板系統的資料庫密碼

// ============================================
// 檢測開始
// ============================================
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>資料庫檢測工具</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #64748b;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .section h2 {
            color: #334155;
            font-size: 18px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status.success {
            background: #dcfce7;
            color: #166534;
        }
        .status.error {
            background: #fee2e2;
            color: #991b1b;
        }
        .status.warning {
            background: #fef3c7;
            color: #92400e;
        }
        .info-box {
            background: white;
            padding: 15px;
            border-radius: 6px;
            margin-top: 10px;
            border: 1px solid #e2e8f0;
        }
        .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .info-row:last-child { border-bottom: none; }
        .info-label {
            font-weight: 600;
            color: #475569;
            width: 180px;
            flex-shrink: 0;
        }
        .info-value {
            color: #0f172a;
            font-family: 'Courier New', monospace;
            word-break: break-all;
        }
        .code-block {
            background: #1e293b;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            margin-top: 10px;
        }
        .highlight {
            background: #fef3c7;
            padding: 2px 6px;
            border-radius: 3px;
            font-weight: 600;
        }
        .table-list {
            max-height: 300px;
            overflow-y: auto;
            margin-top: 10px;
        }
        .table-item {
            padding: 8px 12px;
            background: white;
            margin-bottom: 4px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
            font-family: 'Courier New', monospace;
            font-size: 13px;
        }
        .table-item.wp {
            border-left: 3px solid #0ea5e9;
            background: #f0f9ff;
        }
        .copy-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            margin-top: 10px;
            transition: all 0.2s;
        }
        .copy-btn:hover {
            background: #5568d3;
            transform: translateY(-1px);
        }
        .icon {
            font-size: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 資料庫檢測工具</h1>
        <p class="subtitle">檢查看板系統能否讀取 WordPress 資料庫</p>

        <?php
        try {
            // ============================================
            // 步驟 1: 連接資料庫
            // ============================================
            echo '<div class="section">';
            echo '<h2><span class="icon">🔌</span> 步驟 1: 資料庫連線測試</h2>';
            
            $pdo = new PDO("mysql:host=$db_host", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            echo '<span class="status success">✓ 連線成功</span>';
            echo '<div class="info-box">';
            echo '<div class="info-row"><div class="info-label">主機位址</div><div class="info-value">' . $db_host . '</div></div>';
            echo '<div class="info-row"><div class="info-label">用戶名稱</div><div class="info-value">' . $db_user . '</div></div>';
            echo '</div>';
            echo '</div>';

            // ============================================
            // 步驟 2: 列出所有資料庫
            // ============================================
            echo '<div class="section">';
            echo '<h2><span class="icon">📚</span> 步驟 2: 可用的資料庫列表</h2>';
            
            $stmt = $pdo->query('SHOW DATABASES');
            $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            echo '<div class="info-box">';
            echo '<p style="color: #64748b; margin-bottom: 10px;">找到 <strong>' . count($databases) . '</strong> 個資料庫：</p>';
            
            $wp_db_found = null;
            foreach ($databases as $db) {
                $is_wp = (strpos($db, 'wp') !== false || strpos($db, 'wordpress') !== false);
                $class = $is_wp ? 'wp' : '';
                echo '<div class="table-item ' . $class . '">';
                echo $db;
                if ($is_wp) {
                    echo ' <span class="status warning">← 可能是 WordPress 資料庫</span>';
                    $wp_db_found = $db;
                }
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';

            // ============================================
            // 步驟 3: 檢查 WordPress 資料庫結構
            // ============================================
            if ($wp_db_found) {
                echo '<div class="section">';
                echo '<h2><span class="icon">🎯</span> 步驟 3: WordPress 資料庫驗證</h2>';
                echo '<span class="status success">✓ 發現疑似 WordPress 資料庫</span>';
                
                // 切換到 WordPress 資料庫
                $pdo->exec("USE `$wp_db_found`");
                
                // 列出所有表
                $stmt = $pdo->query('SHOW TABLES');
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // 檢查 WooCommerce 表
                $wc_tables = array_filter($tables, function($table) {
                    return strpos($table, 'woocommerce') !== false 
                        || strpos($table, 'posts') !== false
                        || strpos($table, 'postmeta') !== false;
                });
                
                echo '<div class="info-box">';
                echo '<div class="info-row"><div class="info-label">資料庫名稱</div><div class="info-value highlight">' . $wp_db_found . '</div></div>';
                echo '<div class="info-row"><div class="info-label">總表數量</div><div class="info-value">' . count($tables) . '</div></div>';
                echo '<div class="info-row"><div class="info-label">WooCommerce 相關表</div><div class="info-value">' . count($wc_tables) . '</div></div>';
                echo '</div>';
                
                if (count($wc_tables) > 0) {
                    echo '<p style="margin-top: 15px; color: #64748b;">WooCommerce 相關表：</p>';
                    echo '<div class="table-list">';
                    foreach ($wc_tables as $table) {
                        echo '<div class="table-item wp">' . $table . '</div>';
                    }
                    echo '</div>';
                }
                echo '</div>';

                // ============================================
                // 步驟 4: 測試訂單查詢
                // ============================================
                echo '<div class="section">';
                echo '<h2><span class="icon">🛒</span> 步驟 4: 訂單數據測試</h2>';
                
                // 找出 posts 表的確切名稱
                $posts_table = null;
                foreach ($tables as $table) {
                    if (preg_match('/posts$/i', $table)) {
                        $posts_table = $table;
                        break;
                    }
                }
                
                if ($posts_table) {
                    // 查詢訂單數量
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `$posts_table` WHERE post_type = 'shop_order'");
                    $stmt->execute();
                    $order_count = $stmt->fetchColumn();
                    
                    echo '<span class="status success">✓ 可以讀取訂單資料</span>';
                    echo '<div class="info-box">';
                    echo '<div class="info-row"><div class="info-label">訂單表名稱</div><div class="info-value">' . $posts_table . '</div></div>';
                    echo '<div class="info-row"><div class="info-label">訂單總數</div><div class="info-value highlight">' . number_format($order_count) . ' 筆</div></div>';
                    echo '</div>';
                    
                    // 顯示最新 5 筆訂單
                    if ($order_count > 0) {
                        $stmt = $pdo->prepare("
                            SELECT ID, post_date, post_status 
                            FROM `$posts_table` 
                            WHERE post_type = 'shop_order' 
                            ORDER BY post_date DESC 
                            LIMIT 5
                        ");
                        $stmt->execute();
                        $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        echo '<p style="margin-top: 15px; color: #64748b;">最新 5 筆訂單：</p>';
                        echo '<div class="table-list">';
                        foreach ($recent_orders as $order) {
                            echo '<div class="table-item wp">';
                            echo '訂單 #' . $order['ID'] . ' - ' . $order['post_date'] . ' - ' . $order['post_status'];
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                } else {
                    echo '<span class="status error">✗ 找不到訂單表</span>';
                }
                echo '</div>';

                // ============================================
                // 步驟 5: 生成配置代碼
                // ============================================
                echo '<div class="section">';
                echo '<h2><span class="icon">⚙️</span> 步驟 5: 配置代碼</h2>';
                echo '<p style="color: #64748b; margin-bottom: 10px;">請複製以下配置，發給 Claude：</p>';
                
                $config_code = "// ✅ 檢測結果\n";
                $config_code .= "WordPress 資料庫名稱: $wp_db_found\n";
                $config_code .= "訂單表名稱: $posts_table\n";
                $config_code .= "訂單總數: $order_count 筆\n";
                $config_code .= "看板系統可以讀取: ✓ 是\n\n";
                $config_code .= "// 📝 配置代碼（用於 orders-api.php）\n";
                $config_code .= "define('WP_DB_NAME', '$wp_db_found');\n";
                $config_code .= "define('WP_TABLE_PREFIX', '" . str_replace('posts', '', $posts_table) . "');\n";
                
                echo '<div class="code-block" id="config-code">' . htmlspecialchars($config_code) . '</div>';
                echo '<button class="copy-btn" onclick="copyConfig()">📋 複製配置</button>';
                echo '</div>';

            } else {
                echo '<div class="section">';
                echo '<h2><span class="icon">⚠️</span> 步驟 3: 未找到 WordPress 資料庫</h2>';
                echo '<span class="status warning">! 需要手動確認</span>';
                echo '<div class="info-box">';
                echo '<p style="color: #64748b;">請檢查以下資料庫，哪一個是 WordPress 的：</p>';
                foreach ($databases as $db) {
                    echo '<div class="table-item">' . $db . '</div>';
                }
                echo '</div>';
                echo '</div>';
            }

        } catch (PDOException $e) {
            echo '<div class="section">';
            echo '<h2><span class="icon">❌</span> 連線失敗</h2>';
            echo '<span class="status error">✗ 錯誤</span>';
            echo '<div class="info-box">';
            echo '<div class="info-row"><div class="info-label">錯誤訊息</div><div class="info-value" style="color: #dc2626;">' . htmlspecialchars($e->getMessage()) . '</div></div>';
            echo '</div>';
            echo '<p style="margin-top: 15px; color: #64748b;">請檢查 config.php 中的資料庫帳密是否正確。</p>';
            echo '</div>';
        }
        ?>

    </div>

    <script>
        function copyConfig() {
            const code = document.getElementById('config-code').textContent;
            navigator.clipboard.writeText(code).then(() => {
                const btn = document.querySelector('.copy-btn');
                btn.textContent = '✓ 已複製';
                btn.style.background = '#10b981';
                setTimeout(() => {
                    btn.textContent = '📋 複製配置';
                    btn.style.background = '#667eea';
                }, 2000);
            });
        }
    </script>
</body>
</html>
