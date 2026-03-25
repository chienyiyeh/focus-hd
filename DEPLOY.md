# 🚀 快速部署指南 - Cloudways 專用

## 📋 部署檢查清單

- [ ] Cloudways 帳號已準備
- [ ] 已創建 PHP 應用程式
- [ ] 已取得資料庫連線資訊
- [ ] 已下載系統檔案
- [ ] 已準備 SFTP 工具（FileZilla / Cyberduck）

---

## 步驟 1：創建 Cloudways 應用程式

1. 登入 https://platform.cloudways.com
2. 點擊「Add Application」
3. 設定：
   - Application Name: `Kanban`
   - Choose Application: `PHP`
   - Application Version: `8.0` 或以上
   - Database Name: `kanban_db`（記下來）
4. 點擊「Add Application」
5. 等待部署完成（約 3-5 分鐘）

---

## 步驟 2：取得連線資訊

### 2.1 SFTP 資訊

在 Cloudways 後台：
1. 選擇您的應用程式
2. 點擊「Access Details」
3. 記下：
   - Host: `xxx.xxx.xxx.xxx`
   - Username: `xxxxxx`
   - Password: `xxxxxx`
   - Port: `22`

### 2.2 資料庫資訊

在同一頁面，記下：
- Database Name: `kanban_db`（或您設定的名稱）
- Username: `xxxxxx`
- Password: `xxxxxx`
- Host: `localhost`

---

## 步驟 3：上傳檔案

### 使用 FileZilla

1. 開啟 FileZilla
2. 輸入連線資訊：
   - Host: `sftp://您的IP`
   - Username: SFTP用戶名
   - Password: SFTP密碼
   - Port: `22`
3. 連線成功後，找到 `/public_html/` 目錄
4. 將以下檔案上傳：

```
上傳到 /public_html/
├── index.php
├── login.php
├── api/
│   ├── config.php
│   ├── auth.php
│   └── cards.php
├── DATABASE.sql
├── README.md
└── API-DOCS.md
```

---

## 步驟 4：設定資料庫

### 方法 A：使用 phpMyAdmin（推薦）

1. Cloudways → Application → Access Details
2. 點擊「Admin Panel」（phpMyAdmin）
3. 登入後，選擇您的資料庫（例如：`kanban_db`）
4. 點擊「Import」
5. 選擇上傳的 `DATABASE.sql`
6. 點擊「Go」
7. 看到「Import has been successfully finished」表示成功

### 方法 B：使用 SSH

1. Cloudways → Application → Access Details
2. 複製 SSH Access 指令
3. 在終端機執行：

```bash
ssh master@xxx.xxx.xxx.xxx -p 22

# 進入應用程式目錄
cd applications/xxxxx/public_html

# 匯入資料庫
mysql -u資料庫用戶名 -p資料庫名稱 < DATABASE.sql
```

---

## 步驟 5：設定資料庫連線

1. 使用 FileZilla 或 Cloudways 內建編輯器
2. 編輯 `/public_html/api/config.php`
3. 修改以下部分：

```php
define('DB_HOST', 'localhost');           
define('DB_NAME', '您的資料庫名稱');       // 例如：kanban_db
define('DB_USER', '您的資料庫用戶名');     // 從 Access Details 取得
define('DB_PASS', '您的資料庫密碼');       // 從 Access Details 取得
```

4. 儲存檔案

---

## 步驟 6：測試安裝

1. 開啟瀏覽器
2. 訪問：`https://您的網域/login.php`
   （或 Cloudways 提供的臨時網址）
3. 使用預設帳號登入：
   - 帳號：`admin`
   - 密碼：`admin123`
4. 登入成功後，應該會看到看板介面

---

## 步驟 7：安全設定（重要！）

### 7.1 修改預設密碼

1. 在終端機執行（產生新密碼雜湊）：

```bash
php -r "echo password_hash('您的新密碼', PASSWORD_DEFAULT);"
```

2. 複製產生的雜湊值
3. 在 phpMyAdmin 執行：

```sql
UPDATE users 
SET password_hash = '剛才產生的雜湊值' 
WHERE username = 'admin';
```

### 7.2 關閉 Debug 模式

編輯 `api/config.php`：

```php
define('APP_DEBUG', false);  // 改為 false
```

---

## 🎉 完成！

現在您可以：

1. ✅ 登入系統
2. ✅ 新增卡片
3. ✅ 管理任務
4. ✅ 開始使用

---

## 🔧 常見問題

### Q: 無法登入？

**A:** 檢查：
1. 資料庫是否成功匯入（檢查 `users` 表是否存在）
2. `api/config.php` 中的資料庫設定是否正確
3. 清除瀏覽器 Cookie 後重試

### Q: 畫面空白？

**A:** 檢查：
1. PHP 錯誤日誌（Cloudways → Application → Logs）
2. 檔案權限是否正確（644 for files, 755 for directories）
3. `api/config.php` 是否有語法錯誤

### Q: 無法儲存卡片？

**A:** 檢查：
1. 資料庫 `cards` 表是否存在
2. 檢查 PHP error log
3. 確認已登入（Session 是否正常）

### Q: 如何綁定自己的網域？

**A:** 在 Cloudways：
1. Domain Management → Add Domain
2. 輸入您的網域
3. 在網域商（如 Cloudflare）設定 A Record 指向 Cloudways IP
4. 等待 DNS 生效（約 1-24 小時）

---

## 📞 需要協助？

如遇到問題：

1. 檢查 Cloudways 日誌
2. 查看 PHP error log
3. 參考 `README.md`
4. 參考 `API-DOCS.md`

---

## 🚀 下一步

- [ ] 修改預設密碼
- [ ] 備份系統檔案
- [ ] 設定定期備份（Cloudways 自動備份）
- [ ] 考慮啟用 SSL（Cloudways 提供免費 SSL）
- [ ] 開始規劃 WordPress 整合

---

**預估部署時間：** 15-30 分鐘  
**難度：** ⭐⭐ (簡單)

**祝您部署順利！** 🎉
