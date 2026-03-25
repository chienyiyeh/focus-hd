-- ============================================
-- 看板系統資料庫結構
-- 版本: 1.0
-- 日期: 2026-03-21
-- ============================================

-- 創建資料庫（如果需要）
-- CREATE DATABASE IF NOT EXISTS kanban_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE kanban_db;

-- ============================================
-- 用戶表（簡單版本）
-- ============================================
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入預設帳號（密碼: admin123）
INSERT INTO users (username, password_hash) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON DUPLICATE KEY UPDATE username=username;

-- ============================================
-- 卡片表（核心資料）
-- ============================================
CREATE TABLE IF NOT EXISTS cards (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL DEFAULT 1,
  col VARCHAR(20) NOT NULL DEFAULT 'lib' COMMENT '欄位: lib/week/focus/done',
  title VARCHAR(255) NOT NULL COMMENT '標題',
  
  -- 新增欄位（完整版）
  project VARCHAR(50) DEFAULT NULL COMMENT '專案類型: seo/product/client/family/sop/finance/other',
  source_link TEXT DEFAULT NULL COMMENT '來源連結',
  summary TEXT DEFAULT NULL COMMENT '一句摘要',
  next_step TEXT DEFAULT NULL COMMENT '下一步行動',
  body TEXT DEFAULT NULL COMMENT '詳細內容',
  
  -- 樣式
  bgcolor VARCHAR(20) DEFAULT NULL COMMENT '背景顏色',
  textcolor VARCHAR(20) DEFAULT NULL COMMENT '文字顏色',
  
  -- 時間戳記
  completed_at DATETIME DEFAULT NULL COMMENT '完成時間',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- 索引
  INDEX idx_user_col (user_id, col),
  INDEX idx_project (project),
  INDEX idx_completed_at (completed_at),
  
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 計時器表（今日專注使用）
-- ============================================
CREATE TABLE IF NOT EXISTS timers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  card_id INT NOT NULL,
  seconds INT NOT NULL DEFAULT 0 COMMENT '已計時秒數',
  is_running TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否執行中',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  UNIQUE KEY unique_card (card_id),
  FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 未來擴充預留表（訂單系統）
-- ============================================

-- 客戶表（未來使用）
CREATE TABLE IF NOT EXISTS customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL COMMENT '客戶名稱',
  phone VARCHAR(20) DEFAULT NULL COMMENT '電話',
  email VARCHAR(100) DEFAULT NULL COMMENT 'Email',
  address TEXT DEFAULT NULL COMMENT '地址',
  notes TEXT DEFAULT NULL COMMENT '備註',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_name (name),
  INDEX idx_phone (phone),
  INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 訂單表（未來使用）
CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(50) NOT NULL UNIQUE COMMENT '訂單編號',
  customer_id INT DEFAULT NULL COMMENT '客戶ID',
  related_card_id INT DEFAULT NULL COMMENT '關聯的看板卡片',
  
  total_amount DECIMAL(10,2) DEFAULT 0 COMMENT '總金額',
  status VARCHAR(20) DEFAULT 'pending' COMMENT '狀態',
  delivery_date DATE DEFAULT NULL COMMENT '交貨日期',
  
  notes TEXT DEFAULT NULL COMMENT '備註',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_order_number (order_number),
  INDEX idx_customer (customer_id),
  INDEX idx_status (status),
  INDEX idx_delivery_date (delivery_date),
  
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  FOREIGN KEY (related_card_id) REFERENCES cards(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 視圖：方便查詢
-- ============================================

-- 所有卡片的完整資訊
CREATE OR REPLACE VIEW v_cards_full AS
SELECT 
  c.*,
  u.username,
  t.seconds as timer_seconds,
  t.is_running as timer_running
FROM cards c
LEFT JOIN users u ON c.user_id = u.id
LEFT JOIN timers t ON c.id = t.card_id;

-- ============================================
-- 初始化完成
-- ============================================
