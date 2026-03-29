<?php
require_once 'api/config.php';
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Debug</title></head>
<body style="font-family:sans-serif;padding:20px;">
<h2>Session 診斷</h2>
<p>Session Name: <?= session_name() ?></p>
<p>Session ID: <?= session_id() ?></p>
<p>user_id: <?= $_SESSION['user_id'] ?? '❌ 無' ?></p>
<p>username: <?= $_SESSION['username'] ?? '❌ 無' ?></p>
<hr>
<h2>管理按鈕條件</h2>
<p>in_array 結果: <?= in_array($_SESSION['username'] ?? '', ['admin','chienyi']) ? '✅ 符合' : '❌ 不符合' ?></p>
<p>username 值: "<?= htmlspecialchars($_SESSION['username'] ?? '') ?>"</p>
<p>username 長度: <?= strlen($_SESSION['username'] ?? '') ?></p>
<hr>
<h2>快速連結</h2>
<a href="logout.php" style="padding:10px 20px;background:red;color:white;border-radius:8px;text-decoration:none;margin-right:10px;">登出 (小寫)</a>
<a href="Logout.php" style="padding:10px 20px;background:orange;color:white;border-radius:8px;text-decoration:none;margin-right:10px;">登出 (大寫)</a>
<a href="users.php" style="padding:10px 20px;background:blue;color:white;border-radius:8px;text-decoration:none;">使用者管理</a>
</body>
</html>
