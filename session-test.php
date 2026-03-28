<?php
require_once 'api/config.php';

echo "<h2>Session 診斷</h2>";
echo "<p>Session Name: " . session_name() . "</p>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>user_id: " . ($_SESSION['user_id'] ?? '❌ 不存在') . "</p>";
echo "<p>username: " . ($_SESSION['username'] ?? '❌ 不存在') . "</p>";
echo "<hr>";
echo "<p>全部 SESSION 內容：<pre>" . print_r($_SESSION, true) . "</pre></p>";
echo "<p>Cookie 內容：<pre>" . print_r($_COOKIE, true) . "</pre></p>";
