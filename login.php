<?php
/**
 * 登入頁面
 */
session_start();

// 如果已經登入，轉到主頁
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

/* 修復手機字體縮放導致版面跑掉 */
html {
  -webkit-text-size-adjust: 100%;
  text-size-adjust: 100%;
}

:root {
  --bg: #F5F3EE;
  --surface: #FFFFFF;
  --border: rgba(0,0,0,0.08);
  --border-strong: rgba(0,0,0,0.15);
  --text: #1A1A18;
  --text-secondary: #6B6B66;
  --accent: #534AB7;
  --accent-hover: #423A95;
  --error: #E24B4A;
  --radius: 10px;
}

body {
  font-family: 'Noto Sans TC', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.login-container {
  background: var(--surface);
  border-radius: var(--radius);
  box-shadow: 0 4px 20px rgba(0,0,0,0.08);
  padding: 40px;
  width: 100%;
  max-width: 400px;
}

.logo {
  text-align: center;
  font-size: 24px;
  font-weight: 700;
  margin-bottom: 10px;
  color: var(--text);
}

.logo span {
  color: var(--accent);
}

.subtitle {
  text-align: center;
  font-size: 14px;
  color: var(--text-secondary);
  margin-bottom: 30px;
}

.form-group {
  margin-bottom: 20px;
}

.form-label {
  display: block;
  font-size: 13px;
  font-weight: 500;
  color: var(--text);
  margin-bottom: 6px;
}

.form-input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--border-strong);
  border-radius: var(--radius);
  font-size: 14px;
  font-family: inherit;
  color: var(--text);
  background: var(--surface);
  transition: border-color 0.2s;
}

.form-input:focus {
  outline: none;
  border-color: var(--accent);
}

/* 記住我 */
.remember-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 20px;
}

.remember-row input[type="checkbox"] {
  width: 16px;
  height: 16px;
  accent-color: var(--accent);
  cursor: pointer;
  flex-shrink: 0;
}

.remember-row label {
  font-size: 13px;
  color: var(--text-secondary);
  cursor: pointer;
  user-select: none;
}

.btn-login {
  width: 100%;
  padding: 12px;
  background: var(--accent);
  color: white;
  border: none;
  border-radius: var(--radius);
  font-size: 14px;
  font-weight: 500;
  font-family: inherit;
  cursor: pointer;
  transition: background 0.2s;
}

.btn-login:hover {
  background: var(--accent-hover);
}

.btn-login:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.error-message {
  background: #FFF3F2;
  border: 1px solid var(--error);
  border-radius: var(--radius);
  color: var(--error);
  padding: 10px 12px;
  font-size: 13px;
  margin-bottom: 20px;
  display: none;
}

.error-message.show {
  display: block;
}

.info-box {
  margin-top: 30px;
  padding: 15px;
  background: #F0F9FF;
  border: 1px solid #BFDBFE;
  border-radius: var(--radius);
  font-size: 12px;
  color: #1E40AF;
  line-height: 1.6;
}

.info-box strong {
  display: block;
  margin-bottom: 5px;
}

@media (max-width: 480px) {
  .login-container {
    padding: 30px 20px;
  }
}
</style>
</head>
<body>

<div class="login-container">
  <div class="logo">看板<span>系統</span></div>
  <div class="subtitle">專注管理，提升效率</div>
  
  <div id="error-message" class="error-message"></div>
  
  <form id="login-form">
    <div class="form-group">
      <label class="form-label" for="username">帳號</label>
      <input 
        type="text" 
        id="username" 
        name="username" 
        class="form-input" 
        placeholder="請輸入帳號"
        autocomplete="username"
        required
      >
    </div>
    
    <div class="form-group">
      <label class="form-label" for="password">密碼</label>
      <input 
        type="password" 
        id="password" 
        name="password" 
        class="form-input" 
        placeholder="請輸入密碼"
        autocomplete="current-password"
        required
      >
    </div>

    <!-- 記住我 -->
    <div class="remember-row">
      <input type="checkbox" id="remember_me" name="remember_me">
      <label for="remember_me">記住我（30 天不用重新登入）</label>
    </div>
    
    <button type="submit" class="btn-login" id="btn-submit">登入</button>
  </form>
  
  <div class="info-box">
    <strong>預設帳號資訊：</strong>
    帳號：admin<br>
    密碼：admin123
  </div>
</div>

<script>
const loginForm = document.getElementById('login-form');
const btnSubmit = document.getElementById('btn-submit');
const errorMessage = document.getElementById('error-message');

loginForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const username = document.getElementById('username').value.trim();
  const password = document.getElementById('password').value;
  const rememberMe = document.getElementById('remember_me').checked;
  
  if (!username || !password) {
    showError('請輸入帳號和密碼');
    return;
  }
  
  btnSubmit.disabled = true;
  btnSubmit.textContent = '登入中...';
  hideError();
  
  try {
    const response = await fetch('api/auth.php?action=login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ username, password, remember_me: rememberMe })
    });
    
    const data = await response.json();
    
    if (data.success) {
      // 登入成功，轉到主頁
      window.location.href = 'index.php';
    } else {
      showError(data.error || '登入失敗');
      btnSubmit.disabled = false;
      btnSubmit.textContent = '登入';
    }
    
  } catch (error) {
    showError('連線錯誤，請稍後再試');
    btnSubmit.disabled = false;
    btnSubmit.textContent = '登入';
  }
});

function showError(message) {
  errorMessage.textContent = message;
  errorMessage.classList.add('show');
}

function hideError() {
  errorMessage.classList.remove('show');
}

// Enter 鍵快速登入
document.getElementById('password').addEventListener('keydown', (e) => {
  if (e.key === 'Enter') {
    loginForm.dispatchEvent(new Event('submit'));
  }
});
</script>

</body>
</html>
