<?php
/**
 * 登入頁面
 */
require_once 'api/config.php';

// 已登入 → 直接進主頁
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>登入 - 看板系統</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { -webkit-text-size-adjust: 100%; }
:root {
  --bg: #F5F3EE; --surface: #FFFFFF;
  --border-strong: rgba(0,0,0,0.15);
  --text: #1A1A18; --text-secondary: #6B6B66;
  --accent: #534AB7; --accent-hover: #423A95;
  --error: #E24B4A; --radius: 10px;
}
body { font-family: 'Noto Sans TC', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
.login-container { background: var(--surface); border-radius: var(--radius); box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 40px; width: 100%; max-width: 400px; }
.logo { text-align: center; font-size: 24px; font-weight: 700; margin-bottom: 10px; }
.logo span { color: var(--accent); }
.subtitle { text-align: center; font-size: 14px; color: var(--text-secondary); margin-bottom: 30px; }
.form-group { margin-bottom: 20px; }
.form-label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; }
.form-input { width: 100%; padding: 10px 12px; border: 1px solid var(--border-strong); border-radius: var(--radius); font-size: 14px; font-family: inherit; color: var(--text); background: var(--surface); }
.form-input:focus { outline: none; border-color: var(--accent); }
.btn-login { width: 100%; padding: 12px; background: var(--accent); color: white; border: none; border-radius: var(--radius); font-size: 14px; font-weight: 600; font-family: inherit; cursor: pointer; }
.btn-login:hover { background: var(--accent-hover); }
.btn-login:disabled { opacity: 0.5; cursor: not-allowed; }
.error-message { background: #FFF3F2; border: 1px solid var(--error); border-radius: var(--radius); color: var(--error); padding: 10px 12px; font-size: 13px; margin-bottom: 20px; display: none; }
.error-message.show { display: block; }
@media (max-width: 480px) { .login-container { padding: 30px 20px; } }
</style>
</head>
<body>
<div class="login-container">
  <div class="logo">看板<span>系統</span></div>
  <div class="subtitle">專注管理，提升效率</div>
  <div id="error-message" class="error-message"></div>
  <form id="login-form">
    <div class="form-group">
      <label class="form-label">帳號</label>
      <input type="text" id="username" class="form-input" placeholder="請輸入帳號" autocomplete="off" required>
    </div>
    <div class="form-group">
      <label class="form-label">密碼</label>
      <input type="password" id="password" class="form-input" placeholder="請輸入密碼" autocomplete="off" required>
    </div>
    <button type="submit" class="btn-login" id="btn-submit">登入</button>
  </form>
</div>
<script>
const form = document.getElementById('login-form');
const btn  = document.getElementById('btn-submit');
const err  = document.getElementById('error-message');

form.addEventListener('submit', async e => {
  e.preventDefault();
  const username = document.getElementById('username').value.trim();
  const password = document.getElementById('password').value;
  if (!username || !password) { showErr('請輸入帳號和密碼'); return; }
  btn.disabled = true; btn.textContent = '登入中...'; err.classList.remove('show');
  try {
    const res  = await fetch('api/auth.php?action=login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username, password })
    });
    const data = await res.json();
    if (data.success) {
      window.location.href = 'index.php';
    } else {
      showErr(data.error || '登入失敗');
      btn.disabled = false; btn.textContent = '登入';
    }
  } catch(e) {
    showErr('連線錯誤，請稍後再試');
    btn.disabled = false; btn.textContent = '登入';
  }
});
function showErr(msg) { err.textContent = msg; err.classList.add('show'); }
document.getElementById('password').addEventListener('keydown', e => {
  if (e.key === 'Enter') form.dispatchEvent(new Event('submit'));
});
</script>
</body>
</html>
