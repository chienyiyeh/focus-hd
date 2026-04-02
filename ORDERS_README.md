# 📦 FOCUS 看板系統 - 訂單管理模組

## 🎉 已完成的功能

### ✅ 核心功能
1. **即時訂單同步** - 直接從 WordPress 資料庫讀取 WooCommerce 訂單
2. **統計儀表板** - 總訂單數、總營收、客戶數、回購率
3. **客戶分析** - 法人 vs 個人客戶比例
4. **產品排行** - 熱銷產品 TOP 10（數量 + 金額）
5. **時間篩選** - 本月、本季、本年度、全部
6. **訂單列表** - 完整訂單資訊，支援搜尋

---

## 📁 檔案說明

### 新增檔案（需要上傳）

```
focus-hd/
├── orders-api.php           ← 訂單 API（從 WordPress 讀取資料）
├── orders-dashboard.html    ← 訂單管理儀表板
└── db-check.php            ← 資料庫檢測工具（完成後可刪除）
```

### 已有檔案（需要修改）

```
index.php                    ← 需要加上「訂單管理」導航連結
```

---

## 🚀 部署步驟

### **步驟 1：上傳檔案到 GitHub**

```bash
# 在你的本地 focus-hd 資料夾中
git add orders-api.php orders-dashboard.html
git commit -m "Add orders management system"
git push origin main
```

### **步驟 2：等待 Cloudways 自動同步**

- 通常 1-2 分鐘完成
- 或在 Cloudways 後台手動點擊「Pull」

### **步驟 3：更新 index.php 導航連結**

在 `index.php` 的導航區塊加上：

```html
<a href="orders-dashboard.html" class="nav-link">
    📦 訂單管理
</a>
```

### **步驟 4：測試訪問**

訪問以下網址測試：

```
https://phpstack-1553960-6296402.cloudwaysapps.com/orders-dashboard.html
```

---

## 🔧 API 使用說明

### **1. 取得訂單列表**

```
GET /orders-api.php?action=list&period=month&limit=50
```

**參數說明：**
- `action=list` - 取得訂單列表
- `period` - 時間範圍：all（全部）/ month（本月）/ quarter（本季）/ year（本年）
- `limit` - 每頁筆數（預設 100）
- `offset` - 分頁偏移（預設 0）

**回應範例：**
```json
{
  "success": true,
  "data": {
    "orders": [
      {
        "order_id": "12345",
        "order_date": "2026-04-01 10:30:00",
        "customer_name": "王小明",
        "company": "ABC 公司",
        "customer_type": "business",
        "total": 5000.00,
        "order_status": "wc-completed"
      }
    ],
    "total": 156
  }
}
```

### **2. 取得統計分析**

```
GET /orders-api.php?action=stats&period=month
```

**回應範例：**
```json
{
  "success": true,
  "data": {
    "total_orders": 156,
    "total_revenue": 450000.00,
    "customer_types": {
      "business": { "count": 89, "revenue": 320000 },
      "personal": { "count": 67, "revenue": 130000 }
    },
    "total_customers": 120,
    "repeat_customers": 35,
    "repeat_rate": 29.17
  }
}
```

### **3. 取得產品排行**

```
GET /orders-api.php?action=products&period=month
```

**回應範例：**
```json
{
  "success": true,
  "data": [
    {
      "product_name": "名片印刷",
      "quantity": 450,
      "revenue": 135000.00
    }
  ]
}
```

### **4. 取得單筆訂單詳情**

```
GET /orders-api.php?action=detail&order_id=12345
```

---

## 🔐 安全性說明

### **資料庫帳密保護**

`orders-api.php` 中包含資料庫密碼，請確保：

1. ✅ 檔案權限設為 `644`
2. ✅ 不要將密碼 commit 到公開 GitHub（建議用環境變數）
3. ✅ 定期更換資料庫密碼

### **建議改進（進階）**

將資料庫帳密移到環境變數：

```php
// 改用環境變數（在 Cloudways Application Settings 設定）
define('WP_DB_PASS', getenv('WP_DB_PASSWORD'));
```

---

## 📊 資料庫架構說明

### **WordPress 資料庫**

- **資料庫名稱：** `jdrnmynced`
- **用戶名稱：** `jdrnmynced`
- **主要資料表：**
  - `wp_posts` - 訂單主表（post_type = 'shop_order'）
  - `wp_postmeta` - 訂單詳細資訊
  - `wp_woocommerce_order_items` - 訂單項目
  - `wp_woocommerce_order_itemmeta` - 項目詳細資訊

### **看板系統資料庫**

- **資料庫名稱：** `zeyjsvrczr`
- **用戶名稱：** `zeyjsvrczr`
- **用途：** 目前僅用於看板系統，未來可儲存訂單快照

---

## 🎯 未來擴充功能

### **第一階段（已完成）** ✅
- [x] 訂單列表顯示
- [x] 統計儀表板
- [x] 客戶類型分析
- [x] 產品排行榜
- [x] 時間篩選

### **第二階段（規劃中）**
- [ ] 訂單詳情頁（點擊訂單查看完整資訊）
- [ ] 客戶檔案管理（CRM 功能）
- [ ] 訂單匯出（Excel / PDF）
- [ ] 自動化報表（每週 / 每月自動寄送）

### **第三階段（工單系統）**
- [ ] 訂單轉工單（一鍵建立生產任務）
- [ ] 工單進度追蹤
- [ ] 檔案上傳管理（客戶設計檔）
- [ ] 易印系統整合

---

## ❓ 常見問題

### **Q1：為什麼看不到訂單資料？**

**檢查清單：**
1. WordPress 和看板系統在同一個伺服器嗎？✅ 是
2. 資料庫帳密正確嗎？檢查 `orders-api.php` 中的設定
3. 訪問 `orders-api.php?action=stats` 是否有錯誤訊息？

### **Q2：如何測試 API 是否正常？**

直接訪問以下網址：

```
https://phpstack-1553960-6296402.cloudwaysapps.com/orders-api.php?action=stats
```

應該看到 JSON 格式的統計數據。

### **Q3：訂單資料多久更新一次？**

目前是**即時讀取**，每次打開儀表板都會從 WordPress 資料庫抓取最新資料。

### **Q4：db-check.php 要保留嗎？**

**不需要！** 這只是檢測工具，確認系統正常後請刪除：

```bash
git rm db-check.php
git commit -m "Remove database check tool"
git push origin main
```

---

## 📞 技術支援

如果遇到問題，請提供：

1. 錯誤訊息截圖
2. 訪問 `orders-api.php?action=stats` 的回應
3. 瀏覽器 Console 的錯誤訊息（F12 → Console）

---

## 🎉 完成檢查清單

- [ ] 上傳 `orders-api.php` 和 `orders-dashboard.html`
- [ ] 確認檔案同步到 Cloudways
- [ ] 訪問儀表板，看到訂單資料
- [ ] 測試時間篩選功能
- [ ] 測試搜尋功能
- [ ] 在 `index.php` 加上導航連結
- [ ] 刪除 `db-check.php`（完成測試後）

---

**🚀 部署完成後，你就有一個完整的訂單管理系統了！**

任何問題隨時問我 😊
