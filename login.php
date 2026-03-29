<?php
session_name('KANBAN_SESSION');
session_start();
$_SESSION = [];
session_destroy();
setcookie('remember_token', '', time() - 3600, '/');
header('Location: login.php');
exit;
