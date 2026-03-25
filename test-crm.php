<?php
// 簡單測試檔案
echo "測試開始...<br><br>";

// 測試 1：PHP 能運行嗎
echo "✅ PHP 正常運行<br>";

// 測試 2：config.php 存在嗎
if (file_exists('config.php')) {
    echo "✅ config.php 存在<br>";
    require_once 'config.php';
    echo "✅ config.php 載入成功<br>";
} else {
    echo "❌ config.php 不存在<br>";
    die();
}

// 測試 3：資料庫連線正常嗎
try {
    $db = getDB();
    echo "✅ 資料庫連線成功<br>";
} catch (Exception $e) {
    echo "❌ 資料庫連線失敗: " . $e->getMessage() . "<br>";
    die();
}

// 測試 4：customers 表存在嗎
try {
    $stmt = $db->query("SHOW TABLES LIKE 'customers'");
    if ($stmt->rowCount() > 0) {
        echo "✅ customers 表存在<br>";
    } else {
        echo "❌ customers 表不存在 → 需要執行 SQL！<br>";
    }
} catch (Exception $e) {
    echo "❌ 查詢失敗: " . $e->getMessage() . "<br>";
}

// 測試 5：quotations 表存在嗎
try {
    $stmt = $db->query("SHOW TABLES LIKE 'quotations'");
    if ($stmt->rowCount() > 0) {
        echo "✅ quotations 表存在<br>";
    } else {
        echo "❌ quotations 表不存在 → 需要執行 SQL！<br>";
    }
} catch (Exception $e) {
    echo "❌ 查詢失敗: " . $e->getMessage() . "<br>";
}

echo "<br>測試完成！";
?>
```

7. **儲存檔案**
8. **打開瀏覽器訪問：**
```
https://phpstack-1553960-6296402.cloudwaysapps.com/test-crm.php
```

---

## 📋 請告訴我您看到什麼？

**可能的結果：**

### **結果 A：看到這個**
```
測試開始...
✅ PHP 正常運行
✅ config.php 存在
✅ config.php 載入成功
✅ 資料庫連線成功
❌ customers 表不存在 → 需要執行 SQL！
❌ quotations 表不存在 → 需要執行 SQL！
測試完成！
```

**→ 這表示：需要執行 SQL！** 我會教您怎麼做！

---

### **結果 B：看到這個**
```
測試開始...
✅ PHP 正常運行
❌ config.php 不存在
```

**→ 這表示：config.php 不在正確位置** 我會幫您修復！

---

### **結果 C：看到這個**
```
測試開始...
✅ PHP 正常運行
✅ config.php 存在
✅ config.php 載入成功
❌ 資料庫連線失敗: ...