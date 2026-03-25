# 🍎 蘋果印刷設計工坊 - 管理系統專案

**最後更新：** 2026-03-24  
**版本：** FOCUS HD v3.1.2 + CRM v1.0.0  
**開發者：** chienyi + Claude  

---

## 📊 系統架構

### **雙系統設計**

```
蘋果印刷管理系統
│
├── 📊 FOCUS HD 專注看板（個人任務管理）
│   └── 策略庫 → 本週 → 今日 → 完成
│
└── 💼 CRM 客戶管理系統（業務流程管理）
    └── 報價 → 工單 → 出貨 → 發票
```

**系統特色：**
- 兩個系統完全獨立運作
- 共用登入系統和資料庫
- 頂部選單可快速切換

---

## 🗂️ 檔案清單

### **主要頁面（/public_html/）**

| 檔案 | 功能 | 版本 | 狀態 |
|------|------|------|------|
| `index.php` | FOCUS HD 看板主頁 | v3.1.2 | ✅ 完成 |
| `crm.php` | CRM 看板主頁 | v1.0.0 | ✅ 完成 |
| `quotation-edit.php` | 報價單新增/編輯 | v1.0.0 | ✅ 完成 |
| `quotation-print.php` | 報價單列印 | v1.0.0 | ✅ 完成 |
| `config.php` | 資料庫配置 | - | ✅ 已複製到根目錄 |
| `login.php` | 登入頁面 | - | ✅ 完成 |

### **API 目錄（/public_html/api/）**

| 檔案 | 功能 | 版本 | 狀態 |
|------|------|------|------|
| `cards.php` | FOCUS HD 卡片 API | v3.1.1 | ✅ 完成 |
| `quotations.php` | 報價單 API | v1.0.0 | ✅ 完成 |
| `notifications.php` | 通知 API | - | ✅ 完成 |
| `projects.php` | 專案 API | - | ✅ 完成 |

### **待建立檔案**

| 檔案 | 功能 | 優先級 |
|------|------|--------|
| `work-order-edit.php` | 工單編輯頁面 | 🔴 高 |
| `api/work-orders.php` | 工單 API | 🔴 高 |
| `delivery-edit.php` | 出貨單頁面 | 🟡 中 |
| `api/deliveries.php` | 出貨 API | 🟡 中 |
| `invoice-edit.php` | 發票頁面 | 🟡 中 |
| `api/invoices.php` | 發票 API | 🟡 中 |

---

## 🗄️ 資料庫結構

**資料庫名稱：** `zeyjsvrczr`

### **FOCUS HD 系統表**

| 表名 | 說明 | 重要欄位 |
|------|------|---------|
| `users` | 用戶表 | id, username, password |
| `cards` | 卡片表 | id, col, title, priority, postponed_count |
| `notifications` | 通知表 | - |
| `projects` | 專案表 | - |

### **CRM 系統表**

| 表名 | 說明 | 重要欄位 |
|------|------|---------|
| `customers` | 客戶主檔 | id, name, company, phone, email |
| `quotations` | 報價單主檔 | id, quotation_number, customer_name, total_amount |
| `quotation_items` | 報價明細 | id, quotation_id, item_name, quantity, unit_price |
| `work_orders` | 工單主檔 | ⏳ 已建立但未使用 |
| `deliveries` | 出貨單主檔 | ⏳ 已建立但未使用 |
| `invoices` | 發票主檔 | ⏳ 已建立但未使用 |
| `invoice_items` | 發票明細 | ⏳ 已建立但未使用 |
| `document_counters` | 單據編號計數器 | doc_type, date_key, counter |

---

## ✅ FOCUS HD 已完成功能

### **核心功能**
- [x] 四欄看板：策略庫 → 本週 → 今日 → 完成
- [x] 卡片拖拽移動
- [x] 計時器功能（今日專注）
- [x] 四象限優先級系統
  - 🔥 重要且緊急
  - ⭐ 重要但不緊急
  - ⚡ 緊急但不重要
  - 💤 不重要不緊急
- [x] 延期管理（本週 → 策略庫）
- [x] 刪除保護（刪除別人的卡片會警告）
- [x] 多用戶支援（chienyi, holly）
- [x] 私人/共用卡片

### **UI/UX**
- [x] 手機版完全滿版
- [x] 字體全面加大（手機版）
- [x] 純閃爍計時動畫（不晃動）
- [x] FOCUS HD 品牌 Logo
- [x] 桌面版側邊編輯器

### **技術細節**
- 版本：v3.1.2
- 強制規則：今日專注 1 個、本週目標最多 3 個
- 資料結構：cards 表包含 priority、postponed_count 欄位

---

## ✅ CRM 系統已完成功能

### **報價管理**
- [x] 報價單新增/編輯
- [x] 動態新增品項（無限新增）
- [x] 自動計算小計、稅額、總計
- [x] 報價單列印（專業格式）
- [x] 自動生成報價單號（Q-2026-0324-001）
- [x] 客戶資料管理

### **報價單功能細節**
- **品項表格：**
  - 品項名稱（必填）
  - 規格說明（多行文字）
  - 數量（必填）
  - 單位（預設：張）
  - 單價（必填）
  - 小計（自動計算）
  - 刪除按鈕
- **計算邏輯：**
  - 小計 = Σ(數量 × 單價)
  - 稅額 = 小計 × 5%
  - 總計 = 小計 + 稅額
- **列印格式：**
  - 公司 Logo 和資訊
  - 客戶資訊卡片
  - 報價資訊（單號、日期、有效期限）
  - 產品明細表格
  - 總計金額醒目顯示
  - 備註說明區塊

---

## 🔄 業務流程設計

### **完整流程圖**

```
💰 報價階段
├── 客戶資訊
├── 產品明細（多品項）
├── 自動計算總價
└── 列印報價單
    ↓
   確認
    ↓
🏭 工單階段（待開發）
├── 自動帶入客戶和產品資訊
├── 生產細節（紙張、印刷方式、加工）
├── 時程管理（開工、完工日期）
└── 生產狀態追蹤
    ↓
  完工
    ↓
📦 出貨階段（待開發）
├── 物流資訊
├── 配送方式
├── 簽收狀態
└── 列印出貨單
    ↓
  已出貨
    ↓
🧾 發票階段（待開發）
├── 自動帶入金額
├── 稅額計算
├── 收款狀態追蹤
└── 列印發票
    ↓
  收款完成
    ↓
✅ 訂單完成
```

### **單據編號規則**

```
報價單：Q-YYYY-MMDD-XXX  例：Q-2026-0324-001
工單：  W-YYYY-MMDD-XXX  例：W-2026-0324-001
出貨單：D-YYYY-MMDD-XXX  例：D-2026-0324-001
發票：  I-YYYY-MMDD-XXX  例：I-2026-0324-001

XXX = 當日流水號（001, 002, 003...）
由 document_counters 表自動管理
```

---

## 🔧 系統配置

### **伺服器資訊**

```
平台：Cloudways (DigitalOcean)
網址：https://phpstack-1553960-6296402.cloudwaysapps.com
PHP：8.x
資料庫：MySQL 8.0
```

### **資料庫連線**

```
Host:     localhost
Database: zeyjsvrczr
Username: zeyjsvrczr
Password: nrPBsleknr
```

### **SFTP 資訊**

```
Host:     146.190.101.127
Port:     22
Username: kanban_sftp
Password: 2024Ts851213
路徑：    /public_html/
```

### **登入帳號**

```
帳號 1：chienyi / d421d421
帳號 2：holly / password
```

---

## 🎨 設計規範

### **顏色配置**

```css
--bg: #F8F8F6;              /* 背景色 */
--surface: #FFFFFF;         /* 卡片背景 */
--border: rgba(0,0,0,0.06); /* 邊框 */

--accent-lib: #7BA5D6;      /* 策略庫藍 */
--accent-week: #9B8FD9;     /* 本週紫 */
--accent-focus: #E8763E;    /* 今日橙 */
--accent-done: #8FBC8F;     /* 完成綠 */

/* CRM 品牌色 */
--crm-primary: #2C5F2D;     /* 蘋果綠 */
```

### **優先級顏色**

```
🔥 重要且緊急：#FF4444（紅色）
⭐ 重要但不緊急：#FF9800（橙色）
⚡ 緊急但不重要：#2196F3（藍色）
💤 不重要不緊急：#9E9E9E（灰色）
```

---

## ⏳ 待開發功能

### **優先級 🔴 高**

1. **工單系統**
   - [ ] work-order-edit.php（工單編輯頁面）
   - [ ] api/work-orders.php（工單 API）
   - [ ] 報價 → 工單轉換功能
   - [ ] 生產狀態追蹤
   - [ ] 工單列印

**預計時間：** 1-2 天

### **優先級 🟡 中**

2. **出貨系統**
   - [ ] delivery-edit.php
   - [ ] api/deliveries.php
   - [ ] 工單 → 出貨轉換
   - [ ] 簽收狀態管理

3. **發票系統**
   - [ ] invoice-edit.php
   - [ ] api/invoices.php
   - [ ] 出貨 → 發票轉換
   - [ ] 收款狀態追蹤

**預計時間：** 2-3 天

### **優先級 🟢 低**

4. **進階功能**
   - [ ] Email 發送（報價單、工單等）
   - [ ] 統計報表
   - [ ] 客戶歷史記錄查詢
   - [ ] 逾期提醒
   - [ ] 手機版 CRM 優化

---

## 🐛 已知問題

### **已解決**
- ✅ config.php 路徑問題（已複製到根目錄）
- ✅ Session 配置問題（已修復）
- ✅ 桌面版側邊編輯器位置問題（已修復）
- ✅ 卡片顯示「未登入」問題（已修復）

### **當前問題**
- 無

---

## 📝 重要技術筆記

### **config.php 配置**
```
⚠️ 重要：config.php 同時存在於兩個位置
- /public_html/config.php（主要）
- /public_html/api/config.php（API 用）

所有 CRM 頁面（crm.php, quotation-edit.php 等）
都引用根目錄的 config.php

API 檔案（api/quotations.php 等）
引用 ../config.php（也是根目錄）
```

### **資料流轉邏輯**
```javascript
// 報價 → 工單（未來實現）
convertQuoteToWorkOrder(quoteId) {
  // 1. 從 quotations 取得客戶和產品資料
  // 2. 自動填入 work_orders 表
  // 3. 生成工單編號 W-YYYY-MMDD-XXX
  // 4. 更新報價單狀態為 'accepted'
}

// 工單 → 出貨（未來實現）
convertWorkOrderToDelivery(workOrderId) {
  // 1. 從 work_orders 取得資料
  // 2. 填入 deliveries 表
  // 3. 生成出貨單號 D-YYYY-MMDD-XXX
}

// 出貨 → 發票（未來實現）
convertDeliveryToInvoice(deliveryId) {
  // 1. 從 deliveries 取得資料
  // 2. 填入 invoices 表
  // 3. 生成發票號碼 I-YYYY-MMDD-XXX
  // 4. 計算稅額
}
```

### **單據編號生成機制**
```php
function generateDocumentNumber($db, $docType) {
    // 使用 document_counters 表
    // 每天重新計數
    // 格式：PREFIX-YYYY-MMDD-XXX
    // 自動遞增，不重複
}
```

---

## 🚀 下次對話要做的事

### **立即執行（下次對話）**

1. **測試報價系統**
   - 建立測試報價單
   - 測試動態新增品項
   - 測試列印功能
   - 確認自動編號

2. **開發工單系統**
   - 建立 work-order-edit.php
   - 建立 api/work-orders.php
   - 實現「報價 → 工單」轉換按鈕
   - 工單列印模板

3. **串接流程**
   - 在報價單詳情頁添加「轉為工單」按鈕
   - 測試資料自動帶入

### **本週目標**

- [ ] 完成工單系統
- [ ] 完成出貨系統
- [ ] 完成發票系統
- [ ] 測試完整業務流程

---

## 💡 開發建議

### **給 Claude 的提示**

**當我說「讀取專案狀態」時：**
1. 用 Google Drive MCP 搜尋「蘋果印刷 CRM」
2. 讀取這份 PROJECT-STATUS.md
3. 了解當前進度
4. 直接繼續開發，無需重新解釋

**當我說「更新專案狀態」時：**
1. 根據當前對話更新這份文檔
2. 更新已完成功能清單
3. 更新待辦事項
4. 輸出新版本給我貼回 Google Drive

### **開發原則**

1. **簡潔優先** - 代碼要簡潔，不要過度複雜
2. **複用優先** - 盡量複用現有代碼邏輯
3. **測試優先** - 每個功能都要能立即測試
4. **文檔同步** - 每次改動都更新此文檔

---

## 📊 專案統計

**總代碼量：** ~5,000 行  
**開發時間：** 3 天  
**完成度：**
- FOCUS HD：100% ✅
- CRM 報價系統：100% ✅
- CRM 完整流程：25% ⏳

**下階段預估：**
- 工單系統：1-2 天
- 出貨+發票：2-3 天
- 總計：3-5 天可完成整個 CRM

---

## 🎯 專案願景

**短期目標（1 週內）：**
完成報價 → 工單 → 出貨 → 發票完整流程

**中期目標（1 個月內）：**
- 統計報表
- Email 自動發送
- 手機版優化

**長期目標（3 個月內）：**
- 客戶管理進階功能
- 財務報表
- 庫存管理

---

**🍎 蘋果印刷，用科技提升效率！**

---

*最後更新：2026-03-24 by Claude*  
*下次更新：完成工單系統後*
