<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CRM 系統 - 蘋果印刷</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
  font-family: -apple-system, BlinkMacSystemFont, "Microsoft JhengHei", sans-serif;
  background: #F0F0EE;
}
.nav {
  background: white;
  border-bottom: 1px solid #E0E0E0;
  padding: 1rem 2rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.logo { font-size: 20px; font-weight: 500; color: #2C5F2D; }
.nav-tabs { display: flex; gap: 1rem; }
.nav-tab {
  padding: 8px 16px;
  background: none;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  color: #666;
  text-decoration: none;
}
.nav-tab.active { background: #2C5F2D; color: white; }
.container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }
.welcome {
  background: white;
  border-radius: 12px;
  padding: 3rem;
  text-align: center;
}
.welcome h1 {
  font-size: 32px;
  color: #2C5F2D;
  margin-bottom: 1rem;
}
.welcome p {
  font-size: 16px;
  color: #666;
  margin-bottom: 2rem;
}
.btn {
  display: inline-block;
  padding: 12px 24px;
  background: #2C5F2D;
  color: white;
  border-radius: 8px;
  text-decoration: none;
  font-size: 15px;
}
.btn:hover { background: #1F4420; }
.features {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1.5rem;
  margin-top: 2rem;
}
.feature {
  background: white;
  padding: 2rem;
  border-radius: 12px;
  text-align: center;
}
.feature-icon {
  font-size: 48px;
  margin-bottom: 1rem;
}
.feature-title {
  font-size: 18px;
  font-weight: 500;
  margin-bottom: 0.5rem;
}
.feature-desc {
  font-size: 14px;
  color: #666;
}
</style>
</head>
<body>

<div class="nav">
  <div style="display: flex; align-items: center; gap: 2rem;">
    <div class="logo">🍎 蘋果印刷管理系統</div>
    <div class="nav-tabs">
      <a href="index.php" class="nav-tab">📊 專注看板</a>
      <a href="crm.php" class="nav-tab active">💼 CRM 系統</a>
    </div>
  </div>
  <div>
    <span style="color: #666; font-size: 14px;">👤 <?php echo htmlspecialchars($_SESSION['username']); ?></span>
  </div>
</div>

<div class="container">
  <div class="welcome">
    <h1>🎉 歡迎來到 CRM 客戶管理系統！</h1>
    <p>報價 → 工單 → 出貨 → 發票 完整業務流程管理</p>
    <a href="#" class="btn" onclick="alert('功能開發中，即將推出！'); return false;">+ 新增報價單</a>
  </div>

  <div class="features">
    <div class="feature">
      <div class="feature-icon">💰</div>
      <div class="feature-title">報價管理</div>
      <div class="feature-desc">快速建立專業報價單，支援多品項、自動計算</div>
    </div>
    <div class="feature">
      <div class="feature-icon">🏭</div>
      <div class="feature-title">工單追蹤</div>
      <div class="feature-desc">生產進度一目了然，狀態即時更新</div>
    </div>
    <div class="feature">
      <div class="feature-icon">📦</div>
      <div class="feature-title">出貨管理</div>
      <div class="feature-desc">物流資訊完整記錄，簽收狀態追蹤</div>
    </div>
    <div class="feature">
      <div class="feature-icon">🧾</div>
      <div class="feature-title">發票收款</div>
      <div class="feature-desc">應收帳款清楚掌握，逾期自動提醒</div>
    </div>
  </div>
</div>

</body>
</html>
```

5. **点击「Save」储存**

---

## ✅ 测试 CRM 系统

**现在访问：**
```
https://phpstack-1553960-6296402.cloudwaysapps.com/crm.php
```

**您应该看到：**
```
┌─────────────────────────────────────────┐
│  🍎 蘋果印刷管理系統                     │
│  [📊 專注看板] [💼 CRM 系統] ← 可以切换  │
├─────────────────────────────────────────┤
│                                         │
│  🎉 歡迎來到 CRM 客戶管理系統！          │
│  報價 → 工單 → 出貨 → 發票              │
│                                         │
│        [+ 新增報價單]                    │
│                                         │
│  ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐  │
│  │ 💰   │ │ 🏭   │ │ 📦   │ │ 🧾   │  │
│  │報價  │ │工單  │ │出貨  │ │發票  │  │
│  └──────┘ └──────┘ └──────┘ └──────┘  │
└─────────────────────────────────────────┘
```

---

## 🎯 现在您可以：

### **在两个系统之间切换：**

**FOCUS HD（原系统）：**
```
https://phpstack-1553960-6296402.cloudwaysapps.com/index.php
```

**CRM 系统（新系统）：**
```
https://phpstack-1553960-6296402.cloudwaysapps.com/crm.php