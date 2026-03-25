# 📋 看板管理系統 - PHP + MySQL 版

完全獨立的任務看板系統，未來可擴充為工單管理系統。

## ✨ 特色

- ✅ **完全獨立** - 不依賴任何框架或第三方服務
- ✅ **簡單維護** - 只需 PHP + MySQL，任何開發者都能接手
- ✅ **資料掌控** - 所有資料在您自己的資料庫
- ✅ **易於擴充** - 預留訂單系統接口
- ✅ **WordPress 友善** - 提供 API 可與 WordPress 整合
- ✅ **PWA 支援** - 手機體驗如同原生 App

## 📦 系統需求

- PHP 7.4 或以上
- MySQL 5.7 或以上（或 MariaDB 10.2+）
- Web 伺服器（Apache / Nginx）

## 🚀 快速安裝（Cloudways）

### 步驟 1：創建應用程式

1. 登入 Cloudways
2. 新增應用程式
3. 選擇：PHP 8.0 + MySQL
4. 等待部署完成

### 步驟 2：上傳檔案

1. 使用 SFTP 連線到伺服器
2. 將所有檔案上傳到應用程式根目錄
3. 確保檔案結構如下：

```
/public_html/
├── index.php
├── login.php
├── api/
│   ├── config.php
│   ├── auth.php
│   └── cards.php
├── DATABASE.sql
└── README.md
```

### 步驟 3：建立資料庫

1. Cloudways → Application → Access Details
2. 記下資料庫名稱、用戶名、密碼
3. 使用 phpMyAdmin 或 SSH 執行：

```bash
mysql -u 用戶名 -p 資料庫名 < DATABASE.sql
```

或在 phpMyAdmin 中：
- 選擇資料庫
- 匯入 → 選擇 DATABASE.sql
- 執行

### 步驟 4：設定資料庫連線

編輯 `api/config.php`：

```php
define('DB_HOST', 'localhost');
define('DB_NAME', '您的資料庫名稱');
define('DB_USER', '您的資料庫用戶名');
define('DB_PASS', '您的資料庫密碼');
```

### 步驟 5：測試

訪問：`https://您的網域/login.php`

預設帳號：
- 帳號：`admin`
- 密碼：`admin123`

## 🔐 修改預設密碼

登入後，執行以下 SQL 更新密碼：

```sql
-- 生成新密碼雜湊（在 PHP 中執行）
php -r "echo password_hash('您的新密碼', PASSWORD_DEFAULT);"

-- 更新資料庫
UPDATE users 
SET password_hash = '上面生成的雜湊' 
WHERE username = 'admin';
```

## 📚 功能說明

### 四個欄位

1. **策略筆記區（lib）** - 長期規劃、重要資料
2. **本週目標（week）** - 本週要完成的任務
3. **今日專注（focus）** - 今天要處理的事項
4. **已完成（done）** - 完成的任務

### 卡片功能

- 標題、摘要、詳細說明
- 專案分類（SEO / 商品頁 / 客戶 / 其他）
- 來源連結
- 下一步行動
- 自訂背景 & 文字顏色
- 自動記錄完成時間

### 資料管理

- 搜尋卡片
- 專案篩選
- JSON 備份/還原

## 🔌 API 文件

### 認證 API

```
POST /api/auth.php?action=login
POST /api/auth.php?action=logout
GET  /api/auth.php?action=check
```

### 卡片 API

```
GET    /api/cards.php?action=list
POST   /api/cards.php?action=save
DELETE /api/cards.php?action=delete&id=123
POST   /api/cards.php?action=move
```

詳細 API 文件請見 `API-DOCS.md`

## 🎯 未來擴充

系統已預留以下擴充空間：

### 階段 1：看板系統（當前）
- ✅ 任務管理
- ✅ 專案分類
- ✅ 搜尋篩選

### 階段 2：訂單系統
- 客戶管理
- 訂單建立
- 報價計算
- 自動通知

### 階段 3：WordPress 整合
- 客戶線上下單
- 訂單狀態查詢
- 檔案上傳
- 金流串接

資料庫已預留 `customers` 和 `orders` 表，可隨時啟用。

## 🛠️ 疑難排解

### 無法登入

1. 檢查 `api/config.php` 資料庫設定
2. 確認資料庫已匯入 `DATABASE.sql`
3. 檢查 PHP session 是否啟用

### 無法儲存卡片

1. 檢查資料庫連線
2. 確認 `cards` 表存在
3. 查看 PHP error log

### 圖片/樣式無法載入

1. 檢查檔案權限（建議 644）
2. 確認 `.htaccess` 設定正確

## 📞 技術支援

如有問題，請檢查：

1. PHP 版本是否符合需求
2. MySQL 是否正常運作
3. 檔案權限是否正確
4. PHP error log 中的錯誤訊息

## 📝 授權

此系統完全開源，您可以：
- ✅ 自由使用
- ✅ 修改程式碼
- ✅ 商業使用
- ✅ 轉讓給其他開發者維護

## 🎉 開始使用

1. 完成安裝步驟
2. 登入系統
3. 新增第一張卡片
4. 開始管理您的任務！

---

**版本：** 1.0  
**更新日期：** 2026-03-21  
**開發者：** Claude + 您的團隊
