<?php
/**
 * 使用者管理頁面（僅 admin 可使用）
 */
require_once 'api/config.php';

// 檢查登入
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 只有 admin 可以管理使用者
$db = getDB();
$stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$me = $stmt->fetch();

if (!in_array($me['username'], ['admin', 'chienyi'])) {
    die('您沒有管理權限');
}

$msg = '';
$err = '';

// 新增使用者
if ($_POST['action'] ?? '' === 'add') {
    $newUser = trim($_POST['username'] ?? '');
    $newPass = $_POST['password'] ?? '';
    if (!$newUser || !$newPass) {
        $err = '請輸入帳號和密碼';
    } elseif (strlen($newPass) < 6) {
        $err = '密碼至少 6 個字元';
    } else {
        try {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
            $stmt->execute([$newUser, $hash]);
            $msg = "✅ 使用者「{$newUser}」新增成功";
        } catch (Exception $e) {
            $err = '帳號已存在或新增失敗';
        }
    }
}

// 刪除使用者
if ($_POST['action'] ?? '' === 'delete') {
    $delId = (int)($_POST['user_id'] ?? 0);
    if ($delId === $_SESSION['user_id']) {
        $err = '不能刪除自己';
    } else {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND username != 'admin'");
        $stmt->execute([$delId]);
        $msg = '✅ 使用者已刪除';
    }
}

// 修改密碼
if ($_POST['action'] ?? '' === 'change_pass') {
    $changeId = (int)($_POST['user_id'] ?? 0);
    $newPass  = $_POST['new_password'] ?? '';
    if (strlen($newPass) < 6) {
        $err = '密碼至少 6 個字元';
    } else {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $changeId]);
        $msg = '✅ 密碼已更新';
    }
}

// 取得所有使用者
$users = $db->query("SELECT id, username, created_at FROM users ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>使用者管理</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { -webkit-text-size-adjust: 100%; }
body { font-family: 'Noto Sans TC', sans-serif; background: #F5F3EE; color: #1A1A18; min-height: 100vh; padding: 20px; }
.container { max-width: 600px; margin: 0 auto; }
.header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
.title { font-size: 20px; font-weight: 700; }
.back-btn { padding: 8px 16px; background: #534AB7; color: white; border: none; border-radius: 8px; font-size: 13px; cursor: pointer; text-decoration: none; font-family: inherit; }

.card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 16px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.card-title { font-size: 14px; font-weight: 700; color: #6B6B66; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.5px; }

.msg { padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 13px; }
.msg.ok { background: #E8F5E9; color: #2E7D32; }
.msg.err { background: #FFF3F2; color: #C62828; }

.form-row { display: flex; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
.form-input { flex: 1; min-width: 120px; padding: 10px 12px; border: 1px solid rgba(0,0,0,0.15); border-radius: 8px; font-size: 14px; font-family: inherit; }
.form-input:focus { outline: none; border-color: #534AB7; }
.btn { padding: 10px 16px; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit; white-space: nowrap; }
.btn-primary { background: #534AB7; color: white; }
.btn-danger { background: #FEE2E2; color: #991B1B; }
.btn-sm { padding: 5px 10px; font-size: 12px; }

.user-list { display: flex; flex-direction: column; gap: 10px; }
.user-item { border: 1px solid rgba(0,0,0,0.08); border-radius: 10px; padding: 12px 14px; }
.user-top { display: flex; align-items: center; justify-content: space-between; }
.user-name { font-weight: 600; font-size: 15px; }
.user-date { font-size: 11px; color: #9E9E9E; margin-top: 2px; }
.user-actions { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin-top: 10px; }
.pass-form { display: flex; gap: 6px; flex: 1; min-width: 200px; }
.pass-form input { flex: 1; padding: 6px 10px; border: 1px solid rgba(0,0,0,0.15); border-radius: 6px; font-size: 13px; }
.badge-admin { font-size: 10px; background: #534AB720; color: #534AB7; border: 1px solid #534AB740; border-radius: 4px; padding: 1px 6px; font-weight: 700; margin-left: 6px; }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <div class="title">👥 使用者管理</div>
    <a href="index.php" class="back-btn">← 返回看板</a>
  </div>

  <?php if ($msg): ?>
  <div class="msg ok"><?= htmlspecialchars($msg) ?></div>
  <?php endif; ?>
  <?php if ($err): ?>
  <div class="msg err"><?= htmlspecialchars($err) ?></div>
  <?php endif; ?>

  <!-- 新增使用者 -->
  <div class="card">
    <div class="card-title">新增使用者</div>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div class="form-row">
        <input type="text" name="username" class="form-input" placeholder="帳號名稱" required autocomplete="off">
        <input type="password" name="password" class="form-input" placeholder="密碼（至少6碼）" required>
        <button type="submit" class="btn btn-primary">+ 新增</button>
      </div>
    </form>
  </div>

  <!-- 使用者列表 -->
  <div class="card">
    <div class="card-title">目前使用者（共 <?= count($users) ?> 人）</div>
    <div class="user-list">
      <?php foreach ($users as $u): ?>
      <div class="user-item">
        <div class="user-top">
          <div>
            <span class="user-name"><?= htmlspecialchars($u['username']) ?></span>
            <?php if ($u['username'] === 'admin'): ?>
            <span class="badge-admin">管理員</span>
            <?php endif; ?>
            <div class="user-date">建立：<?= date('Y/m/d', strtotime($u['created_at'])) ?></div>
          </div>
        </div>
        <div class="user-actions">
          <!-- 改密碼 -->
          <form method="POST" class="pass-form">
            <input type="hidden" name="action" value="change_pass">
            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
            <input type="password" name="new_password" placeholder="新密碼" autocomplete="off">
            <button type="submit" class="btn btn-sm btn-primary">改密碼</button>
          </form>
          <!-- 刪除 -->
          <?php if ($u['username'] !== 'admin' && $u['id'] !== $_SESSION['user_id']): ?>
          <form method="POST" onsubmit="return confirm('確定刪除「<?= htmlspecialchars($u['username']) ?>」？')">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
            <button type="submit" class="btn btn-sm btn-danger">刪除</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
</body>
</html>
