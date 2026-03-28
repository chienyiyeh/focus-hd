<?php
session_name('KANBAN_SESSION');
session_start();

if (isset($_GET['write'])) {
    $_SESSION['test_value'] = 'hello_' . time();
    echo "✅ 已寫入 Session，值為：" . $_SESSION['test_value'];
    echo "<br><a href='session-write-test.php'>點這裡測試能不能讀回來</a>";
} else {
    echo "Session 裡的值：" . ($_SESSION['test_value'] ?? '❌ 空的，什麼都沒有');
    echo "<br><a href='session-write-test.php?write=1'>點這裡先寫入</a>";
}

echo "<hr>";
echo "Session ID: " . session_id();
echo "<br>Session 存放路徑: " . session_save_path();
echo "<br>Session 狀態: " . session_status();
