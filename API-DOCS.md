# 📡 API 文件 - WordPress 整合指南

## 🎯 概述

此文件說明如何使用看板系統的 API，特別是如何與 WordPress 整合。

## 🔐 認證機制

系統使用 PHP Session 進行認證。

### 啟用 CORS（跨域請求）

如需從 WordPress 呼叫 API，編輯 `api/config.php`：

```php
define('ALLOW_CORS', true);
define('ALLOWED_ORIGINS', [
    'https://yourdomain.com',
    'https://www.yourdomain.com',
]);
```

## 📋 API 端點

### 基礎 URL

```
https://yourdomain.com/kanban/api/
```

---

## 🔑 認證 API

### 1. 登入

**端點：** `POST /auth.php?action=login`

**請求：**
```json
{
  "username": "admin",
  "password": "admin123"
}
```

**成功回應：**
```json
{
  "success": true,
  "message": "登入成功",
  "data": {
    "user_id": 1,
    "username": "admin"
  }
}
```

**錯誤回應：**
```json
{
  "error": "帳號或密碼錯誤"
}
```

### 2. 檢查登入狀態

**端點：** `GET /auth.php?action=check`

**回應：**
```json
{
  "logged_in": true,
  "user_id": 1,
  "username": "admin"
}
```

### 3. 登出

**端點：** `POST /auth.php?action=logout`

**回應：**
```json
{
  "success": true,
  "message": "登出成功"
}
```

---

## 📇 卡片 API

### 1. 取得所有卡片

**端點：** `GET /cards.php?action=list`

**回應：**
```json
{
  "lib": [
    {
      "id": 1,
      "title": "SEO優化計劃",
      "project": "seo",
      "sourceLink": "https://example.com",
      "summary": "提升網站排名",
      "nextStep": "完成關鍵字研究",
      "body": "詳細說明...",
      "bgcolor": "#E1F5EE",
      "textcolor": "#085041",
      "completedAt": null,
      "createdAt": "2026-03-21 10:00:00",
      "updatedAt": "2026-03-21 10:00:00"
    }
  ],
  "week": [],
  "focus": [],
  "done": []
}
```

### 2. 新增卡片

**端點：** `POST /cards.php?action=save`

**請求：**
```json
{
  "col": "lib",
  "title": "新任務",
  "project": "seo",
  "sourceLink": "https://example.com",
  "summary": "摘要",
  "nextStep": "下一步",
  "body": "詳細內容",
  "bgcolor": "#FFFFFF",
  "textcolor": "#1A1A18"
}
```

**回應：**
```json
{
  "success": true,
  "message": "卡片新增成功",
  "data": {
    "id": 123
  }
}
```

### 3. 更新卡片

**端點：** `POST /cards.php?action=save`

**請求：**（包含 id）
```json
{
  "id": 123,
  "col": "week",
  "title": "更新後的標題",
  "project": "product",
  "summary": "新摘要"
}
```

### 4. 刪除卡片

**端點：** `DELETE /cards.php?action=delete&id=123`

**回應：**
```json
{
  "success": true,
  "message": "卡片刪除成功"
}
```

### 5. 移動卡片

**端點：** `POST /cards.php?action=move`

**請求：**
```json
{
  "id": 123,
  "col": "done"
}
```

**說明：**
- 移動到 `done` 會自動記錄 `completedAt` 時間
- 移動到其他欄位會清除 `completedAt`

---

## 🔗 WordPress 整合範例

### 範例 1：從 WordPress 建立卡片

```php
<?php
// WordPress 頁面或外掛中

function create_kanban_card($title, $project = 'client') {
    $api_url = 'https://yourdomain.com/kanban/api/cards.php?action=save';
    
    $data = array(
        'col' => 'lib',
        'title' => $title,
        'project' => $project,
        'summary' => '來自 WordPress 的訂單',
    );
    
    $response = wp_remote_post($api_url, array(
        'headers' => array('Content-Type' => 'application/json'),
        'body' => json_encode($data),
        'cookies' => $_COOKIE, // 傳遞 session
    ));
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['success'] ?? false;
}

// 使用範例
create_kanban_card('客戶訂單 #1001', 'client');
```

### 範例 2：WooCommerce 訂單自動建立卡片

```php
<?php
// functions.php

add_action('woocommerce_new_order', 'create_card_from_order', 10, 1);

function create_card_from_order($order_id) {
    $order = wc_get_order($order_id);
    
    $title = sprintf(
        '訂單 #%s - %s',
        $order->get_order_number(),
        $order->get_billing_first_name()
    );
    
    $summary = sprintf(
        '商品: %s | 金額: $%s',
        implode(', ', wp_list_pluck($order->get_items(), 'name')),
        $order->get_total()
    );
    
    $api_url = 'https://yourdomain.com/kanban/api/cards.php?action=save';
    
    $data = array(
        'col' => 'week',
        'title' => $title,
        'project' => 'client',
        'summary' => $summary,
        'sourceLink' => $order->get_edit_order_url(),
        'nextStep' => '確認訂單並開始生產',
    );
    
    wp_remote_post($api_url, array(
        'headers' => array('Content-Type' => 'application/json'),
        'body' => json_encode($data),
    ));
}
```

### 範例 3：從 WordPress 表單建立卡片

```php
<?php
// Contact Form 7 或自訂表單提交後

add_action('wpcf7_mail_sent', 'create_card_from_form');

function create_card_from_form($contact_form) {
    $submission = WPCF7_Submission::get_instance();
    $posted_data = $submission->get_posted_data();
    
    $title = '客戶諮詢: ' . $posted_data['your-name'];
    $body = sprintf(
        "姓名: %s\nEmail: %s\n電話: %s\n內容: %s",
        $posted_data['your-name'],
        $posted_data['your-email'],
        $posted_data['your-phone'],
        $posted_data['your-message']
    );
    
    $api_url = 'https://yourdomain.com/kanban/api/cards.php?action=save';
    
    $data = array(
        'col' => 'lib',
        'title' => $title,
        'project' => 'client',
        'summary' => '新的客戶諮詢',
        'body' => $body,
        'nextStep' => '聯繫客戶並提供報價',
    );
    
    wp_remote_post($api_url, array(
        'headers' => array('Content-Type' => 'application/json'),
        'body' => json_encode($data),
    ));
}
```

---

## 📊 資料欄位說明

### 卡片欄位（cards）

| 欄位 | 類型 | 必填 | 說明 |
|------|------|------|------|
| `id` | int | - | 卡片 ID（更新時需要） |
| `col` | string | ✅ | 欄位名稱（lib/week/focus/done） |
| `title` | string | ✅ | 標題 |
| `project` | string | - | 專案類型（seo/product/client/family/sop/finance/other） |
| `sourceLink` | string | - | 來源連結（URL） |
| `summary` | string | - | 一句摘要 |
| `nextStep` | string | - | 下一步行動 |
| `body` | string | - | 詳細說明 |
| `bgcolor` | string | - | 背景顏色（HEX） |
| `textcolor` | string | - | 文字顏色（HEX） |
| `completedAt` | datetime | - | 完成時間（自動記錄） |

### 專案類型（project）

| 值 | 顯示名稱 |
|----|---------|
| `seo` | SEO |
| `product` | 商品頁 |
| `client` | 客戶 |
| `family` | 家庭 |
| `sop` | SOP |
| `finance` | 財務 |
| `other` | 其他 |

### 欄位名稱（col）

| 值 | 說明 |
|----|------|
| `lib` | 策略筆記區 |
| `week` | 本週目標 |
| `focus` | 今日專注 |
| `done` | 已完成 |

---

## 🔒 安全建議

### 1. API 金鑰（未來擴充）

目前使用 Session 認證，未來可加入 API Token：

```php
// 在請求 header 中加入
Authorization: Bearer YOUR_API_TOKEN
```

### 2. IP 白名單

限制只有特定 IP 可以呼叫 API：

```php
// api/config.php
define('ALLOWED_IPS', [
    '123.456.789.0',  // WordPress 伺服器 IP
    '::1',             // localhost
]);
```

### 3. Rate Limiting

防止 API 濫用（未來可加入）

---

## 🚀 進階應用

### 1. 自動化工作流程

```
客戶下單（WordPress）
    ↓
自動建立卡片（看板系統 - 本週目標）
    ↓
完成製作（移動到已完成）
    ↓
自動通知客戶（WordPress）
```

### 2. 雙向同步

```
看板系統更新狀態
    ↓
透過 Webhook 通知 WordPress
    ↓
WordPress 更新訂單狀態
    ↓
自動發送 Email 給客戶
```

### 3. 資料統計

```php
// 取得本週完成的客戶訂單數量
$cards = json_decode(
    file_get_contents('https://yourdomain.com/kanban/api/cards.php?action=list'),
    true
);

$client_orders = array_filter($cards['done'], function($card) {
    return $card['project'] === 'client' 
        && strtotime($card['completedAt']) >= strtotime('-7 days');
});

echo count($client_orders);
```

---

## 📞 支援

如需更多整合協助，請聯繫系統管理員。

---

**版本：** 1.0  
**更新日期：** 2026-03-21
