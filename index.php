<?php
// 引入設定檔 (確保 KANBAN_SESSION 一致)
require_once 'api/config.php';

// 處理登出
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    session_destroy();
    setcookie('KANBAN_SESSION', '', time() - 3600, '/');
    setcookie('remember_token', '', time() - 3600, '/');
    setcookie(session_name(), '', time() - 3600, '/');
    // 用 HTML meta 跳轉，不依賴 header()
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    echo '<meta http-equiv="refresh" content="0;url=login.php">';
    echo '<script>window.location.replace("login.php");</script>';
    echo '</head><body></body></html>';
    exit();
}

// 檢查是否登入，沒登入就踢回登入頁
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 取得使用者名稱
$username = $_SESSION['username'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>FOCUS 專注看板</title>

<!-- PWA 支援 -->
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#667eea">
<meta name="description" content="蘋果印刷設計工坊 - 任務管理與專注看板系統">

<!-- iOS Safari PWA 支援 -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="專注看板">
<link rel="apple-touch-icon" href="/icon-192.png">
<link rel="icon" type="image/png" sizes="192x192" href="/icon-192.png">
<link rel="shortcut icon" href="/icon-192.png">

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<!-- Quill ImageResize 模組 -->
<link href="https://unpkg.com/quill-image-resize-module@3.0.0/image-resize.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  html { -webkit-text-size-adjust: 100%; text-size-adjust: 100%; }

  :root {
    --bg: #F5F3EE;
    --surface: #FFFFFF;
    --surface2: #EFEDE8;
    --border: rgba(0,0,0,0.08);
    --border-strong: rgba(0,0,0,0.15);
    --text: #1A1A18;
    --text-secondary: #6B6B66;
    --text-muted: #9E9E99;
    --accent-lib: #1D9E75;
    --accent-lib-bg: #E1F5EE;
    --accent-lib-text: #085041;
    --accent-week: #534AB7;
    --accent-week-bg: #EEEDFE;
    --accent-week-text: #26215C;
    --accent-focus: #D85A30;
    --accent-focus-bg: #FAECE7;
    --accent-focus-text: #712B13;
    --accent-done: #3B6D11;
    --accent-done-bg: #EAF3DE;
    --accent-done-text: #173404;
    --radius: 10px;
    --radius-lg: 14px;
  }

  body { font-family: 'Noto Sans TC', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding: 0; -webkit-font-smoothing: antialiased; padding-bottom: 70px; }

  header { background: var(--surface); border-bottom: 1px solid var(--border); padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; flex-wrap: wrap; gap: 12px; }
  .logo { font-size: 15px; font-weight: 700; letter-spacing: -0.3px; color: var(--text); }
  .logo span { color: var(--accent-focus); }
  
  .header-center { display: flex; gap: 12px; align-items: center; flex: 1; justify-content: center; min-width: 0; }
  .search-box { position: relative; max-width: 300px; width: 100%; flex-shrink: 1; }
  .search-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: var(--surface); border: 1px solid var(--border-strong); border-radius: var(--radius); box-shadow: 0 4px 16px rgba(0,0,0,0.12); z-index: 99; max-height: 320px; overflow-y: auto; display: none; margin-top: 2px; }
  .search-dropdown.show { display: block; }
  .search-result-item { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; }
  .search-result-item:last-child { border-bottom: none; }
  .search-result-item:hover { background: var(--surface2); }
  .search-result-col { font-size: 10px; color: var(--text-muted); flex-shrink: 0; }
  .search-result-title { font-size: 13px; color: var(--text); flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .search-input { width: 100%; padding: 6px 12px 6px 32px; border: 1px solid var(--border-strong); border-radius: var(--radius); font-size: 13px; font-family: inherit; color: var(--text); background: var(--surface); }
  .search-input:focus { outline: none; border-color: var(--accent-week); }
  .search-icon { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 14px; }
  
  .filter-tags { display: flex; gap: 6px; flex-wrap: wrap; }
  .filter-tag { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 500; cursor: pointer; border: 1px solid var(--border); background: var(--surface); color: var(--text-secondary); transition: all 0.12s; white-space: nowrap; }
  .filter-tag:hover { background: var(--surface2); }
  .filter-tag.active { background: var(--accent-week); color: white; border-color: var(--accent-week); }

  .header-actions { display: flex; gap: 8px; align-items: center; flex-wrap: nowrap; }
  .header-user { font-size: 12px; color: var(--text-secondary); padding: 6px 12px; background: var(--surface2); border-radius: var(--radius); white-space: nowrap; }
  
  /* 通知按钮 */
  .notification-btn { position: relative; background: none; border: 1px solid var(--border-strong); border-radius: var(--radius); padding: 6px 12px; font-size: 16px; cursor: pointer; transition: background 0.12s; }
  .notification-btn:hover { background: var(--surface2); }
  .notification-badge { position: absolute; top: -4px; right: -4px; background: #EF4444; color: white; font-size: 10px; font-weight: 600; padding: 2px 5px; border-radius: 10px; min-width: 18px; text-align: center; }
  
  /* 通知弹窗 */
  .notification-panel { position: fixed; top: 60px; right: 20px; width: 380px; max-height: 500px; background: var(--surface); border: 1px solid var(--border-strong); border-radius: var(--radius); box-shadow: 0 8px 24px rgba(0,0,0,0.15); display: none; flex-direction: column; z-index: 10000; }
  .notification-panel.open { display: flex; }
  .notification-header { display: flex; justify-content: space-between; align-items: center; padding: 16px; border-bottom: 1px solid var(--border); }
  .notification-title { font-size: 16px; font-weight: 600; color: var(--text); }
  .notification-actions { display: flex; gap: 8px; align-items: center; }
  .notification-action-btn { background: none; border: none; font-size: 11px; color: var(--accent-lib); cursor: pointer; padding: 4px 8px; }
  .notification-action-btn:hover { text-decoration: underline; }
  .notification-close-btn { background: none; border: none; font-size: 20px; color: var(--text-muted); cursor: pointer; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; border-radius: 4px; }
  .notification-close-btn:hover { background: var(--surface2); }
  .notification-list { flex: 1; overflow-y: auto; max-height: 420px; }
  .notification-empty { padding: 40px 20px; text-align: center; color: var(--text-muted); font-size: 14px; }
  .notification-item { padding: 12px 16px; border-bottom: 1px solid var(--border); cursor: pointer; transition: background 0.12s; }
  .notification-item:hover { background: var(--surface2); }
  .notification-item.unread { background: var(--accent-lib-bg); }
  .notification-item-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px; }
  .notification-item-actor { font-size: 12px; font-weight: 600; color: var(--text); }
  .notification-item-time { font-size: 11px; color: var(--text-muted); }
  .notification-item-message { font-size: 13px; color: var(--text-secondary); line-height: 1.4; }
  
  .help-toggle, .export-btn, .logout-btn, .settings-btn { background: none; border: 1px solid var(--border-strong); border-radius: var(--radius); padding: 6px 12px; font-size: 12px; font-family: inherit; color: var(--text-secondary); cursor: pointer; transition: background 0.12s; white-space: nowrap; }
  .help-toggle:hover, .export-btn:hover, .settings-btn:hover { background: var(--surface2); }
  .help-toggle.active { background: var(--accent-week-bg); color: var(--accent-week-text); border-color: var(--accent-week); }
  
  .export-btn { background: var(--accent-lib-bg); color: var(--accent-lib-text); border-color: var(--accent-lib); font-weight: 500; }
  .export-btn:hover { background: var(--accent-lib); color: white; }
  
  .settings-btn { background: var(--accent-week-bg); color: var(--accent-week-text); border-color: var(--accent-week); }
  .settings-btn:hover { background: var(--accent-week); color: white; }
  
  .logout-btn { background: #FFF3F2; color: #991B1B; border-color: #FCA5A5; }
  .logout-btn:hover { background: #FEE2E2; }
  
  .privacy-filters { display: flex; gap: 6px; margin-left: 12px; }
  .privacy-filter-btn { background: var(--surface2); border: 1px solid var(--border); border-radius: var(--radius); padding: 4px 10px; font-size: 12px; font-family: inherit; color: var(--text-secondary); cursor: pointer; transition: all 0.15s; white-space: nowrap; }
  .privacy-filter-btn:hover { background: var(--surface3); border-color: var(--border-strong); }
  .privacy-filter-btn.active { background: var(--accent-lib-bg); color: var(--accent-lib-text); border-color: var(--accent-lib); font-weight: 500; }

  .main-wrap { display: flex; align-items: start; max-width: 1400px; margin: 0 auto; padding: 24px; gap: 16px; }

  /* Sidebar */
  .sidebar { width: 220px; flex-shrink: 0; background: var(--surface); border-radius: var(--radius-lg); border: 1px solid var(--border); overflow: hidden; display: none; position: sticky; top: 72px; }
  .sidebar.open { display: block; }
  .sidebar-accent { height: 3px; background: var(--accent-week); }
  .sidebar-header { padding: 14px 16px 10px; border-bottom: 1px solid var(--border); }
  .sidebar-title { font-size: 13px; font-weight: 700; color: var(--text); }
  .sidebar-body { padding: 12px 14px 16px; }
  .help-section { margin-bottom: 16px; }
  .help-label { font-size: 10px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px; }
  .help-step { display: flex; gap: 8px; align-items: flex-start; margin-bottom: 7px; }
  .step-num { width: 18px; height: 18px; border-radius: 50%; background: var(--accent-week-bg); color: var(--accent-week-text); font-size: 10px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px; }
  .step-text, .help-tip { font-size: 11px; color: var(--text-secondary); line-height: 1.55; }
  .help-tip { background: var(--surface2); border-radius: var(--radius); padding: 8px 10px; margin-top: 4px; }

  /* Board Area */
  .board-wrap { flex: 1; min-width: 0; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; align-items: start; }
  
  .col { background: var(--surface); border-radius: var(--radius-lg); border: 1px solid var(--border); overflow: hidden; }
  .col.drag-over { border-color: var(--accent-week); background: var(--accent-week-bg); }
  .col-header { padding: 14px 16px 12px; border-bottom: 1px solid var(--border); }
  .col-title-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px; }
  .col-title { font-size: 14px; font-weight: 700; }
  
  .badge { font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 20px; }
  .badge-lib { background: var(--accent-lib-bg); color: var(--accent-lib-text); }
  .badge-week { background: var(--accent-week-bg); color: var(--accent-week-text); }
  .badge-week.full { background: var(--accent-focus-bg); color: var(--accent-focus-text); }
  .badge-focus { background: var(--accent-focus-bg); color: var(--accent-focus-text); }
  .badge-focus.full { background: #FAECE7; color: #712B13; }
  .badge-done { background: var(--accent-done-bg); color: var(--accent-done-text); }
  
  .col-sub { font-size: 11px; color: var(--text-muted); }
  .col-accent { height: 3px; }
  .col-lib .col-accent { background: var(--accent-lib); }
  .col-week .col-accent { background: var(--accent-week); }
  .col-focus .col-accent { background: var(--accent-focus); }
  .col-done .col-accent { background: var(--accent-done); }
  
  .cards-area { padding: 10px; min-height: 120px; }
  .empty { text-align: center; padding: 24px 16px; font-size: 12px; color: var(--text-muted); line-height: 1.6; }

  /* Cards */
  .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 12px; margin-bottom: 8px; margin-top: 22px; cursor: move; transition: all 0.15s; position: relative; overflow: visible; }
  .card.is-project { background: #FFFDF8 !important; border: 1px solid #D4B896; margin-top: 22px !important; overflow: visible !important; }
  .card.is-project .card-top { background: #E8C98A; margin: -12px -12px 10px -12px; padding: 8px 12px; border-radius: calc(var(--radius) - 1px) calc(var(--radius) - 1px) 0 0; position: relative; }
  /* 資料夾標籤：顯示最頂層目標名稱（透過 CSS 變數動態設定）*/
  .card.is-project .card-top::before {
    content: var(--card-tag-label, "專案筆記");
    display: inline-block;
    background: var(--card-tag-bg, #C8922A);
    color: var(--card-tag-color, #FFF8EE);
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.3px;
    padding: 2px 10px;
    border-radius: 6px 6px 0 0;
    position: absolute;
    top: -20px;
    left: 12px;
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .card.is-project .card-top .card-title { color: #4A3520 !important; }
  .card.is-project .card-top .drag-handle { color: rgba(74,53,32,0.4) !important; }
  .card.is-project .card-top .card-actions-menu button { color: #4A3520 !important; border-color: rgba(74,53,32,0.3) !important; background: rgba(74,53,32,0.08) !important; }
  .card:hover { border-color: var(--border-strong); box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
  .card.dragging { opacity: 0.5; cursor: grabbing; }
  .card.open { padding-bottom: 6px; }
  .card.goal-dimmed { opacity: 0.35; transition: opacity 0.2s; }
  .card.goal-dimmed:hover { opacity: 0.7; }
  .card.open .card-body { display: block; }
  .card.open .card-preview { display: none; }
  .card.open .chevron { transform: rotate(180deg); }

  .card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px;
    background: #C8D4DC; margin: -12px -12px 10px -12px; padding: 8px 12px;
    border-radius: calc(var(--radius) - 1px) calc(var(--radius) - 1px) 0 0;
    position: relative;
  }
  .card-top::before { 
    content: "筆記";
    display: inline-block;
    background: #4A7A9B;
    color: #FFF;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.5px;
    padding: 2px 10px;
    border-radius: 6px 6px 0 0;
    position: absolute;
    top: -20px;
    left: 12px;
  }
  .card-top .card-title { color: #1A2C3A !important; }
  .card-top .drag-handle { color: rgba(26,44,58,0.4) !important; }
  .drag-handle { cursor: grab; color: var(--text-muted); font-size: 16px; margin-right: 4px; }
  .card-title { font-size: 14px; font-weight: 500; line-height: 1.45; flex: 1; }
  .card-meta { display: flex; gap: 6px; margin-bottom: 8px; flex-wrap: wrap; align-items: center; }
  
  .project-tag { padding: 3px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; display: inline-block; }
  .project-tag.seo { background: #E3F2FD; color: #1565C0; }
  .project-tag.product { background: #F3E5F5; color: #6A1B9A; }
  .project-tag.client { background: #FFF3E0; color: #E65100; }
  .project-tag.family { background: #FCE4EC; color: #C2185B; }
  .project-tag.sop { background: #E8F5E9; color: #2E7D32; }
  .project-tag.finance { background: #FFF9C4; color: #F57F17; }
  .project-tag.other { background: #ECEFF1; color: #455A64; }

  .completed-time { font-size: 10px; color: var(--text-muted); font-style: italic; }
  .created-by { font-size: 10px; color: var(--text-secondary); background: rgba(102, 126, 234, 0.1); padding: 2px 8px; border-radius: 10px; margin-left: 6px; font-style: italic; }
  .privacy-tag.private { font-size: 10px; color: #C62828; background: rgba(198, 40, 40, 0.1); padding: 2px 8px; border-radius: 10px; margin-left: 6px; font-weight: 500; }
  .source-link { font-size: 11px; color: var(--accent-lib); text-decoration: none; display: inline-flex; align-items: center; gap: 3px; margin-bottom: 6px; }
  .chevron { font-size: 14px; color: var(--text-muted); transition: transform 0.15s; flex-shrink: 0; }
  .card-summary { font-size: 12px; color: var(--text-secondary); line-height: 1.5; margin-bottom: 8px; font-style: italic; }
  .card-next-step { font-size: 12px; color: var(--accent-focus-text); background: var(--accent-focus-bg); padding: 8px; border-radius: 6px; margin-bottom: 8px; line-height: 1.5; }
  
  /* 待办清单样式 */
  .checklist-container { margin-bottom: 12px; }
  .checklist-item { display: flex; align-items: flex-start; gap: 8px; padding: 8px; background: var(--surface2); border-radius: 6px; margin-bottom: 6px; }
  .checklist-item input[type="checkbox"] { margin-top: 3px; cursor: pointer; width: 16px; height: 16px; }
  .checklist-item textarea { flex: 1; border: none; background: transparent; font-size: 13px; padding: 0; font-family: inherit; resize: none; overflow: hidden; min-height: 20px; line-height: 1.5; word-break: break-all; }
  .checklist-item textarea:focus { outline: none; }
  .checklist-item-delete { padding: 2px 8px; background: #FEE2E2; color: #991B1B; border: 1px solid #FCA5A5; border-radius: 4px; font-size: 11px; cursor: pointer; }
  .checklist-item-delete:hover { background: #FEF2F2; }
  .add-checklist-item-btn { width: 100%; padding: 8px; border: 1px dashed var(--border-strong); background: none; border-radius: var(--radius); font-size: 12px; font-family: inherit; color: var(--text-muted); cursor: pointer; }
  .add-checklist-item-btn:hover { background: var(--surface2); color: var(--text-secondary); border-color: var(--text-secondary); }
  
  .card-checklist { background: var(--surface2); border-radius: 6px; padding: 8px; margin-bottom: 8px; }
  .card-checklist-header { font-size: 11px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
  .card-checklist-progress { font-size: 10px; color: var(--accent-lib); background: var(--accent-lib-bg); padding: 2px 6px; border-radius: 10px; }
  .card-checklist-item { display: flex; align-items: center; gap: 6px; padding: 4px 0; font-size: 12px; }
  .card-checklist-item input[type="checkbox"] { cursor: pointer; width: 14px; height: 14px; }
  .card-checklist-item.checked label { text-decoration: line-through; color: var(--text-muted); }
  
  .card-preview { font-size: 12px; line-height: 1.5; margin-bottom: 8px; max-height: 48px; overflow: hidden; position: relative; }
  .card-preview p, .card-preview h1, .card-preview h2, .card-preview h3 { margin: 0; padding: 0; }
  .card-preview .ql-align-center { text-align: center; }
  .card-preview .ql-align-right { text-align: right; }
  .card-preview strong { font-weight: bold; }
  .card-preview em { font-style: italic; }
  .card-preview u { text-decoration: underline; }
  .card-body { display: none; font-size: 12px; line-height: 1.6; margin-bottom: 12px; padding: 10px; background: var(--surface2); border-radius: 6px; }
  .card-body * { max-width: 100%; }
  .card-body p { margin-bottom: 8px; }
  .card-body h1, .card-body h2, .card-body h3 { margin: 12px 0 8px; font-weight: 700; }
  .card-body ul, .card-body ol { margin-left: 20px; margin-bottom: 8px; }
  .card-body .ql-align-center { text-align: center; }
  .card-body .ql-align-right { text-align: right; }
  
  .focus-timer { background: var(--accent-focus-bg); border: 1px solid var(--accent-focus); border-radius: var(--radius); padding: 12px; margin-bottom: 12px; text-align: center; }
  .timer-display { font-size: 28px; font-weight: 700; color: var(--accent-focus); margin-bottom: 10px; font-variant-numeric: tabular-nums; }
  .timer-controls { display: flex; gap: 8px; justify-content: center; }
  .timer-btn { padding: 8px 16px; border: none; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; background: var(--accent-focus); color: white; }
  .timer-btn.secondary { background: var(--surface); color: var(--text-secondary); border: 1px solid var(--border); }

  .card-actions { display: flex; gap: 6px; flex-wrap: wrap; }
  .act-btn { font-size: 11px; font-family: inherit; padding: 6px 10px; border: 1px solid var(--border); background: var(--surface); color: var(--text-secondary); border-radius: 6px; cursor: pointer; transition: all 0.12s; font-weight: 500; }
  .act-btn:hover { background: var(--surface2); border-color: var(--border-strong); }
  .act-btn.focus { background: var(--accent-focus-bg); color: var(--accent-focus-text); border-color: var(--accent-focus); }
  .act-btn.done { background: var(--accent-done-bg); color: var(--accent-done-text); border-color: var(--accent-done); }
  .act-btn.back { background: var(--accent-lib-bg); color: var(--accent-lib-text); border-color: var(--accent-lib); }
  .act-btn.postpone { background: var(--accent-week-bg); color: var(--accent-week-text); border-color: var(--accent-week); }
  .act-btn.del { color: #E24B4A; }

  .col-collapsible { overflow: hidden; max-height: 4000px; transition: max-height 0.35s ease; }
  .col-collapsible.collapsed { max-height: 0 !important; overflow: hidden; }
  .col-resize-handle {
    text-align: center; font-size: 14px; color: var(--text-muted);
    padding: 4px 0; cursor: ns-resize; user-select: none;
    border-top: 1px dashed var(--border); letter-spacing: 4px;
    flex-shrink: 0;
  }
  .col-resize-handle:hover { background: var(--surface2); color: var(--text-secondary); }
  .add-card-btn { width: 100%; padding: 12px; border: 1px dashed var(--border-strong); background: none; border-radius: var(--radius); font-size: 13px; font-weight: 500; font-family: inherit; color: var(--text-muted); cursor: pointer; margin-top: 4px; }
  .add-card-btn:hover { background: var(--surface2); color: var(--text-secondary); border-color: var(--text-secondary); }
  .clear-done-btn { width: 100%; padding: 10px; border: 1px solid var(--border); background: var(--surface); border-radius: var(--radius); font-size: 12px; font-family: inherit; color: var(--text-muted); cursor: pointer; margin-top: 8px; }

  /* Modal & Overlay */
  .overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 100; padding: 16px; overflow-y: auto; }
  .overlay.open { display: flex; }
  .modal { background: var(--surface); border-radius: var(--radius-lg); width: 100%; max-width: calc(100vw - 32px); max-height: calc(100vh - 32px); display: flex; flex-direction: column; margin: auto; overflow: hidden; }
  .modal-header { padding: 16px 20px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
  .modal-title { font-size: 16px; font-weight: 700; }
  .modal-body { padding: 20px; flex: 1; overflow-y: auto; }
  .field { margin-bottom: 16px; position: relative; }
  .field-label { display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 8px; }
  .field-label .optional { font-weight: 400; color: var(--text-muted); font-size: 12px; }
  .field-input, .field-textarea, .field-select { width: 100%; padding: 12px 14px; border: 1px solid var(--border); border-radius: var(--radius); font-family: inherit; font-size: 14px; color: var(--text); background: var(--surface); }
  .field-input:focus, .field-textarea:focus, .field-select:focus { outline: none; border-color: var(--accent-week); }
  .field-textarea { resize: vertical; min-height: 80px; line-height: 1.5; }
  
  /* Word 編輯器預覽區 */
  .word-preview { min-height: 100px; max-height: 150px; overflow-y: auto; border: 1px solid var(--border); border-radius: var(--radius); padding: 12px; background: #FAFAFA; cursor: pointer; transition: all 0.2s; }
  .word-preview:hover { border-color: var(--accent-week); background: var(--surface); }
  .word-preview.empty { color: var(--text-muted); font-style: italic; }
  
  /* 全螢幕 Quill 編輯器 */
  .fullscreen-editor { position: fixed; inset: 0; background: var(--surface); z-index: 200; display: none; flex-direction: column; }
  .fullscreen-editor.open { display: flex; }
  .editor-header { background: var(--surface2); border-bottom: 1px solid var(--border); padding: 12px 20px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
  .editor-title { font-size: 14px; font-weight: 600; }
  .quill-container { flex: 1; display: flex; flex-direction: row; overflow: hidden; }
  .ql-toolbar { border-left: 1px solid var(--border) !important; border-top: none !important; border-bottom: none !important; border-right: none !important; background: var(--surface2); flex-shrink: 0; width: 48px; display: flex; flex-direction: column; align-items: center; padding: 8px 4px; overflow-y: auto; }
  .ql-toolbar .ql-formats { display: flex; flex-direction: column; align-items: center; gap: 2px; margin: 0 0 8px 0 !important; }
  .ql-toolbar button { width: 36px !important; height: 36px !important; display: flex; align-items: center; justify-content: center; border-radius: 6px; }
  .ql-toolbar button:hover { background: var(--border) !important; }
  .ql-toolbar .ql-picker { width: 36px !important; }
  .ql-container { flex: 1; overflow-y: auto; border-right: none !important; border-top: 1px solid var(--border) !important; }
  #editor-container { flex: 1; overflow-y: auto; }
  #editor-container .ql-editor { font-size: 15px; line-height: 1.8; min-height: 300px; }
  .editor-footer { padding: 16px 20px; background: var(--surface2); border-top: 1px solid var(--border); display: flex; gap: 12px; justify-content: flex-end; flex-shrink: 0; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); }
  .editor-footer .modal-btn { padding: 12px 32px; font-size: 15px; font-weight: 700; }
  
  /* 圖片調整與裁切工具 */
  .image-resize-module { position: absolute; }
  .image-resize-module .toolbar { position: absolute; top: -40px; left: 50%; transform: translateX(-50%); background: white; border: 1px solid #ddd; border-radius: 6px; padding: 4px; display: flex; gap: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
  .image-resize-module .toolbar span { cursor: pointer; padding: 6px 10px; border-radius: 4px; font-size: 13px; transition: background 0.2s; }
  .image-resize-module .toolbar span:hover { background: #f0f0f0; }
  .image-resize-module .toolbar .active { background: var(--accent-week); color: white; }
  
  .color-section { margin-bottom: 16px; }
  .color-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 10px; display: block; }
  .swatches { display: flex; gap: 12px; flex-wrap: wrap; }
  .swatch { width: 48px; height: 48px; border-radius: 10px; cursor: pointer; border: 2px solid transparent; }
  .swatch.selected { border-color: var(--accent-week); box-shadow: 0 0 0 2px var(--surface), 0 0 0 4px var(--accent-week); }

  .modal-footer { padding: 16px 20px; border-top: 1px solid var(--border); display: flex; gap: 12px; justify-content: flex-end; flex-shrink: 0; position: sticky; bottom: 0; background: var(--surface); z-index: 10; }
  .modal-btn { padding: 12px 24px; border: none; border-radius: var(--radius); font-family: inherit; font-size: 14px; font-weight: 600; cursor: pointer; }
  .modal-btn.primary { background: var(--accent-week); color: white; }
  .modal-btn.secondary { background: var(--surface2); color: var(--text-secondary); }

  /* 專案管理 Modal */

  /* 專案顏色色票 */
  .proj-color-swatches { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 4px; }
  .proj-color-swatch { width: 20px; height: 20px; border-radius: 50%; cursor: pointer; border: 2px solid transparent; flex-shrink: 0; transition: transform 0.1s; }
  .proj-color-swatch:hover { transform: scale(1.2); }
  .proj-color-swatch.selected { border-color: #333; box-shadow: 0 0 0 2px #fff, 0 0 0 4px #333; }
  .project-edit-form { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 10px; margin-top: 6px; }
  .project-edit-row { display: flex; gap: 6px; align-items: center; margin-bottom: 8px; }
  .project-edit-input { flex: 1; padding: 6px 10px; border: 1px solid var(--border); border-radius: var(--radius); font-size: 13px; font-family: inherit; }
  .project-edit-save { padding: 6px 12px; background: var(--accent-week); color: white; border: none; border-radius: var(--radius); font-size: 12px; cursor: pointer; font-weight: 500; }
  .project-edit-cancel { padding: 6px 12px; background: var(--surface2); color: var(--text-secondary); border: 1px solid var(--border); border-radius: var(--radius); font-size: 12px; cursor: pointer; }
  .project-edit-btn { padding: 5px 10px; background: var(--surface2); color: var(--text-secondary); border: 1px solid var(--border); border-radius: 6px; font-size: 11px; cursor: pointer; }
  .project-list { margin-top: 16px; }
  .project-item { display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--surface2); border-radius: var(--radius); margin-bottom: 8px; }
  .project-info { display: flex; align-items: center; gap: 12px; flex: 1; }
  .project-color-preview { width: 24px; height: 24px; border-radius: 6px; border: 1px solid var(--border); }
  .project-name { font-size: 13px; font-weight: 500; }
  .project-default { font-size: 11px; color: var(--text-muted); font-style: italic; }
  .project-delete { padding: 6px 12px; background: #FEE2E2; color: #991B1B; border: 1px solid #FCA5A5; border-radius: 6px; font-size: 11px; cursor: pointer; }
  .project-delete:hover { background: #FEF2F2; }
  .add-project-form { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
  .add-project-input { flex: 1; min-width: 150px; padding: 8px 12px; border: 1px solid var(--border); border-radius: var(--radius); font-size: 13px; }
  .color-input { width: 60px; height: 38px; border: 1px solid var(--border); border-radius: var(--radius); cursor: pointer; }
  .add-project-btn { padding: 8px 16px; background: var(--accent-week); color: white; border: none; border-radius: var(--radius); font-size: 13px; cursor: pointer; font-weight: 500; }

  #toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background: #222; color: #FFF; padding: 14px 24px; border-radius: 30px; font-size: 14px; font-weight: 500; opacity: 0; transition: opacity 0.2s, transform 0.2s; pointer-events: none; z-index: 200; box-shadow: 0 8px 24px rgba(0,0,0,0.2); text-align: center; white-space: nowrap; }
  #toast.show { opacity: 1; transform: translateX(-50%) translateY(-10px); }

  .export-menu { position: relative; display: inline-block; }
  .export-dropdown { display: none; position: absolute; right: 0; top: 100%; margin-top: 8px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: 0 8px 24px rgba(0,0,0,0.15); min-width: 180px; z-index: 60; overflow: hidden; }
  .export-dropdown.open { display: block; }
  .export-option { padding: 12px 16px; font-size: 13px; color: var(--text-secondary); cursor: pointer; border-bottom: 1px solid var(--border); }
  .export-option:hover { background: var(--surface2); color: var(--text); }
  .export-option-title { font-weight: 600; margin-bottom: 4px; color: var(--text); }
  .export-option-desc { font-size: 11px; color: var(--text-muted); }

  /* 四象限優先級 */
  .priority-grid { display: flex; flex-wrap: nowrap; gap: 6px; margin-top: 4px; }
  .priority-btn { flex: 1; padding: 7px 4px; border: 1.5px solid var(--border-strong); background: var(--surface); border-radius: 8px; font-size: 11px; font-weight: 500; font-family: inherit; cursor: pointer; transition: all 0.12s; text-align: center; color: var(--text-secondary); white-space: nowrap; }
  .priority-btn:hover { background: var(--surface2); }
  .priority-btn.active[data-value="urgent_important"] { background: #FFF0F0; border-color: #FF4444; color: #CC0000; }
  .priority-btn.active[data-value="important_not_urgent"] { background: #FFF8EC; border-color: #FF9800; color: #CC7700; }
  .priority-btn.active[data-value="urgent_not_important"] { background: #EEF5FF; border-color: #2196F3; color: #0D6EBF; }
  .priority-btn.active[data-value="not_urgent_not_important"] { background: #F5F5F5; border-color: #9E9E9E; color: #555555; }

  /* 手機版底部 Tab Bar */
  .mobile-tab-bar { display: none; }

  /* 手機版下拉選單 */
  .mobile-menu-btn { display: flex; align-items: center; justify-content: center; background: none; border: 1px solid var(--border-strong); border-radius: var(--radius); padding: 6px 10px; font-size: 18px; cursor: pointer; flex-shrink: 0; }
  .mobile-dropdown { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999; }
  .mobile-dropdown.open { display: block; }
  .mobile-dropdown-bg { position: absolute; inset: 0; background: rgba(0,0,0,0.5); }
  .mobile-dropdown-panel { position: absolute; top: 0; right: 0; width: 260px; height: 100%; background: var(--surface); display: flex; flex-direction: column; box-shadow: -4px 0 20px rgba(0,0,0,0.15); }
  .mobile-dropdown-header { padding: 20px 16px 16px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
  .mobile-dropdown-title { font-size: 15px; font-weight: 700; }
  .mobile-dropdown-close { background: none; border: none; font-size: 22px; cursor: pointer; color: var(--text-muted); padding: 4px; }
  .mobile-dropdown-item { display: flex; align-items: center; gap: 12px; padding: 16px; border-bottom: 1px solid var(--border); font-size: 15px; font-weight: 500; cursor: pointer; color: var(--text); background: none; border-left: none; border-right: none; border-top: none; font-family: inherit; width: 100%; text-align: left; }
  .mobile-dropdown-item:hover { background: var(--surface2); }
  .mobile-dropdown-item.danger { color: #991B1B; }

  /* 迷你富文字工具列 - 直接顯示在 cornell-b 右側，不依賴任何狀態 */
  .mini-toolbar { display: none; gap: 4px; padding: 6px 8px; background: #111110; border-radius: 8px; margin-bottom: 6px; flex-wrap: wrap; align-items: center; max-width: 100%; }
  .mini-toolbar.show { display: flex; }
  /* cornell-b 工具列：預設隱藏，點筆記區後加 active 才顯示 */
  .cornell-b .mini-toolbar { display: none; }
  .cornell-b .mini-toolbar.active { display: flex !important; }
  .cornell-b .mini-toolbar.hidden { display: none !important; }
  /* 所有欄位卡片預設收合，只用 card-collapse-body 控制 */
  .card .card-collapse-body { display: none; }
  .card.open .card-collapse-body { display: block; }
  .mini-tb-btn { padding: 4px 10px; border: none; border-radius: 5px; background: transparent; font-size: 14px; cursor: pointer; font-family: inherit; color: #FFF; line-height: 1; }
  .mini-tb-btn:hover { background: rgba(255,255,255,0.2); }
  .mini-tb-sep { width: 1px; height: 16px; background: rgba(255,255,255,0.2); margin: 0 2px; flex-shrink: 0; }
  .mini-tb-color { width: 24px; height: 24px; border: 2px solid rgba(255,255,255,0.4); border-radius: 4px; cursor: pointer; padding: 0; background: #E24B4A; }
  .note-editable { width: 100%; min-height: 120px; max-height: 400px; overflow-y: auto; border: none; background: transparent; font-family: inherit; font-size: 12px; line-height: 1.6; color: var(--text); outline: none; touch-action: auto; -webkit-user-select: text; user-select: text; -webkit-touch-callout: default; }
  .note-editable:focus { background: #FAFFF8; border-radius: 4px; padding: 4px; }
  .note-editable:empty:before { content: attr(placeholder); color: var(--text-muted); font-style: italic; pointer-events: none; }
  /* 分頁線：編輯器內顯示虛線 */
  .page-break-divider {
    display: block;
    width: 100%;
    height: 28px;
    line-height: 28px;
    text-align: center;
    font-size: 10px;
    font-weight: 600;
    color: #6366f1;
    letter-spacing: 0.5px;
    background: transparent;
    border-top: 2px dashed #6366f1;
    border-bottom: none;
    border-left: none;
    border-right: none;
    outline: none !important;
    box-shadow: none !important;
    margin: 12px 0;
    cursor: default;
    user-select: none;
    -webkit-user-select: none;
    pointer-events: none;
  }
  .page-break-divider::before {
    content: '── 列印時換頁 ──';
  }
  @media print {
    .page-break-divider { page-break-after: always; break-after: always; border: none; height: 0; margin: 0; visibility: hidden; }
    .page-break-divider::before { display: none; }
  }

  /* 行內直接編輯 */
  .cornell-note-input { width: 100%; border: none; background: transparent; font-family: inherit; font-size: 12px; line-height: 1.6; color: var(--text); resize: none; outline: none; min-height: 80px; }
  .cornell-note-input:focus { background: #FAFFF8; border-radius: 4px; padding: 4px; }
  .cornell-note-input::placeholder { color: var(--text-muted); font-style: italic; }
  .cornell-add-item { display: flex; gap: 6px; margin-top: 8px; align-items: center; width: 100%; }
  .cornell-add-input { flex: 1; min-width: 0; border: 1px dashed #E8763E; border-radius: 6px; padding: 8px; font-size: 13px; font-family: inherit; background: var(--surface); color: var(--text); outline: none; width: 100%; }
  .cornell-add-input:focus { border-color: var(--accent-lib); border-style: solid; }
  .cornell-add-btn { padding: 8px 12px; border: none; background: #E8763E; color: white; border-radius: 6px; font-size: 14px; font-weight: 700; cursor: pointer; white-space: nowrap; flex-shrink: 0; }
  .card-checklist-item .del-item { opacity: 0; background: none; border: none; color: #E24B4A; font-size: 14px; cursor: pointer; padding: 0 2px; line-height: 1; }
  .card-checklist-item:hover .del-item { opacity: 1; }
  /* 康乃爾裡的 checkbox */
  .cornell-a .card-checklist-item input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; flex-shrink: 0; accent-color: var(--accent-lib); }
  .cornell-a .card-checklist-item { gap: 7px; padding: 5px 2px; border-bottom: 1px solid var(--border); }
  .cornell-a .card-checklist-item:last-of-type { border-bottom: none; }
  .cornell-a .card-checklist-item label { font-size: 12px; line-height: 1.4; cursor: pointer; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block; }
  .checklist-text-edit { flex: 1; border: none; background: transparent; font-size: 12px; font-family: inherit; color: var(--text); padding: 0; outline: none; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; cursor: text; }
  .checklist-text-edit:focus { background: var(--surface); border-radius: 4px; padding: 2px 4px; border: 1px solid var(--accent-lib); white-space: normal; overflow: visible; width: 100%; box-sizing: border-box; }
  .checklist-text-edit:focus ~ .subtask-menu-btn { display: none; }
  /* 編輯待辦時左欄展開到 70% */
  /* 編輯筆記時右欄展開到 80% */

  .cornell-a { transition: width 0.2s; }
  .cornell-b { transition: width 0.2s; flex: 1; }
  .card-checklist-item.checked .checklist-text-edit { text-decoration: line-through; color: var(--text-muted); }
  .cornell-a .card-checklist-item.checked label { text-decoration: line-through; color: var(--text-muted); }
  .saving-indicator { font-size: 10px; color: var(--accent-lib); position: absolute; right: 8px; top: 8px; display: none; }
  .saving-indicator.show { display: block; }
  .subtask-menu-btn { background: none; border: 1px solid var(--border); border-radius: 10px; font-size: 12px; cursor: pointer; padding: 2px 7px; color: var(--text-muted); flex-shrink: 0; }
  .subtask-menu-btn:hover { background: var(--surface2); color: var(--text); }
  .inline-item-btn { background: none; border: none; cursor: pointer; line-height: 1; flex-shrink: 0; }
  /* 今日專注：實心橘底白字，更清楚 */
  .inline-item-btn.focus-btn {
    font-size: 11px;
    font-weight: 700;
    padding: 4px 10px;
    background: var(--accent-focus);
    border: none;
    color: #FFF;
    border-radius: 6px;
    white-space: nowrap;
    margin-right: 8px;
  }
  .inline-item-btn.focus-btn:hover,
  .inline-item-btn.focus-btn:active { opacity: 0.85; }
  .inline-item-btn.del-btn { font-size: 16px; color: #E24B4A; opacity: 0.7; padding: 0 4px; margin-left: 4px; }
  .inline-item-btn.del-btn:hover,
  .inline-item-btn.del-btn:active { opacity: 1; }
  .subtask-dropdown { display: none; position: fixed; background: var(--surface); border: 1px solid var(--border-strong); border-radius: var(--radius); box-shadow: 0 4px 16px rgba(0,0,0,0.15); min-width: 160px; z-index: 500; overflow: hidden; }
  .subtask-dropdown.open { display: block; }
  .subtask-dropdown-item { display: flex; align-items: center; gap: 10px; padding: 12px 14px; font-size: 13px; font-weight: 500; cursor: pointer; border-bottom: 1px solid var(--border); color: var(--text); background: none; border-left: none; border-right: none; border-top: none; font-family: inherit; width: 100%; text-align: left; }
  .subtask-dropdown-item:hover { background: var(--surface2); }
  .subtask-dropdown-item.danger { color: #E24B4A; }
  .subtask-dropdown-item:last-child { border-bottom: none; }

  /* 康乃爾筆記格式 */
  .cornell-layout { display: block; border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; margin-bottom: 8px; }
  /* 預設上下排列（手機桌機一致） */
  .cornell-top { display: flex; flex-direction: column; border-bottom: 1px solid var(--border); }
  .cornell-a { width: 100%; border-right: none; border-bottom: 1px solid var(--border); padding: 8px; background: var(--surface2); overflow: hidden; }
  .cornell-b { flex: 1; padding: 10px 10px 10px 14px; font-size: 12px; line-height: 1.8; overflow-wrap: break-word; cursor: text; position: relative;
    background-image: repeating-linear-gradient(
      to bottom,
      transparent,
      transparent calc(1.8em - 1px),
      rgba(0,0,0,0.15) calc(1.8em - 1px),
      rgba(0,0,0,0.15) 1.8em
    );
    background-size: 100% 1.8em;
  }
  /* 編輯模式才左右康乃爾並排 */
  .cornell-layout.edit-mode .cornell-top { flex-direction: row; }
  .cornell-layout.edit-mode .cornell-a { width: 45%; border-right: 1px solid var(--border); border-bottom: none; }
  .cornell-b * { max-width: 100%; }
  .cornell-b p { margin-bottom: 6px; }
  .cornell-label { font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
  .cornell-c { padding: 8px 12px; background: #FFFEF0; border-top: 2px solid var(--accent-week); font-size: 12px; color: var(--text-secondary); font-style: italic; line-height: 1.5; }
  .cornell-c-label { font-size: 10px; font-weight: 700; color: var(--accent-week-text); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }

  /* 卡片動作選單 */
  .card-actions-menu { position: relative; display: inline-block; margin-left: auto; flex-shrink: 0; }
  .card-actions-toggle { background: var(--surface2); border: 1px solid var(--border-strong); border-radius: 20px; padding: 5px 14px; font-size: 16px; color: var(--text); cursor: pointer; line-height: 1; margin-left: 8px; font-weight: 700; }
  .card-actions-toggle:hover { background: var(--surface2); color: var(--text); }
  .card-actions-dropdown { display: none; position: absolute; right: 0; top: 100%; margin-top: 4px; background: var(--surface); border: 1px solid var(--border-strong); border-radius: var(--radius); box-shadow: 0 4px 16px rgba(0,0,0,0.12); min-width: 140px; z-index: 50; overflow: hidden; }
  .card-actions-dropdown.open { display: block; }
  .card-action-item { display: block; width: 100%; padding: 10px 14px; font-size: 13px; font-family: inherit; font-weight: 500; text-align: left; border: none; background: none; cursor: pointer; color: var(--text-secondary); }
  .card-action-item:hover { background: var(--surface2); color: var(--text); }
  .card-action-item.danger { color: #E24B4A; }
  .card-action-item.danger:hover { background: #FEE2E2; }
  .card-action-item.primary { color: var(--accent-week-text); }
  .card-action-item.done-btn { color: var(--accent-done-text); }

  /* 卡片上的優先級標籤 */
  .priority-tag { padding: 2px 7px; border-radius: 10px; font-size: 10px; font-weight: 600; display: inline-block; }

  /* 響應式 */
  @media (max-width: 1024px) { 
    .board-wrap { grid-template-columns: repeat(2, minmax(0, 1fr)); } 
  }

  @media (max-width: 768px) { 
    body { background: var(--surface2); }

    /* Header 簡化：Logo 左、搜尋中、漢堡右，單行排列 */
    header { 
      padding: 10px 12px; 
      gap: 8px; 
      flex-wrap: nowrap;
      align-items: center;
    }
    .logo { flex-shrink: 0; font-size: 16px; }
    .header-center { 
      flex: 1; 
      min-width: 0;
      margin: 0;
      display: flex;
      align-items: center;
    }
    .search-box { max-width: 100%; width: 100%; }
    .header-actions { flex-shrink: 0; gap: 6px; }

    /* 手機版隱藏不需要的元素 */
    .header-user { display: none !important; }
    .filter-tags { display: none !important; }
    .privacy-filters { display: none !important; }
    .header-actions .help-toggle,
    .header-actions .export-btn,
    .header-actions .logout-btn,
    .header-actions .settings-btn,
    .header-actions #notification-sound-toggle,
    .header-actions .notification-btn { display: none !important; }

    /* 手機版專案篩選列 */
    .mobile-filter-bar {
      display: flex;
      gap: 6px;
      padding: 6px 16px 8px;
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      scrollbar-width: none;
      background: var(--surface);
      border-bottom: 1px solid var(--border);
    }
    .mobile-filter-bar::-webkit-scrollbar { display: none; }
    .mobile-filter-bar .filter-tag {
      white-space: nowrap;
      flex-shrink: 0;
    }

    /* 漢堡按鈕顯示 */


    .sidebar { width: 100%; position: relative; top: 0; border-radius: 0; border-left: none; border-right: none; margin-bottom: 0; }
    .main-wrap { flex-direction: column; padding: 0 !important; gap: 0; }
    .col-header { padding: 14px 16px; }
    .cards-area { padding: 8px 16px; }
    body { padding-bottom: 70px !important; }
    .board-wrap { display: block !important; grid-template-columns: none !important; gap: 0 !important; padding: 0 !important; }
    .board-wrap .col { display: none !important; width: 100vw !important; max-width: 100vw !important; border-radius: 0 !important; border-left: none !important; border-right: none !important; box-shadow: none !important; }
    .board-wrap .col.active { display: block !important; }

    /* 底部 Tab Bar */
    .mobile-tab-bar {
      display: grid !important;
      position: fixed;
      bottom: 0; left: 0; right: 0;
      grid-template-columns: repeat(auto-fit, minmax(60px, 1fr));
      height: 64px;
      background: var(--surface);
      border-top: 1px solid var(--border);
      z-index: 100;
      box-shadow: 0 -2px 10px rgba(0,0,0,0.06);
    }
    .mobile-tab {
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      gap: 3px; border: none; background: none; cursor: pointer;
      color: var(--text-muted); font-family: inherit;
    }
    .mobile-tab-icon { font-size: 22px; line-height: 1; }
    .mobile-tab-label { font-size: 11px; font-weight: 500; }
    .mobile-tab.active { color: var(--accent-focus); background: var(--accent-focus-bg); }

    .card { padding: 14px; border-radius: 0 !important; margin-bottom: 0 !important; border-left: none !important; border-right: none !important; border-top: none !important; border-bottom: 1px solid var(--border) !important; }
    .act-btn { padding: 7px 10px; font-size: 13px; white-space: nowrap; flex-shrink: 0; }
    .card-actions { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
    .card-actions::-webkit-scrollbar { display: none; }
    .cornell-top { flex-direction: column; }
    .cornell-a { width: 100% !important; border-right: none; border-bottom: 1px solid var(--border); }
    .cornell-b { width: 100%; }
    #mobile-logout-btn { display: block !important; }
    #float-toolbar { display: flex !important; }
  }

  /* ==========================================
     戰略樹（左側收折面板）
  ========================================== */
  /* 側邊小標籤（面板收折時顯示） */
  .goal-panel-tab {
    position: sticky; top: 16px; align-self: flex-start; flex-shrink: 0;
    width: 24px; display: none; flex-direction: column; align-items: center; cursor: pointer; z-index: 10;
  }
  .goal-panel-tab-inner {
    writing-mode: vertical-lr;
    text-orientation: mixed;
    background: linear-gradient(180deg, #1A1A18 0%, #2d2d2a 100%);
    color: #FFD700; font-size: 11px; font-weight: 700; padding: 12px 5px;
    border-radius: 0 var(--radius) var(--radius) 0;
    border: 1px solid rgba(255,215,0,0.3); border-left: none;
    white-space: nowrap; letter-spacing: 1px; user-select: none;
  }
  .goal-panel-tab-inner:hover { background: linear-gradient(135deg, #2d2d2a 0%, #3d3d38 100%); }
  .goal-panel-tab.visible { display: flex; }

  .goal-panel {
    flex-shrink: 0; background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius); display: flex; flex-direction: column;
    align-self: flex-start; position: sticky; top: 16px;
    max-height: calc(100vh - 100px); overflow: hidden;
    transition: width 0.25s ease, max-width 0.25s ease, opacity 0.2s ease;
    width: 560px; max-width: 560px; min-width: 260px;
  }
  .goal-panel.size-lg { width: 560px; max-width: 560px; }
  .goal-panel.size-md { width: 340px; max-width: 340px; }
  .goal-panel.size-hidden { width: 0; max-width: 0; min-width: 0; opacity: 0; pointer-events: none; border: none; overflow: hidden; }
  .goal-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(135deg, #1A1A18 0%, #2d2d2a 100%);
    border-radius: calc(var(--radius) - 1px) calc(var(--radius) - 1px) 0 0;
    flex-shrink: 0;
    gap: 8px;
  }
  .goal-panel-title { font-size: 13px; font-weight: 700; color: #FFD700; white-space: nowrap; overflow: hidden; letter-spacing: 0.5px; }
  .goal-panel-toggle { background: none; border: none; cursor: pointer; font-size: 16px; color: rgba(255,255,255,0.7); padding: 2px 4px; border-radius: 4px; flex-shrink: 0; transition: transform 0.25s; line-height: 1; }
  .goal-panel-body { overflow-y: auto; flex: 1; padding: 8px; }

  /* 年度卡 - 最大最醒目，金色邊框 */
  .goal-year-card { background: linear-gradient(135deg, #1A1A18 0%, #2d2d2a 100%); border-radius: var(--radius); margin-bottom: 10px; overflow: hidden; border: 2px solid rgba(255,215,0,0.5); box-shadow: 0 2px 8px rgba(255,215,0,0.1); }
  .goal-year-header { display: flex; align-items: center; gap: 8px; padding: 11px 12px; cursor: pointer; user-select: none; background: rgba(255,215,0,0.05); }
  .goal-year-header:hover { background: rgba(255,215,0,0.1); }
  .goal-year-chevron { font-size: 11px; color: #FFD700; transition: transform 0.2s; flex-shrink: 0; }
  .goal-year-card.open .goal-year-chevron { transform: rotate(90deg); }
  .goal-year-title { font-size: 14px; font-weight: 800; color: #FFD700; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; letter-spacing: 0.3px; }
  .goal-year-progress-bar { height: 4px; background: rgba(255,215,0,0.15); margin: 0 12px 10px; border-radius: 2px; overflow: hidden; }
  .goal-year-progress-fill { height: 100%; background: linear-gradient(90deg,#FFD700,#FFA500); border-radius: 2px; transition: width 0.6s cubic-bezier(0.34,1.56,0.64,1); }
  .goal-year-progress-fill.complete { background: #22c55e; }
  .goal-year-children { padding: 0 4px 8px 12px; display: none; border-left: 2px solid rgba(255,215,0,0.2); margin-left: 10px; margin-top: 2px; }
  .goal-year-card.open .goal-year-children { display: block; }

  /* 月度卡 - 中等，紫色邊框，縮排 */
  .goal-month-card { background: rgba(99,102,241,0.06); border-radius: calc(var(--radius) - 1px); margin-bottom: 6px; border: 1px solid rgba(99,102,241,0.3); overflow: hidden; }
  .goal-month-header { display: flex; align-items: center; gap: 8px; padding: 8px 10px; cursor: pointer; user-select: none; }
  .goal-month-header:hover { background: rgba(99,102,241,0.08); }
  .goal-month-chevron { font-size: 9px; color: #a5b4fc; transition: transform 0.2s; flex-shrink: 0; }
  .goal-month-card.open .goal-month-chevron { transform: rotate(90deg); }
  .goal-month-title { font-size: 12px; font-weight: 700; color: #c4b5fd; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .goal-month-badge { font-size: 10px; padding: 1px 6px; border-radius: 8px; background: rgba(99,102,241,0.25); color: #a5b4fc; flex-shrink: 0; border: 1px solid rgba(99,102,241,0.4); }
  .goal-month-progress-bar { height: 3px; background: rgba(99,102,241,0.15); margin: 0 10px 8px; border-radius: 2px; overflow: hidden; }
  .goal-month-progress-fill { height: 100%; background: #6366f1; border-radius: 2px; transition: width 0.6s cubic-bezier(0.34,1.56,0.64,1); }
  .goal-month-progress-fill.complete { background: #22c55e; }
  .goal-month-children { padding: 0 4px 6px 10px; display: none; border-left: 2px solid rgba(99,102,241,0.25); margin-left: 8px; margin-top: 2px; }
  .goal-month-card.open .goal-month-children { display: block; }

  /* 週卡 - 最小，橘色細線，更深縮排 */
  .goal-week-card { background: rgba(249,115,22,0.04); border-radius: 6px; margin-bottom: 4px; border-left: 3px solid #f97316; border-top: 1px solid rgba(249,115,22,0.15); border-right: 1px solid rgba(249,115,22,0.15); border-bottom: 1px solid rgba(249,115,22,0.15); padding: 6px 8px; }
  .goal-week-card.goal-active-filter { border-left-color: #22c55e; background: rgba(34,197,94,0.06); border-top-color: rgba(34,197,94,0.2); border-right-color: rgba(34,197,94,0.2); border-bottom-color: rgba(34,197,94,0.2); }
  .goal-week-header { display: flex; align-items: center; gap: 6px; cursor: pointer; user-select: none; }
  .goal-week-title { font-size: 11px; color: rgba(255,255,255,0.75); flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .goal-week-progress { font-size: 10px; color: #f97316; flex-shrink: 0; font-weight: 600; }
  .goal-week-bar { height: 2px; background: rgba(249,115,22,0.15); margin-top: 4px; border-radius: 1px; overflow: hidden; }
  .goal-week-bar-fill { height: 100%; background: #f97316; border-radius: 1px; transition: width 0.6s cubic-bezier(0.34,1.56,0.64,1); }
  .goal-week-bar-fill.complete { background: #22c55e; }
  .goal-week-actions { display: flex; gap: 4px; margin-top: 5px; }
  .goal-action-btn { font-size: 10px; padding: 2px 7px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.55); cursor: pointer; font-family: inherit; transition: all 0.15s; white-space: nowrap; }
  .goal-action-btn:hover { background: rgba(255,255,255,0.12); color: #fff; }
  .goal-action-btn.primary { background: rgba(249,115,22,0.2); border-color: rgba(249,115,22,0.4); color: #fb923c; }
  .goal-action-btn.primary:hover { background: rgba(249,115,22,0.35); color: #fff; }
  .goal-inline-add { width: 100%; padding: 5px 8px; border: 1px dashed rgba(255,255,255,0.12); background: none; border-radius: 4px; font-size: 11px; color: rgba(255,255,255,0.3); cursor: pointer; font-family: inherit; text-align: left; transition: all 0.15s; margin-top: 4px; }
  .goal-inline-add:hover { border-color: rgba(255,255,255,0.3); color: rgba(255,255,255,0.6); background: rgba(255,255,255,0.03); }

  /* 篩選提示列 */
  #goal-filter-bar { display:none; position:sticky; top:0; z-index:50; background:#22c55e; color:#fff; padding:6px 12px; font-size:12px; font-weight:600; align-items:center; gap:8px; }
  #goal-filter-bar.show { display:flex; }
  #goal-filter-bar button { background:rgba(255,255,255,0.25); border:none; color:#fff; border-radius:4px; padding:2px 8px; cursor:pointer; font-size:11px; font-family:inherit; }

  /* 子任務標籤（右側看板卡片） */
  .parent-badge { display: inline-flex; align-items: center; gap: 3px; font-size: 10px; padding: 3px 9px; border-radius: 8px; background: rgba(99,102,241,0.07); border: 1px solid rgba(99,102,241,0.18); font-weight: 600; max-width: 100%; white-space: normal; word-break: break-word; line-height: 1.5; }
  .parent-badge.block, div.parent-badge { display: block; border-radius: 6px; padding: 5px 8px; }

  @media (max-width: 768px) { .goal-panel { display: none; } }
</style>
</head>
<body>

<header>
  <div class="logo">🎯 <span>FOCUS</span></div>
  
  <div class="header-center">
    <div class="search-box">
      <span class="search-icon">🔍</span>
      <input type="text" class="search-input" id="search-input" placeholder="搜尋卡片..." readonly>
      <button id="search-close-btn" onclick="closeSearchDropdown()" style="display:none;position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;font-size:18px;cursor:pointer;color:#999;padding:4px;">✕</button>
      <div class="search-dropdown" id="search-dropdown"></div>
    </div>
    <div class="filter-tags" id="filter-tags"></div>
    <div class="privacy-filters">
      <button class="privacy-filter-btn active" data-filter="all" onclick="setPrivacyFilter('all')">全部</button>
      <button class="privacy-filter-btn" data-filter="shared" onclick="setPrivacyFilter('shared')">👥 共用</button>
      <button class="privacy-filter-btn" data-filter="private" onclick="setPrivacyFilter('private')">🔒 私人</button>
    </div>
  </div>

  <div class="header-actions">
    <button class="notification-btn" id="notification-sound-toggle" onclick="toggleNotificationSound()" title="點擊關閉提示音" style="border: none; background: none; font-size: 14px; cursor: pointer; padding: 6px;">
      🔔
    </button>
    <button class="notification-btn" id="notification-btn" onclick="toggleNotifications()">
      🔔
      <span class="notification-badge" id="notification-badge" style="display:none;">0</span>
    </button>
    <div class="header-user">👤 <?php echo htmlspecialchars($username); ?></div>
    <button class="settings-btn" onclick="openProjectSettings()">⚙️ 設定</button>
    <?php if (in_array($_SESSION['username'] ?? '', ['admin', 'chienyi'])): ?><a href="users.php" class="settings-btn" style="text-decoration:none;">👥 使用者</a><?php endif; ?>
    <div class="export-menu">
      <button class="export-btn" onclick="toggleExportMenu()">📥 匯出</button>
      <div class="export-dropdown" id="export-dropdown">
        <div class="export-option" onclick="exportToExcel()">
          <div class="export-option-title">📊 匯出 Excel</div>
          <div class="export-option-desc">下載完整任務清單</div>
        </div>
        <div class="export-option" onclick="exportToPDF()">
          <div class="export-option-title">📄 匯出 PDF</div>
          <div class="export-option-desc">列印視覺化看板</div>
        </div>
        <div class="export-option" onclick="backupData()">
          <div class="export-option-title">💾 備份資料</div>
          <div class="export-option-desc">下載完整備份檔</div>
        </div>
        <div class="export-option" onclick="document.getElementById('restore-file').click()">
          <div class="export-option-title">📂 還原資料</div>
          <div class="export-option-desc">從備份檔還原</div>
        </div>
      </div>
    </div>
    <button class="help-toggle" id="help-btn" onclick="toggleHelp()">💡 說明</button>
    <button class="notification-btn" onclick="logout()" style="background:#FFF3F2;color:#991B1B;border-color:#FCA5A5;">登出</button>
  </div>
</header>

<div class="main-wrap">
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-accent"></div>
    <div class="sidebar-header">
      <div class="sidebar-title">📖 使用指南</div>
    </div>
    <div class="sidebar-body">
      <div class="help-section">
        <div class="help-label">工作流程</div>
        <div class="help-step"><div class="step-num">1</div><div class="step-text">所有想法放<strong>策略筆記區</strong></div></div>
        <div class="help-step"><div class="step-num">2</div><div class="step-text">每週選 3 件到<strong>本週目標</strong></div></div>
        <div class="help-step"><div class="step-num">3</div><div class="step-text">每天選 1 件到<strong>今日專注</strong></div></div>
        <div class="help-step"><div class="step-num">4</div><div class="step-text">完成就移到<strong>已完成</strong></div></div>
      </div>
      <div class="help-section">
        <div class="help-label">卡片欄位說明</div>
        <div class="help-tip">
          <strong>標題</strong>：任務名稱<br>
          <strong>專案標籤</strong>：SEO/商品頁/客戶等<br>
          <strong>來源連結</strong>：原始資料網址<br>
          <strong>一句摘要</strong>：卡片重點精華<br>
          <strong>下一步</strong>：明確的行動指令<br>
        </div>
      </div>
    </div>
  </aside>

  <!-- 焦點目標樹：收折時的側邊標籤 -->
  <div class="goal-panel-tab" id="goal-panel-tab" onclick="openGoalPanel()">
    <div class="goal-panel-tab-inner">🌳 焦點目標樹</div>
  </div>

  <!-- 戰略樹面板（左側收折） -->
  <div class="goal-panel size-lg" id="goal-panel">
    <div class="goal-panel-header">
      <span class="goal-panel-title">🌳 焦點目標樹</span>
      <div style="display:flex;align-items:center;gap:4px;flex-shrink:0;">
        <button onclick="setGoalPanelSize('md')" id="gp-btn-md" title="中等寬度" style="background:none;border:1px solid rgba(255,255,255,0.2);color:rgba(255,255,255,0.6);border-radius:4px;padding:2px 8px;font-size:10px;cursor:pointer;font-family:inherit;">中</button>
        <button onclick="setGoalPanelSize('lg')" id="gp-btn-lg" title="完整寬度" style="background:rgba(255,255,255,0.25);border:1px solid rgba(255,255,255,0.2);color:#fff;border-radius:4px;padding:2px 8px;font-size:10px;cursor:pointer;font-family:inherit;">大</button>
        <button onclick="hideGoalPanel()" title="收折面板" style="background:none;border:none;cursor:pointer;font-size:16px;color:rgba(255,255,255,0.7);padding:2px 4px;border-radius:4px;line-height:1;">◀</button>
      </div>
    </div>
    <div class="goal-panel-body" id="goal-panel-body">
      <!-- 動態渲染 -->
    </div>
    <div style="display:flex;gap:6px;margin:8px;flex-shrink:0;" id="goal-add-btns">
      <button onclick="openGoalModal('year',null)" style="flex:1;padding:7px 4px;border:1px solid rgba(180,140,0,0.5);background:rgba(255,215,0,0.12);border-radius:8px;font-size:11px;font-weight:700;color:#7A5C00;cursor:pointer;font-family:inherit;">📌 ＋年度</button>
      <button onclick="openGoalModal('month',null)" style="flex:1;padding:7px 4px;border:1px solid rgba(99,102,241,0.5);background:rgba(99,102,241,0.1);border-radius:8px;font-size:11px;font-weight:700;color:#3730A3;cursor:pointer;font-family:inherit;">📅 ＋月度</button>
      <button onclick="openGoalModal('week',null)" style="flex:1;padding:7px 4px;border:1px solid rgba(194,86,10,0.5);background:rgba(249,115,22,0.1);border-radius:8px;font-size:11px;font-weight:700;color:#C2560A;cursor:pointer;font-family:inherit;">📋 ＋週</button>
    </div>
    <div style="margin:0 8px 10px;display:flex;gap:6px;">
      <button onclick="openGoalImportModal()" style="flex:1;padding:6px 4px;border:1px solid rgba(83,74,183,0.4);background:rgba(83,74,183,0.07);border-radius:8px;font-size:11px;font-weight:700;color:#534AB7;cursor:pointer;font-family:inherit;">📥 匯入目標樹</button>
      <button onclick="exportGoalTree()" style="flex:1;padding:6px 4px;border:1px solid rgba(83,74,183,0.4);background:rgba(83,74,183,0.07);border-radius:8px;font-size:11px;font-weight:700;color:#534AB7;cursor:pointer;font-family:inherit;">📤 匯出目標樹</button>
    </div>
  </div>

  <!-- 篩選提示列 + 看板 -->
  <div style="flex:1;min-width:0;display:flex;flex-direction:column;gap:0;">
    <div id="goal-filter-bar">
      <span id="goal-filter-label">🔍 篩選中</span>
      <button onclick="filterByParent(null)">✕ 清除篩選</button>
    </div>

  <div class="board-wrap">
    <div class="col col-lib" data-col="lib">
      <div class="col-accent"></div>
      <div class="col-header" style="cursor:pointer;" onclick="toggleColSection('lib')">
        <div class="col-title-row">
          <div class="col-title">📚 策略筆記區</div>
          <div style="display:flex;align-items:center;gap:6px;">
            <div class="badge badge-lib" id="badge-lib">0 則</div>
            <span id="col-chev-lib" style="font-size:12px;color:var(--text-muted);transition:transform 0.2s;">▲</span>
          </div>
        </div>
        <div class="col-sub">長期儲存 • 無限制</div>
      </div>
      <div id="col-body-lib" class="col-collapsible">
        <div class="mobile-filter-bar" id="mobile-filter-bar"></div>
        <div style="padding: 0 16px 8px;"><button class="add-card-btn" onclick="openModal('lib');event.stopPropagation()">+ 新增筆記</button></div>
        <div class="cards-area" id="cards-lib" style="overflow-y:auto;"></div>
        <div class="col-resize-handle" onmousedown="startColResize(event,'lib')" title="拖曳調整高度">⋯</div>
      </div>
    </div>

    <div class="col col-week" data-col="week">
      <div class="col-accent"></div>
      <div class="col-header" style="cursor:pointer;" onclick="toggleColSection('week')">
        <div class="col-title-row">
          <div class="col-title">📅 本週目標</div>
          <div style="display:flex;align-items:center;gap:6px;">
            <div class="badge badge-week" id="badge-week">0 / 3</div>
            <span id="col-chev-week" style="font-size:12px;color:var(--text-muted);transition:transform 0.2s;">▲</span>
          </div>
        </div>
        <div class="col-sub">這週最重要的事</div>
      </div>
      <div id="col-body-week" class="col-collapsible">
        <div style="padding: 0 16px 8px;"><button class="add-card-btn" id="add-week" onclick="openModal('week');event.stopPropagation()">+ 新增目標</button></div>
        <div class="cards-area" id="cards-week" style="overflow-y:auto;"></div>
        <div class="col-resize-handle" onmousedown="startColResize(event,'week')" title="拖曳調整高度">⋯</div>
      </div>
    </div>

    <div class="col col-focus" data-col="focus">
      <div class="col-accent"></div>
      <div class="col-header">
        <div class="col-title-row">
          <div class="col-title">🎯 今日專注</div>
          <div class="badge badge-focus" id="badge-focus">0 / 1</div>
        </div>
        <div class="col-sub">現在就做這件事</div>
      </div>
      <div class="cards-area" id="cards-focus"></div>
      <div style="padding: 0 16px 16px;"><button class="add-card-btn" id="add-focus" onclick="openModal('focus')">+ 設定專注</button></div>
    </div>

    <div class="col col-done" data-col="done">
      <div class="col-accent"></div>
      <div class="col-header">
        <div class="col-title-row">
          <div class="col-title">✅ 已完成</div>
          <div class="badge badge-done" id="badge-done">0 件</div>
        </div>
        <div class="col-sub">成就收藏</div>
      </div>
      <div class="cards-area" id="cards-done"></div>
      <div style="padding: 0 16px 16px; display:none;" id="clear-done-btn"><button class="clear-done-btn" onclick="clearDone()">清空完成區</button></div>
    </div>
  </div>
  </div><!-- end board+filter wrapper -->
</div><!-- end main-wrap -->

<!-- 手機版年度計畫面板 -->
<div id="mobile-goal-panel" style="display:none;padding:16px;padding-bottom:80px;">
  <div style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:8px;display:flex;align-items:center;gap:8px;">
    🌳 焦點目標樹
    <button onclick="openGoalModal('year',null)" style="margin-left:auto;padding:6px 14px;background:#534AB7;color:#fff;border:none;border-radius:8px;font-size:12px;cursor:pointer;font-family:inherit;font-weight:600;">＋ 年度</button>
  </div>
  <div style="display:flex;gap:8px;margin-bottom:12px;">
    <button onclick="openGoalImportModal()" style="flex:1;padding:8px 4px;border:1px solid rgba(83,74,183,0.4);background:rgba(83,74,183,0.07);border-radius:8px;font-size:12px;font-weight:700;color:#534AB7;cursor:pointer;font-family:inherit;">📥 匯入目標樹</button>
    <button onclick="exportGoalTree()" style="flex:1;padding:8px 4px;border:1px solid rgba(83,74,183,0.4);background:rgba(83,74,183,0.07);border-radius:8px;font-size:12px;font-weight:700;color:#534AB7;cursor:pointer;font-family:inherit;">📤 匯出目標樹</button>
  </div>
  <div id="mobile-goal-body">
    <!-- 由 renderGoalTree 同步渲染 -->
  </div>
</div>

<!-- 手機版底部 Tab Bar -->
<div class="mobile-tab-bar">
  <button class="mobile-tab" data-col="goal" onclick="switchMobileTab('goal')">
    <div class="mobile-tab-icon">🏆</div>
    <div class="mobile-tab-label">年度</div>
  </button>
  <button class="mobile-tab active" data-col="lib" onclick="switchMobileTab('lib')">
    <div class="mobile-tab-icon">📚</div>
    <div class="mobile-tab-label">策略</div>
  </button>
  <button class="mobile-tab" data-col="week" onclick="switchMobileTab('week')">
    <div class="mobile-tab-icon">📅</div>
    <div class="mobile-tab-label">本週</div>
  </button>
  <button class="mobile-tab" data-col="focus" onclick="switchMobileTab('focus')">
    <div class="mobile-tab-icon">🎯</div>
    <div class="mobile-tab-label">今日</div>
  </button>
  <button class="mobile-tab" data-col="done" onclick="switchMobileTab('done')">
    <div class="mobile-tab-icon">✅</div>
    <div class="mobile-tab-label">完成</div>
  </button>
  <button onclick="openMobileSettings()" style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;color:var(--text-muted);padding:0 8px;flex:1;border:none;background:none;cursor:pointer;font-family:inherit;">
    <span style="font-size:22px;line-height:1;">⚙️</span>
    <span style="font-size:11px;font-weight:500;">設定</span>
  </button>
</div>

<!-- 手機版設定底部 Sheet -->
<div id="mobile-settings-sheet" style="display:none;position:fixed;inset:0;z-index:9999;">
  <div style="position:absolute;inset:0;background:rgba(0,0,0,0.5);" onclick="closeMobileSettings()"></div>
  <div style="position:absolute;bottom:0;left:0;right:0;background:var(--surface);border-radius:20px 20px 0 0;padding:20px 0 32px;">
    <div style="width:40px;height:4px;background:var(--border-strong);border-radius:2px;margin:0 auto 20px;"></div>
    <div style="padding:0 16px;font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:8px;letter-spacing:0.5px;">設定</div>
    <?php if (in_array($_SESSION['username'] ?? '', ['admin', 'chienyi'])): ?>
    <a href="users.php" style="display:flex;align-items:center;gap:14px;padding:14px 20px;text-decoration:none;color:var(--text);border-bottom:1px solid var(--border);">
      <span style="font-size:20px;">👥</span>
      <span style="font-size:15px;font-weight:500;">使用者管理</span>
    </a>
    <?php endif; ?>
    <button onclick="openProjectSettings();closeMobileSettings();" style="display:flex;align-items:center;gap:14px;padding:14px 20px;width:100%;border:none;background:none;cursor:pointer;font-family:inherit;color:var(--text);border-bottom:1px solid var(--border);text-align:left;">
      <span style="font-size:20px;">🎨</span>
      <span style="font-size:15px;font-weight:500;">專案管理</span>
    </button>
    <a href="index.php?action=logout" style="display:flex;align-items:center;gap:14px;padding:14px 20px;text-decoration:none;color:#991B1B;">
      <span style="font-size:20px;">🚪</span>
      <span style="font-size:15px;font-weight:500;">登出</span>
    </a>
  </div>
</div>
<div class="overlay" id="overlay" onclick="handleOverlayClick(event)">
  <div class="modal">
    <div class="modal-header" style="display:flex;align-items:center;justify-content:space-between;">
      <div class="modal-title" id="modal-title">新增卡片</div>
    </div>
    <div class="modal-body">
      <div class="field">
        <label class="field-label">標題 *</label>
        <input type="text" class="field-input" id="input-title" placeholder="輸入任務標題...">
      </div>
      <!-- 康乃爾筆記本 -->
      <div class="field">
        <label class="field-label">✓ 康乃爾筆記本</label>
        <div style="width:80%; margin:0 auto; display:flex; flex-direction:column; border:1px solid var(--border-strong); border-radius:var(--radius); overflow:hidden; background:#FFFDF5; box-shadow:0 2px 12px rgba(0,0,0,0.08);">
          <!-- 工具列：在左右欄上方，橫跨全寬 -->
          <div style="background:#111110; flex-shrink:0; padding:4px 8px; display:flex; flex-wrap:nowrap; gap:2px; align-items:center; border-bottom:1px solid rgba(255,255,255,0.1); overflow-x:auto;">
            <button class="mini-tb-btn" style="width:34px;height:34px;font-size:13px;font-weight:900;padding:0;border-bottom:3px solid #E24B4A;touch-action:manipulation;flex-shrink:0;" onclick="toggleModalColorMenu('modal-color-menu',this);event.preventDefault()">A</button>
            <div id="modal-color-menu" style="display:none;position:fixed;background:#111110;border-radius:12px;padding:10px;flex-direction:row;gap:8px;z-index:300;box-shadow:0 4px 20px rgba(0,0,0,0.6);">
              <button style="width:28px;height:28px;border-radius:50%;background:#E24B4A;border:2px solid rgba(255,255,255,0.5);cursor:pointer;padding:0;touch-action:manipulation;" onclick="modalSetColor('#E24B4A');event.preventDefault()"></button>
              <button style="width:28px;height:28px;border-radius:50%;background:#185FA5;border:2px solid rgba(255,255,255,0.5);cursor:pointer;padding:0;touch-action:manipulation;" onclick="modalSetColor('#185FA5');event.preventDefault()"></button>
              <button style="width:28px;height:28px;border-radius:50%;background:#1D9E75;border:2px solid rgba(255,255,255,0.5);cursor:pointer;padding:0;touch-action:manipulation;" onclick="modalSetColor('#1D9E75');event.preventDefault()"></button>
              <button style="width:28px;height:28px;border-radius:50%;background:#BA7517;border:2px solid rgba(255,255,255,0.5);cursor:pointer;padding:0;touch-action:manipulation;" onclick="modalSetColor('#BA7517');event.preventDefault()"></button>
              <button style="width:28px;height:28px;border-radius:50%;background:#1A1A18;border:2px solid rgba(255,255,255,0.5);cursor:pointer;padding:0;touch-action:manipulation;" onclick="modalSetColor('#1A1A18');event.preventDefault()"></button>
            </div>
            <button class="mini-tb-btn" style="width:34px;height:34px;padding:2px;touch-action:manipulation;flex-shrink:0;" onclick="toggleModalColorMenu('modal-bg-menu',this);event.preventDefault()">
              <svg width="20" height="20" viewBox="0 0 18 18"><rect x="2" y="13" width="14" height="3" rx="1" fill="#FFFACC"/><polygon points="4,13 7,4 11,4 14,13" fill="rgba(255,255,255,0.4)"/><rect x="7" y="2" width="4" height="3" rx="0.5" fill="rgba(255,255,255,0.5)"/></svg>
            </button>
            <div id="modal-bg-menu" style="display:none;position:fixed;background:#111110;border-radius:12px;padding:10px;flex-direction:row;gap:8px;z-index:300;box-shadow:0 4px 20px rgba(0,0,0,0.6);">
              <button style="width:28px;height:28px;border-radius:6px;background:#DAEEFF;border:2px solid rgba(255,255,255,0.5);cursor:pointer;padding:0;touch-action:manipulation;" onclick="modalSetBgColor('#DAEEFF');event.preventDefault()"></button>
              <button style="width:28px;height:28px;border-radius:6px;background:#FFFACC;border:2px solid rgba(255,255,255,0.5);cursor:pointer;padding:0;touch-action:manipulation;" onclick="modalSetBgColor('#FFFACC');event.preventDefault()"></button>
              <button style="width:28px;height:28px;border-radius:6px;background:#FFE4EC;border:2px solid rgba(255,255,255,0.5);cursor:pointer;padding:0;touch-action:manipulation;" onclick="modalSetBgColor('#FFE4EC');event.preventDefault()"></button>
              <button style="width:28px;height:28px;border-radius:6px;background:#E8F5E9;border:2px solid rgba(255,255,255,0.5);cursor:pointer;padding:0;touch-action:manipulation;" onclick="modalSetBgColor('#E8F5E9');event.preventDefault()"></button>
              <button style="width:28px;height:28px;border-radius:6px;background:#FFFFFF;border:2px solid rgba(255,255,255,0.5);cursor:pointer;padding:0;touch-action:manipulation;" onclick="modalSetBgColor('#FFFFFF');event.preventDefault()"></button>
              <button style="width:28px;height:28px;border-radius:6px;background:transparent;border:2px dashed rgba(255,255,255,0.4);cursor:pointer;padding:0;touch-action:manipulation;font-size:13px;color:rgba(255,255,255,0.6);line-height:1;" onclick="modalSetBgColor('transparent');event.preventDefault()">✕</button>
            </div>
            <div style="width:1px;height:20px;background:rgba(255,255,255,0.2);margin:0 3px;flex-shrink:0;"></div>
            <button class="mini-tb-btn" style="width:34px;height:34px;font-size:14px;padding:0;touch-action:manipulation;flex-shrink:0;" onclick="modalExecCmd('undo');event.preventDefault()">↩</button>
            <button class="mini-tb-btn" style="width:34px;height:34px;font-size:14px;padding:0;touch-action:manipulation;flex-shrink:0;" onclick="modalExecCmd('redo');event.preventDefault()">↪</button>
            <button class="mini-tb-btn" style="width:38px;height:34px;font-size:11px;padding:0;touch-action:manipulation;flex-shrink:0;line-height:1.2;" title="全選" onclick="(function(){const n=document.getElementById('input-body-editor');if(!n)return;n.focus();const r=document.createRange();r.selectNodeContents(n);const s=window.getSelection();s.removeAllRanges();s.addRange(r);})();event.preventDefault()">全選</button>
            <button class="mini-tb-btn" style="width:38px;height:34px;font-size:11px;padding:0;touch-action:manipulation;flex-shrink:0;line-height:1.2;" title="選取一行" onclick="(function(){const n=document.getElementById('input-body-editor');if(!n)return;n.focus();const sel=window.getSelection();if(!sel||!sel.rangeCount)return;const lineEl=getCurrentLineEl(n);const r=document.createRange();r.selectNodeContents(lineEl);sel.removeAllRanges();sel.addRange(r);})();event.preventDefault()">選行</button>
            <div style="width:1px;height:20px;background:rgba(255,255,255,0.2);margin:0 3px;flex-shrink:0;"></div>
            <button class="mini-tb-btn" style="width:34px;height:34px;font-size:13px;padding:0;touch-action:manipulation;flex-shrink:0;" onclick="modalExecCmd('bold');event.preventDefault()"><b>B</b></button>
            <button class="mini-tb-btn" style="width:34px;height:34px;font-size:13px;padding:0;touch-action:manipulation;flex-shrink:0;" onclick="modalExecCmd('italic');event.preventDefault()"><i>I</i></button>
            <button class="mini-tb-btn" style="width:34px;height:34px;font-size:13px;padding:0;touch-action:manipulation;flex-shrink:0;" onclick="modalExecCmd('insertOrderedList');event.preventDefault()">1.</button>
            <button class="mini-tb-btn" style="width:34px;height:34px;font-size:16px;padding:0;touch-action:manipulation;flex-shrink:0;" onclick="modalExecCmd('insertUnorderedList');event.preventDefault()">•</button>
            <div style="width:1px;height:20px;background:rgba(255,255,255,0.2);margin:0 3px;flex-shrink:0;"></div>
            <button class="mini-tb-btn" style="width:44px;height:34px;font-size:11px;padding:0;touch-action:manipulation;flex-shrink:0;line-height:1.2;letter-spacing:-0.5px;" title="插入分頁線（列印時換頁）" onclick="insertPageBreak();event.preventDefault()">－頁－</button>
          </div>
          <!-- 下半：左欄待辦 + 右欄筆記 -->
          <div style="display:flex; min-height:260px;">
            <!-- 左欄：待辦清單（可收折，自適應寬度） -->
            <div id="modal-checklist-col" style="width:fit-content; min-width:36px; max-width:260px; border-right:3px solid #E8763E; background:#F8F6F0; padding:10px; display:flex; flex-direction:column; gap:0; max-height:400px; overflow-y:auto; transition:width 0.2s; flex-shrink:0;">
              <div style="font-size:11px; font-weight:700; color:#E8763E; letter-spacing:0.5px; cursor:pointer; display:flex; align-items:center; justify-content:space-between; user-select:none; margin-bottom:4px;"
                onclick="toggleModalChecklist()">
                <span>✓ 待辦清單</span>
                <span id="modal-checklist-chevron" style="font-size:10px; transition:transform 0.2s;">▲</span>
              </div>
              <div id="modal-checklist-body" style="display:flex; flex-direction:column; gap:0;">
                <div class="checklist-container" id="checklist-container" style="display:flex; flex-direction:column; gap:6px; flex:1; margin-top:4px;"></div>
                <button type="button" class="add-checklist-item-btn" style="margin-top:8px;" onclick="addChecklistItem(); event.stopPropagation();">+ 新增待辦項目</button>
              </div>
            </div>
          <!-- 右欄：詳細筆記 -->
          <div style="flex:1; display:flex; flex-direction:column; overflow:hidden;">
            <!-- 筆記區 -->
            <div id="input-body-editor" contenteditable="true" style="
              flex:1;
              width:100%;
              min-height:600px;
              max-height:80vh;
              overflow-y:auto;
              border:none;
              padding:10px 10px 10px 14px;
              font-family:inherit;
              font-size:14px;
              color:var(--text);
              background-color: #FFFDF5;
              line-height: 1.8em;
              background-image: repeating-linear-gradient(
                to bottom,
                transparent,
                transparent calc(1.8em - 1px),
                rgba(0,0,0,0.15) calc(1.8em - 1px),
                rgba(0,0,0,0.15) 1.8em
              );
              background-size: 100% 1.8em;
              outline:none;
              word-break:break-word;
            "></div>
            <input type="hidden" id="input-body">
          </div>
        </div>
        <!-- 底部摘要欄（康乃爾筆記本底部） -->
        <div style="border-top:2px solid var(--accent-week); background:#FFFEF0; padding:8px 12px; display:flex; align-items:center; gap:8px;">
          <span style="font-size:11px; font-weight:700; color:var(--accent-week-text); white-space:nowrap; flex-shrink:0;">💡 摘要</span>
          <input type="text" class="field-input" id="input-summary" 
            placeholder="用一句話說明這張卡的重點..." 
            style="border:none; background:transparent; padding:2px 6px; font-style:italic; font-size:13px; flex:1;">
        </div>
      </div>
      <div class="field">
        <label class="field-label">專案類型 <span class="optional">(選填)</span> <a href="javascript:void(0)" onclick="openProjectSettings(); event.stopPropagation();" style="font-size:11px; color:var(--accent-week); text-decoration:underline; margin-left:8px;">⚙️ 管理專案</a></label>
        <div id="project-btn-group" style="display:flex; flex-wrap:wrap; gap:6px; margin-top:4px;"></div>
        <input type="hidden" id="input-project">
      </div>
      <div class="field">
        <label class="field-label">優先級（四象限） <span class="optional">(選填)</span></label>
        <div class="priority-grid" id="priority-grid">
          <button type="button" class="priority-btn" data-value="urgent_important" onclick="selectPriority('urgent_important')">🔥 重要且緊急</button>
          <button type="button" class="priority-btn" data-value="important_not_urgent" onclick="selectPriority('important_not_urgent')">⭐ 重要不緊急</button>
          <button type="button" class="priority-btn" data-value="urgent_not_important" onclick="selectPriority('urgent_not_important')">⚡ 緊急不重要</button>
          <button type="button" class="priority-btn" data-value="not_urgent_not_important" onclick="selectPriority('not_urgent_not_important')">💤 不重要不緊急</button>
        </div>
        <input type="hidden" id="input-priority">
      </div>
      <div class="field">
        <label class="field-label">隱私設定</label>
        <div style="display: flex; gap: 12px; margin-top: 8px;">
          <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
            <input type="radio" name="privacy" id="privacy-shared" value="0" style="cursor: pointer;">
            <span style="font-size: 14px;">👥 共用（兩人都能看到）</span>
          </label>
          <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
            <input type="radio" name="privacy" id="privacy-private" value="1" checked style="cursor: pointer;">
            <span style="font-size: 14px;">🔒 私人（只有自己能看到）</span>
          </label>
        </div>
      </div>
      <div class="field">
        <label class="field-label">來源連結 <span class="optional">(選填)</span></label>
        <input type="url" class="field-input" id="input-source" placeholder="https://...">
      </div>
      <div class="field">
        <label class="field-label">下一步行動 <span class="optional">(選填)</span></label>
        <textarea class="field-textarea" id="input-nextstep" placeholder="明確的行動指令，例如：打電話給王先生確認規格"></textarea>
      </div>
      <div class="color-section"><label class="color-label">背景顏色</label><div class="swatches" id="bg-swatches"></div></div>
      <div class="color-section"><label class="color-label">文字顏色</label><div class="swatches" id="text-swatches"></div></div>
      <input type="hidden" id="input-col">
      <input type="hidden" id="input-edit-id">
      <input type="hidden" id="input-bgcolor">
      <input type="hidden" id="input-textcolor">
      <input type="hidden" id="input-parent-id">
      <input type="hidden" id="input-level" value="general">
    </div>
    <div class="modal-footer" style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
      <button id="modal-back-btn" onclick="closeModal()" style="display:none;padding:8px 16px;font-size:13px;border:1px solid var(--accent-week);border-radius:var(--radius);background:none;cursor:pointer;color:var(--accent-week);font-weight:600;">← 返回外部</button>
      <div style="display:flex;gap:8px;margin-left:auto;">
        <button onclick="printModalContent()" style="padding:8px 16px;font-size:13px;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface2);cursor:pointer;color:var(--text);">🖨️ 列印</button>
        <button class="modal-btn secondary" onclick="closeModal()">取消</button>
        <button class="modal-btn primary" onclick="saveCardNoClose()">儲存</button>
      </div>
    </div>
  </div>
</div>



<!-- 匯入目標樹 Modal -->
<div id="goal-import-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:99999;align-items:center;justify-content:center;padding:16px;">
  <div style="background:var(--surface);border-radius:16px;width:100%;max-width:520px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;">
      <div style="font-size:16px;font-weight:700;">📥 匯入目標樹</div>
      <button onclick="closeGoalImport()" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text-muted);">✕</button>
    </div>
    <div style="padding:16px 20px;overflow-y:auto;flex:1;">

      <!-- 輸入區 -->
      <div id="goal-import-input-area">
        <div style="display:flex;gap:8px;margin-bottom:12px;">
          <button id="gim-tab-paste" onclick="switchImportTab('paste')"
            style="flex:1;padding:8px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;border:1.5px solid #534AB7;background:#534AB7;color:#fff;">
            📋 貼上 JSON
          </button>
          <button id="gim-tab-file" onclick="switchImportTab('file')"
            style="flex:1;padding:8px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;border:1.5px solid var(--border);background:var(--surface2);color:var(--text-secondary);">
            📂 選取檔案
          </button>
        </div>
        <div id="gim-paste-area">
          <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px;">把 JSON 內容貼到下方（手機長按貼上）：</div>

          <!-- 網址輸入（手機複製連結後貼這裡） -->
          <div style="margin-bottom:10px;">
            <div style="font-size:11px;font-weight:600;color:#534AB7;margin-bottom:4px;">📎 或貼上 JSON 檔案的網址：</div>
            <div style="display:flex;gap:6px;">
              <input id="goal-import-url" type="url" placeholder="https://... 貼上連結"
                style="flex:1;padding:8px 10px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;background:var(--surface2);color:var(--text);outline:none;min-width:0;"
                oninput="this.style.borderColor=this.value?'#534AB7':'var(--border)'">
              <button onclick="fetchImportUrl()"
                style="padding:8px 12px;background:#534AB7;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;flex-shrink:0;">
                抓取
              </button>
            </div>
          </div>

          <div style="font-size:11px;font-weight:600;color:var(--text-secondary);margin-bottom:4px;">或直接貼上 JSON 文字：</div>
          <textarea id="goal-import-textarea"
            placeholder='{"years":[{"title":"年度目標","months":[...]}]}'
            style="width:100%;height:120px;border:1.5px solid var(--border);border-radius:8px;padding:10px;font-size:12px;font-family:monospace;resize:vertical;background:var(--surface2);color:var(--text);box-sizing:border-box;outline:none;"
            oninput="this.style.borderColor=this.value?'#534AB7':'var(--border)'"></textarea>
          <button onclick="parseImportTextarea()"
            style="margin-top:10px;width:100%;padding:11px;background:#534AB7;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;">
            🔍 解析並預覽
          </button>
        </div>
        <div id="gim-file-area" style="display:none;">
          <div style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">選取 .json 檔案（建議桌面使用）：</div>
          <button onclick="document.getElementById('goal-import-file').click()"
            style="width:100%;padding:14px;border:2px dashed var(--border);border-radius:8px;background:var(--surface2);color:var(--text-secondary);font-size:14px;cursor:pointer;font-family:inherit;">
            📂 點此選取 JSON 檔案
          </button>
        </div>
      </div>

      <!-- 預覽區 -->
      <div id="goal-import-preview" style="display:none;">
        <div style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:10px;">📋 預覽匯入內容：</div>
        <div id="goal-import-preview-body" style="background:var(--surface2);border-radius:8px;padding:12px;font-size:12px;max-height:260px;overflow-y:auto;line-height:1.8;"></div>
        <div id="goal-import-conflict" style="display:none;margin-top:12px;padding:10px 14px;background:#FFFBEB;border:1px solid #FCD34D;border-radius:8px;font-size:12px;color:#92400E;">
          <div style="font-weight:700;margin-bottom:4px;">⚠️ 發現同名目標</div>
          <div id="goal-import-conflict-list" style="line-height:1.8;"></div>
          <div style="margin-top:8px;font-weight:600;">確定要繼續匯入嗎？（同名會新增為獨立項目，不會覆蓋）</div>
        </div>
        <div id="goal-import-summary" style="margin-top:10px;font-size:12px;color:var(--text-muted);"></div>
        <button onclick="resetGoalImport()" style="margin-top:8px;padding:6px 14px;border:1px solid var(--border);border-radius:6px;background:none;font-size:12px;cursor:pointer;font-family:inherit;color:var(--text-secondary);">← 重新輸入</button>
      </div>

      <div id="goal-import-error" style="display:none;padding:10px 14px;background:#FEF2F2;border:1px solid #FCA5A5;border-radius:8px;font-size:13px;color:#991B1B;margin-top:8px;"></div>
      <div id="goal-import-progress" style="display:none;margin-top:12px;">
        <div style="font-size:13px;font-weight:600;margin-bottom:8px;">匯入中...</div>
        <div style="background:var(--border);border-radius:4px;height:6px;overflow:hidden;">
          <div id="goal-import-bar" style="height:100%;background:#534AB7;width:0%;transition:width 0.3s;"></div>
        </div>
        <div id="goal-import-status" style="font-size:11px;color:var(--text-muted);margin-top:6px;"></div>
      </div>
      <div id="goal-import-done" style="display:none;margin-top:12px;padding:12px 16px;background:#E6F9EF;border:1px solid #6EE7A0;border-radius:8px;font-size:13px;font-weight:600;color:#166534;"></div>
    </div>
    <div style="padding:12px 20px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end;flex-shrink:0;">
      <button onclick="closeGoalImport()" style="padding:8px 16px;border:1px solid var(--border);border-radius:8px;background:none;cursor:pointer;font-family:inherit;font-size:13px;color:var(--text-secondary);">取消</button>
      <button id="goal-import-confirm-btn" onclick="confirmGoalImport()" style="display:none;padding:8px 20px;border:none;border-radius:8px;background:#534AB7;color:#fff;cursor:pointer;font-family:inherit;font-size:13px;font-weight:700;">確認匯入</button>
    </div>
  </div>
</div>



<!-- 專案設定 Modal -->
<div class="overlay" id="project-settings-overlay" onclick="handleProjectSettingsClick(event)">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">⚙️ 專案類型管理</div>
    </div>
    <div class="modal-body">
      <div class="add-project-form" style="flex-direction:column; gap:8px;">
        <div style="display:flex; gap:8px; align-items:center;">
          <input type="text" class="add-project-input" id="new-project-name" placeholder="專案名稱..." maxlength="20" style="flex:1;" oninput="const sw=document.getElementById('new-proj-swatches');sw.style.display=this.value.trim()?'flex':'none';if(this.value.trim()&&!sw.children.length)renderProjSwatches('new-proj-swatches','new-project-color','#1E88E5');">
          <button class="add-project-btn" onclick="addCustomProject()">+ 新增</button>
        </div>
        <div class="proj-color-swatches" id="new-proj-swatches" style="display:none;"></div>
        <input type="hidden" id="new-project-color" value="#1E88E5">
      </div>
      <div class="project-list" id="project-list"></div>
    </div>
    <div class="modal-footer">
      <button class="modal-btn primary" onclick="closeProjectSettings()">完成</button>
    </div>
  </div>
</div>

<!-- 手機版下拉選單 -->
<div id="toast"></div>
<!-- 手機版懸浮工具列 -->
<div id="float-toolbar" style="display:none;position:fixed;bottom:70px;right:12px;z-index:99999;display:flex;flex-direction:column;gap:8px;">
  <?php if (in_array($_SESSION['username'] ?? '', ['admin', 'chienyi'])): ?>
  <a href="users.php" style="display:flex;align-items:center;justify-content:center;width:48px;height:48px;background:#534AB7;color:white;border-radius:50%;text-decoration:none;font-size:20px;box-shadow:0 2px 8px rgba(0,0,0,0.2);">⚙️</a>
  <?php endif; ?>
  <a href="index.php?action=logout" style="display:flex;align-items:center;justify-content:center;width:48px;height:48px;background:#FEE2E2;color:#991B1B;border-radius:50%;text-decoration:none;font-size:20px;box-shadow:0 2px 8px rgba(0,0,0,0.2);">🚪</a>
</div>
<input type="file" id="restore-file" accept=".json" style="display:none" onchange="restoreData(event)">

<!-- 通知弹窗 -->
<div class="notification-panel" id="notification-panel">
  <div class="notification-header">
    <div class="notification-title">🔔 通知</div>
    <div class="notification-actions">
      <button class="notification-action-btn" onclick="markAllNotificationsRead()">全部標記已讀</button>
      <button class="notification-close-btn" onclick="toggleNotifications()">✕</button>
    </div>
  </div>
  <div class="notification-list" id="notification-list">
    <div class="notification-empty">暫無通知</div>
  </div>
</div>

<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<!-- Quill ImageResize 模組 -->
<script src="https://unpkg.com/quill-image-resize-module@3.0.0/image-resize.min.js"></script>

<script>
// ==========================================
// 狀態與基礎設定
// ==========================================
let state = { lib: [], week: [], focus: [], done: [], goal: [] };
const CURRENT_USERNAME = <?php echo json_encode($_SESSION['username'] ?? ''); ?>;
let privacyFilter = 'all'; // 隐私筛选：'all', 'shared', 'private'
let searchQuery = '';

// 預設專案類型（容錯備份）
const DEFAULT_PROJECTS = {};  // 預設無專案，由使用者自行新增

// 專案資料（優先從資料庫載入，失敗則使用預設）
let ALL_PROJECTS = { ...DEFAULT_PROJECTS };
let PROJECT_LABELS = {};
let currentFilter = null, focusTimer = null, timerSeconds = 0, timerRunning = false;
let quill = null;
let isDBMode = false; // 追蹤是否成功使用資料庫

// ==========================================
// 專案管理（資料庫優先，localStorage 容錯）
// ==========================================

// 從資料庫載入專案
async function loadProjectsFromDB() {
  try {
    const res = await fetch('api/projects.php?action=list');
    
    if (!res.ok) {
      console.warn('API 回應錯誤:', res.status);
      return null;
    }
    
    const data = await res.json();
    
    // ⭐ 支援兩種格式：{success, data: [...]} 或直接 [...]
    let projectsArray;
    
    if (data.error) {
      console.warn('API 錯誤:', data.error);
      return null;
    }
    
    if (Array.isArray(data)) {
      // 直接是陣列
      projectsArray = data;
    } else if (data.data && Array.isArray(data.data)) {
      // 包裹在 data 屬性中
      projectsArray = data.data;
    } else {
      console.warn('API 回應格式錯誤:', data);
      return null;
    }
    
    // 轉換格式
    const projects = {};
    projectsArray.forEach(proj => {
      projects[proj.key] = {
        id: proj.id,
        label: proj.label,
        bg: proj.bgColor,
        color: proj.textColor,
        default: proj.isDefault
      };
    });
    
    console.log('✅ 資料庫載入成功:', Object.keys(projects).length, '個專案');
    isDBMode = true;
    return projects;
    
  } catch (err) {
    console.warn('資料庫連線失敗:', err);
    return null;
  }
}

// localStorage 備份方案
function loadCustomProjects() {
  try {
    const saved = localStorage.getItem('customProjects');
    return saved ? JSON.parse(saved) : {};
  } catch (err) {
    console.error('localStorage 讀取失敗:', err);
    return {};
  }
}

function saveCustomProjects(projects) {
  try {
    localStorage.setItem('customProjects', JSON.stringify(projects));
  } catch (err) {
    console.error('localStorage 儲存失敗:', err);
  }
}

// 初始化專案列表
async function initProjects() {
  // 優先嘗試資料庫
  const dbProjects = await loadProjectsFromDB();
  
  if (dbProjects) {
    // ✅ 資料庫成功
    ALL_PROJECTS = dbProjects;
    console.log('✅ 使用資料庫模式');
  } else {
    // ⚠️ 資料庫失敗，使用 localStorage
    const customProjects = loadCustomProjects();
    ALL_PROJECTS = { ...DEFAULT_PROJECTS, ...customProjects };
    isDBMode = false;
    console.warn('⚠️ 使用 localStorage 模式（資料庫未就緒）');
  }
  
  updateProjectLabels();
}

// 取得所有專案
function getAllProjects() {
  return ALL_PROJECTS;
}

// 更新專案標籤
function updateProjectLabels() {
  PROJECT_LABELS = {};
  Object.keys(ALL_PROJECTS).forEach(key => {
    PROJECT_LABELS[key] = ALL_PROJECTS[key].label;
  });
}

function toast(msg) {
  const el = document.getElementById('toast'); 
  el.textContent = msg; 
  el.classList.add('show');
  clearTimeout(el._t); 
  el._t = setTimeout(() => { el.classList.remove('show'); }, 2500);
}

// ==========================================
// 圖片壓縮與處理函數（改進版）
// ==========================================
function compressImage(file, maxSizeKB = 100) {
  return new Promise((resolve, reject) => {
    // 檢查是否為圖片
    if (!file.type.match('image.*')) {
      reject('只能上傳圖片檔案');
      return;
    }
    
    const reader = new FileReader();
    reader.onload = (e) => {
      const img = new Image();
      img.onload = () => {
        // ⭐ 多級尺寸選項（從大到小）
        const sizeOptions = [1920, 1280, 800, 640, 480];
        let currentSizeIndex = 0;
        
        const tryCompressWithSize = (maxDimension) => {
          const canvas = document.createElement('canvas');
          let width = img.width;
          let height = img.height;
          
          // 計算縮放比例
          if (width > maxDimension || height > maxDimension) {
            if (width > height) {
              height = (height / width) * maxDimension;
              width = maxDimension;
            } else {
              width = (width / height) * maxDimension;
              height = maxDimension;
            }
          }
          
          canvas.width = width;
          canvas.height = height;
          const ctx = canvas.getContext('2d');
          ctx.drawImage(img, 0, 0, width, height);
          
          // ⭐ 品質選項（從高到低）
          const qualityOptions = [0.9, 0.7, 0.5, 0.3, 0.1];
          let qualityIndex = 0;
          
          const tryCompress = () => {
            const quality = qualityOptions[qualityIndex];
            
            canvas.toBlob((blob) => {
              const sizeKB = blob.size / 1024;
              
              if (sizeKB <= maxSizeKB) {
                // ✅ 成功壓縮到目標大小
                const reader = new FileReader();
                reader.onloadend = () => {
                  const base64 = reader.result;
                  const finalSizeKB = Math.round(base64.length / 1024);
                  
                  resolve({
                    success: true,
                    data: base64,
                    originalSize: Math.round(file.size / 1024),
                    finalSize: finalSizeKB,
                    dimension: Math.round(width),
                    quality: Math.round(quality * 100)
                  });
                };
                reader.readAsDataURL(blob);
                
              } else if (qualityIndex < qualityOptions.length - 1) {
                // 繼續降低品質
                qualityIndex++;
                tryCompress();
                
              } else if (currentSizeIndex < sizeOptions.length - 1) {
                // 品質已降到最低，嘗試更小的尺寸
                currentSizeIndex++;
                tryCompressWithSize(sizeOptions[currentSizeIndex]);
                
              } else {
                // 已用盡所有選項，仍然太大
                reject(`圖片過於複雜，壓縮後仍有 ${Math.round(sizeKB)}KB。建議：\n1. 使用更簡單的圖片\n2. 先用其他工具壓縮\n3. 使用截圖而非照片`);
              }
              
            }, 'image/jpeg', quality);
          };
          
          tryCompress();
        };
        
        // 從最大尺寸開始嘗試
        tryCompressWithSize(sizeOptions[currentSizeIndex]);
      };
      
      img.src = e.target.result;
    };
    
    reader.readAsDataURL(file);
  });
}

// 自定義圖片處理器
function imageHandler() {
  const input = document.createElement('input');
  input.setAttribute('type', 'file');
  input.setAttribute('accept', 'image/*');
  input.click();

  input.onchange = async () => {
    const file = input.files[0];
    if (!file) return;
    
    const originalSizeKB = Math.round(file.size / 1024);
    
    // 顯示處理中提示
    toast(`📸 處理圖片中... (${originalSizeKB}KB)`);
    
    try {
      const result = await compressImage(file, 100);
      
      // 插入圖片到編輯器
      const range = quill.getSelection(true);
      quill.insertEmbed(range.index, 'image', result.data);
      quill.setSelection(range.index + 1);
      
      // 顯示成功訊息
      if (result.originalSize > 100) {
        toast(`✅ 圖片已壓縮：${result.originalSize}KB → ${result.finalSize}KB (${result.dimension}px, ${result.quality}%品質)`);
      } else {
        toast(`✅ 圖片已插入 (${result.finalSize}KB)`);
      }
      
    } catch (error) {
      toast(`❌ ${error}`);
    }
  };
}

// ==========================================
// Quill 編輯器初始化
// ==========================================
document.addEventListener('DOMContentLoaded', async () => {
  // ⭐ 註冊 ImageResize 模組
  Quill.register('modules/imageResize', window.ImageResize.default);
  
  quill = new Quill('#editor-container', {
    theme: 'snow',
    modules: {
      toolbar: {
        container: [
          [{ 'header': [1, 2, 3, false] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ 'list': 'ordered'}, { 'list': 'bullet' }],
          [{ 'indent': '-1'}, { 'indent': '+1' }],
          [{ 'color': [] }, { 'background': [] }],
          [{ 'align': [] }],
          ['link', 'image'],
          ['clean']
        ],
        handlers: {
          image: imageHandler
        }
      },
      // ⭐ 啟用圖片調整大小 + 裁切功能
      imageResize: {
        modules: ['Resize', 'DisplaySize', 'Toolbar'],
        // 自定義工具列選項
        handleStyles: {
          backgroundColor: 'black',
          border: 'none',
          color: 'white'
        },
        displayStyles: {
          backgroundColor: 'black',
          border: 'none',
          color: 'white',
          padding: '4px 8px',
          borderRadius: '4px'
        }
      }
    }
  });
  
  // ⭐ 初始化專案（優先資料庫，失敗則 localStorage）
  await initProjects();
  
  renderProjectSelect();
  injectProjectStyles();
  initGoalPanelState(); // 戰略樹收折狀態
  initColSections();    // 策略筆記/本週欄收折狀態

  // 動態綁定匯入 file input（避免 onchange 屬性時機問題）
  const _importFileEl = document.getElementById('goal-import-file');
  if (_importFileEl) {
    _importFileEl.addEventListener('change', function() {
      handleGoalImportFile(this);
    });
  }
  // 手機版：載入前先設定預設分頁（今日專注）
  if (window.innerWidth <= 768) {
    setMobileTab('focus');
  }
  await loadCards();
  window.addEventListener('resize', () => {
    if (window.innerWidth > 768) {
      document.querySelectorAll('.col').forEach(c => c.classList.remove('active'));
    } else {
      // 視窗縮小時，如果沒有 active tab 就顯示今日專注
      if (!document.querySelector('.mobile-tab.active')) {
        setMobileTab('focus');
      }
    }
  });
});

// ==========================================
// Quill 全螢幕編輯器
// ==========================================
function openFullscreenEditor() {
  const content = document.getElementById('input-body').value;
  quill.root.innerHTML = content || '';
  document.getElementById('fullscreen-editor').classList.add('open');
  quill.focus();
}

function closeFullscreenEditor() {
  document.getElementById('fullscreen-editor').classList.remove('open');
}

function saveFullscreenContent() {
  const html = quill.root.innerHTML;
  document.getElementById('input-body').value = html;
  
  // 更新預覽
  const preview = document.getElementById('word-preview');
  if (html && html.trim() && html !== '<p><br></p>') {
    preview.innerHTML = html;
    preview.classList.remove('empty');
  } else {
    preview.innerHTML = '<span class="empty">點擊此處使用 Word 編輯器撰寫...</span>';
    preview.classList.add('empty');
  }
  
  closeFullscreenEditor();
  toast('✅ 內容已儲存');
}

// ==========================================
// 專案類型管理
// ==========================================
function openProjectSettings() {
  renderProjectList();
  document.getElementById('project-settings-overlay').classList.add('open');
}

function closeProjectSettings() {
  document.getElementById('project-settings-overlay').classList.remove('open');
  
  // ⭐ 確保關閉時更新並重新渲染
  updateProjectLabels();
  renderProjectSelect();
  injectProjectStyles();
  render();
}

function handleProjectSettingsClick(e) {
  if (e.target.id === 'project-settings-overlay') closeProjectSettings();
}

// 固定色票
const PROJ_COLORS = [
  { val: '#E53935', label: '紅 · 緊急重要' },
  { val: '#FB8C00', label: '橙 · 財務金錢' },
  { val: '#FDD835', label: '黃 · 注意待確認' },
  { val: '#43A047', label: '綠 · 進行中' },
  { val: '#1E88E5', label: '藍 · 工作專案' },
  { val: '#8E24AA', label: '紫 · 個人創意' },
  { val: '#212121', label: '黑 · 私人機密' },
  { val: '#78909C', label: '灰 · 一般其他' },
];

function renderProjSwatches(containerId, inputId, selectedColor) {
  const container = document.getElementById(containerId);
  if (!container) return;
  container.innerHTML = '';
  PROJ_COLORS.forEach(item => {
    const c = item.val;
    const s = document.createElement('div');
    s.className = 'proj-color-swatch' + (c === selectedColor ? ' selected' : '');
    s.style.background = c;
    s.title = item.label;
    s.onclick = () => {
      document.getElementById(inputId).value = c;
      container.querySelectorAll('.proj-color-swatch').forEach(x => x.classList.remove('selected'));
      s.classList.add('selected');
    };
    container.appendChild(s);
  });
}

function renderProjectList() {
  const allProjects = getAllProjects();
  const list = document.getElementById('project-list');
  list.innerHTML = '';

  // 初始化新增表單色票
  const initColor = document.getElementById('new-project-color')?.value || '#1E88E5';
  renderProjSwatches('new-proj-swatches', 'new-project-color', initColor);

  if (Object.keys(allProjects).length === 0) {
    list.innerHTML = '<div style="color:var(--text-muted);font-size:13px;padding:12px 0;">尚無專案，請新增</div>';
    return;
  }

  Object.keys(allProjects).forEach(key => {
    const proj = allProjects[key];
    const item = document.createElement('div');
    item.id = 'proj-item-' + key;
    item.style.cssText = 'border:1px solid var(--border);border-radius:var(--radius);margin-bottom:8px;overflow:hidden;';
    item.innerHTML = `
      <!-- 標題列 -->
      <div id="proj-header-${key}"
        style="display:flex;align-items:center;gap:10px;padding:10px 12px;cursor:${!proj.default ? 'pointer' : 'default'};background:var(--surface);user-select:none;">
        <div style="width:14px;height:14px;border-radius:50%;background:${proj.color || proj.textColor || '#999'};flex-shrink:0;"></div>
        <span style="flex:1;font-size:13px;font-weight:500;">${proj.label}</span>
        ${proj.default ? '<span style="font-size:11px;color:var(--text-muted);">預設</span>' : ''}
        ${!proj.default ? '<span style="font-size:11px;color:var(--text-muted);">▾</span>' : ''}
        ${!proj.default ? `<button id="proj-del-${key}" style="padding:3px 8px;font-size:11px;background:#FEE2E2;color:#991B1B;border:1px solid #FCA5A5;border-radius:6px;cursor:pointer;">刪除</button>` : ''}
      </div>
      <!-- 編輯區 -->
      <div id="proj-edit-${key}" style="display:none;padding:12px;background:var(--surface2);border-top:1px solid var(--border);">
        <input class="project-edit-input" id="proj-edit-name-${key}" maxlength="20" placeholder="專案名稱..." style="width:100%;margin-bottom:10px;box-sizing:border-box;">
        <div style="font-size:11px;color:var(--text-muted);margin-bottom:6px;">選擇顏色（懸停看說明）</div>
        <div class="proj-color-swatches" id="proj-edit-swatches-${key}" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;"></div>
        <input type="hidden" id="proj-edit-color-${key}">
        <div style="display:flex;gap:6px;">
          <button id="proj-save-${key}" style="padding:6px 16px;background:var(--accent-week);color:white;border:none;border-radius:var(--radius);font-size:12px;cursor:pointer;font-weight:500;">儲存</button>
          <button id="proj-cancel-${key}" style="padding:6px 12px;background:var(--surface2);color:var(--text-secondary);border:1px solid var(--border);border-radius:var(--radius);font-size:12px;cursor:pointer;">取消</button>
        </div>
      </div>
    `;
    list.appendChild(item);
    // 用 addEventListener 避免 onclick 衝突
    if (!proj.default) {
      document.getElementById('proj-header-' + key).addEventListener('click', () => toggleEditProject(key));
      document.getElementById('proj-del-' + key).addEventListener('click', (e) => { e.stopPropagation(); deleteCustomProject(key); });
      document.getElementById('proj-save-' + key).addEventListener('click', (e) => { e.stopPropagation(); saveEditProject(key); });
      document.getElementById('proj-cancel-' + key).addEventListener('click', (e) => { e.stopPropagation(); toggleEditProject(key); });
    }
  });
}

function toggleEditProject(key) {
  const editDiv = document.getElementById('proj-edit-' + key);
  if (!editDiv) return;
  const isOpen = editDiv.style.display !== 'none';
  // 關閉所有其他編輯區
  document.querySelectorAll('div[id^="proj-edit-"]').forEach(d => d.style.display = 'none');
  // 新增色票：有編輯展開時隱藏，全部收合時恢復（若輸入框有值才顯示）
  const newSwatches = document.getElementById('new-proj-swatches');
  if (!isOpen) {
    // 展開編輯：隱藏新增色票
    if (newSwatches) newSwatches.style.display = 'none';
    editDiv.style.display = 'block';
    const proj = ALL_PROJECTS[key];
    const currentColor = proj.color || proj.textColor || '#1E88E5';
    // 填入名稱（避免 HTML 特殊字元問題）
    const nameInput = document.getElementById('proj-edit-name-' + key);
    if (nameInput) nameInput.value = proj.label || '';
    // 色票容器顯示
    const swatchContainer = document.getElementById('proj-edit-swatches-' + key);
    if (swatchContainer) swatchContainer.style.display = 'flex';
    renderProjSwatches('proj-edit-swatches-' + key, 'proj-edit-color-' + key, currentColor);
    const colorInput = document.getElementById('proj-edit-color-' + key);
    if (colorInput) colorInput.value = currentColor;
  } else {
    // 收合：若新增欄位有輸入值則恢復色票
    const nameField = document.getElementById('new-project-name');
    if (newSwatches && nameField && nameField.value.trim()) {
      newSwatches.style.display = 'flex';
    }
  }
}

async function saveEditProject(key) {
  const name  = document.getElementById('proj-edit-name-' + key).value.trim();
  const color = document.getElementById('proj-edit-color-' + key).value;
  if (!name) { toast('請輸入名稱'); return; }
  const bg = color + '22';

  if (isDBMode) {
    const proj = ALL_PROJECTS[key];
    try {
      const res = await fetch('api/projects.php?action=update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: proj.id, label: name, bgColor: bg, textColor: color })
      });
      const data = await res.json();
      if (data.error || data.success === false) { toast(data.error || '更新失敗'); return; }
      await initProjects();
    } catch(e) { toast('更新失敗'); return; }
  } else {
    const customProjects = loadCustomProjects();
    if (customProjects[key]) {
      customProjects[key].label = name;
      customProjects[key].color = color;
      customProjects[key].bg = bg;
      saveCustomProjects(customProjects);
      ALL_PROJECTS[key] = customProjects[key];
    }
  }
  renderProjectList();
  renderProjectSelect();
  injectProjectStyles();
  render();
  toast('✅ 專案已更新');
}

async function addCustomProject() {
  const name = document.getElementById('new-project-name').value.trim();
  const color = document.getElementById('new-project-color').value;
  
  if (!name) {
    toast('請輸入專案名稱');
    return;
  }
  
  const bg = color + '20';
  
  if (isDBMode) {
    // ✅ 資料庫模式
    try {
      const res = await fetch('api/projects.php?action=add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          label: name,
          bgColor: bg,
          textColor: color
        })
      });
      
      const data = await res.json();
      
      // ⭐ 檢查錯誤（支援多種格式）
      if (data.error || (data.success === false)) {
        toast(data.error || data.message || '新增失敗');
        return;
      }
      
      // 重新載入專案
      await initProjects();
      
      document.getElementById('new-project-name').value = '';
      document.getElementById('new-project-color').value = '#E3F2FD';
      
      renderProjectList();
      renderProjectSelect();
      injectProjectStyles();
      render();
      
      toast('✅ 專案類型已新增（已同步到資料庫）');
      
    } catch (err) {
      toast('新增專案失敗');
      console.error(err);
    }
    
  } else {
    // ⚠️ localStorage 模式
    const customProjects = loadCustomProjects();
    const key = 'custom_' + Date.now();
    
    customProjects[key] = {
      label: name,
      bg: bg,
      color: color,
      default: false
    };
    
    saveCustomProjects(customProjects);
    ALL_PROJECTS[key] = customProjects[key];
    
    document.getElementById('new-project-name').value = '';
    document.getElementById('new-project-color').value = '#E3F2FD';
    
    updateProjectLabels();
    renderProjectList();
    renderProjectSelect();
    injectProjectStyles();
    render();
    
    toast('✅ 專案類型已新增（僅儲存在此裝置）');
  }
}

async function deleteCustomProject(key) {
  if (!confirm('確定要刪除這個專案類型嗎？')) return;
  
  const project = ALL_PROJECTS[key];
  if (!project) {
    toast('專案不存在');
    return;
  }
  
  if (project.default) {
    toast('無法刪除預設專案');
    return;
  }
  
  if (isDBMode) {
    // ✅ 資料庫模式
    try {
      const res = await fetch(`api/projects.php?action=delete&id=${project.id}`, {
        method: 'POST'
      });
      
      const data = await res.json();
      
      // ⭐ 檢查錯誤（支援多種格式）
      if (data.error || (data.success === false)) {
        toast(data.error || data.message || '刪除失敗');
        return;
      }
      
      // 重新載入專案
      await initProjects();
      
      renderProjectList();
      renderProjectSelect();
      injectProjectStyles();
      render();
      
      toast('🗑️ 專案類型已刪除（已同步到資料庫）');
      
    } catch (err) {
      toast('刪除專案失敗');
      console.error(err);
    }
    
  } else {
    // ⚠️ localStorage 模式
    const customProjects = loadCustomProjects();
    delete customProjects[key];
    saveCustomProjects(customProjects);
    delete ALL_PROJECTS[key];
    
    updateProjectLabels();
    renderProjectList();
    renderProjectSelect();
    injectProjectStyles();
    render();
    
    toast('🗑️ 專案類型已刪除（僅從此裝置移除）');
  }
}

function renderProjectSelect() {
  const group = document.getElementById('project-btn-group');
  if (!group) return;
  const allProjects = getAllProjects();
  const current = document.getElementById('input-project').value;

  group.innerHTML = '';
  // 無 按鈕
  const noneBtn = document.createElement('button');
  noneBtn.type = 'button';
  noneBtn.className = 'proj-select-btn' + (current === '' ? ' active' : '');
  noneBtn.textContent = '無';
  noneBtn.style.cssText = 'padding:5px 12px;border:1.5px solid var(--border-strong);border-radius:20px;font-size:12px;font-weight:500;cursor:pointer;background:' + (current==='' ? 'var(--surface2)' : 'var(--surface)') + ';font-family:inherit;';
  noneBtn.onclick = () => { document.getElementById('input-project').value = ''; setTimeout(renderProjectSelect, 10); renderProjectSelect(); };
  group.appendChild(noneBtn);

  Object.keys(allProjects).forEach(key => {
    const proj = allProjects[key];
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'proj-select-btn' + (current === key ? ' active' : '');
    btn.textContent = proj.label;
    btn.style.cssText = 'padding:5px 12px;border:1.5px solid ' + (proj.color||'var(--border-strong)') + ';border-radius:20px;font-size:12px;font-weight:500;cursor:pointer;background:' + (current===key ? (proj.color||'#eee') : 'var(--surface)') + ';color:' + (current===key ? '#fff' : 'var(--text)') + ';font-family:inherit;';
    btn.onclick = () => { document.getElementById('input-project').value = key; renderProjectSelect(); };
    group.appendChild(btn);
  });
}

// 動態生成專案樣式
function injectProjectStyles() {
  const allProjects = getAllProjects();
  let styles = '';
  
  Object.keys(allProjects).forEach(key => {
    const proj = allProjects[key];
    styles += `.project-tag.${key} { background: ${proj.bg}; color: ${proj.color}; }\n`;
  });
  
  let styleEl = document.getElementById('dynamic-project-styles');
  if (!styleEl) {
    styleEl = document.createElement('style');
    styleEl.id = 'dynamic-project-styles';
    document.head.appendChild(styleEl);
  }
  styleEl.textContent = styles;
}

// ==========================================
// API 連線層
// ==========================================
async function loadCards() {
  try {
    const currentTab = document.querySelector('.mobile-tab.active')?.dataset?.col || null;
    const res = await fetch('api/cards.php?action=list');
    const data = await res.json();
    if (data.success === false) { toast('❌ ' + (data.error || '讀取失敗')); return; }
    state = { 
      lib: data.lib || [], 
      week: data.week || [], 
      focus: data.focus || [], 
      done: data.done || [],
      goal: data.goal || []
    };
    updateProjectLabels();
    render();
    renderGoalTree();
    if (currentTab && window.innerWidth <= 768) {
      setMobileTab(currentTab);
    }
  } catch (err) { toast('連線異常，無法讀取卡片'); }
}

async function saveCardToAPI(cardData) {
  try {
    const res = await fetch('api/cards.php?action=save', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(cardData)
    });
    const data = await res.json();
    if (data.success) {
      toast(cardData.id ? '✅ 卡片更新成功' : '✅ 卡片新增成功');
      const currentTab = document.querySelector('.mobile-tab.active')?.dataset?.col || null;
      const editingId = cardData.id || null;
      const newId = !cardData.id ? (data.data?.id || null) : null;
      await loadCards();
      if (currentTab && window.innerWidth <= 768) setMobileTab(currentTab);

      setTimeout(() => {
        if (editingId) {
          // 編輯：捲回原卡片
          const cardEl = document.getElementById('card-' + editingId);
          if (cardEl) cardEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else if (newId) {
          // 新增：捲到新卡片並高亮
          const newCardEl = document.getElementById('card-' + newId);
          if (newCardEl) {
            newCardEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            newCardEl.style.outline = '2px solid var(--accent-week)';
            newCardEl.style.outlineOffset = '2px';
            setTimeout(() => { newCardEl.style.outline = ''; newCardEl.style.outlineOffset = ''; }, 2000);
          }
        }
      }, 150);

      // 不自動關閉 modal，顯示「返回外部」按鈕
      const backBtn = document.getElementById('modal-back-btn');
      if (backBtn) backBtn.style.display = 'inline-flex';
    } else toast('❌ ' + (data.error || '儲存失敗'));
  } catch (err) { toast('❌ 連線錯誤，儲存失敗'); }
}

async function moveAPI(id, toCol) {
  try {
    const res = await fetch('api/cards.php?action=move', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id, col: toCol })
    });
    const data = await res.json();
    if (data.success) {
      if (toCol === 'done') toast('✓ 完成任務！繼續衝');
      else if (toCol === 'focus') toast('🎯 專注模式開始！');
      else if (toCol === 'lib') toast('📚 已退回策略庫');
      else if (toCol === 'week') toast('📅 已加入本週目標');
      await loadCards();
    } else { toast('❌ ' + (data.error || '移動失敗')); await loadCards(); }
  } catch (err) { toast('連線錯誤'); await loadCards(); }
}

async function deleteAPI(id) {
  if (!confirm('確定要刪除這張卡片嗎？此動作無法復原。')) return;
  try {
    const res = await fetch(`api/cards.php?action=delete&id=${id}`, { method: 'GET' });
    const data = await res.json();
    if (data.success) { toast('🗑️ 卡片已刪除'); await loadCards(); } 
    else toast('❌ ' + (data.error || '刪除失敗'));
  } catch (err) { toast('連線錯誤'); }
}

async function logout() {
  if (!confirm('確定要登出嗎？')) return;
  try {
    const res = await fetch('api/auth.php?action=logout', { method: 'POST' });
    const data = await res.json();
    if (data.success) window.location.href = 'login.php';
    else toast('登出失敗');
  } catch (err) { toast('登出發生錯誤'); }
}

// ==========================================
// 渲染邏輯
// ==========================================
function shouldShowCard(card) {
  // 隐私筛选
  if (privacyFilter === 'shared' && card.isPrivate) return false;
  if (privacyFilter === 'private' && !card.isPrivate) return false;
  
  // 项目筛选
  if (currentFilter && card.project !== currentFilter) return false;
  
  // 搜索筛选
  if (searchQuery) {
    const q = searchQuery.toLowerCase(), t = (card.title || '').toLowerCase(), s = (card.summary || '').toLowerCase(), b = stripHtml(card.body || '').toLowerCase(), n = (card.nextStep || '').toLowerCase();
    if (!t.includes(q) && !s.includes(q) && !b.includes(q) && !n.includes(q)) return false;
  }
  return true;
}

function stripHtml(html) {
  const tmp = document.createElement('div');
  tmp.innerHTML = html;
  return tmp.textContent || tmp.innerText || '';
}

// ==========================================
// 戰略樹 - 全局狀態
// ==========================================
let goalPanelCollapsed = false;
let goalFilterParentId = null; // 點擊月/週目標時高亮右側子任務

// 欄位收折（策略筆記＋本週目標）
function toggleColSection(col) {
  const body = document.getElementById('col-body-' + col);
  const chev = document.getElementById('col-chev-' + col);
  if (!body) return;
  const isCollapsed = body.classList.contains('collapsed');
  if (isCollapsed) {
    // 展開：恢復記憶高度
    body.classList.remove('collapsed');
    const savedH = localStorage.getItem('colHeight_' + col);
    const area = document.getElementById('cards-' + col);
    if (area && savedH) area.style.maxHeight = savedH + 'px';
    if (chev) chev.style.transform = '';
    try { localStorage.setItem('colOpen_' + col, '1'); } catch(e) {}
  } else {
    // 收折
    body.classList.add('collapsed');
    if (chev) chev.style.transform = 'rotate(180deg)';
    try { localStorage.setItem('colOpen_' + col, '0'); } catch(e) {}
  }
}

// 拖曳調整欄位高度
function startColResize(e, col) {
  e.preventDefault(); e.stopPropagation();
  const area = document.getElementById('cards-' + col);
  if (!area) return;
  const startY = e.clientY;
  const startH = area.offsetHeight;
  function onMove(ev) {
    const newH = Math.max(80, startH + ev.clientY - startY);
    area.style.maxHeight = newH + 'px';
    try { localStorage.setItem('colHeight_' + col, newH); } catch(e) {}
  }
  function onUp() {
    document.removeEventListener('mousemove', onMove);
    document.removeEventListener('mouseup', onUp);
  }
  document.addEventListener('mousemove', onMove);
  document.addEventListener('mouseup', onUp);
}

// 初始化欄位收折狀態（預設收起，打開後記住）
function initColSections() {
  ['lib', 'week'].forEach(col => {
    const body = document.getElementById('col-body-' + col);
    const chev = document.getElementById('col-chev-' + col);
    const area = document.getElementById('cards-' + col);
    if (!body) return;
    const isOpen = localStorage.getItem('colOpen_' + col) === '1';
    const savedH = localStorage.getItem('colHeight_' + col);
    if (!isOpen) {
      body.classList.add('collapsed');
      if (chev) chev.style.transform = 'rotate(180deg)';
    } else {
      if (area && savedH) area.style.maxHeight = savedH + 'px';
    }
  });
}

// 設定面板尺寸（只有 md / lg）
function setGoalPanelSize(size) {
  const panel = document.getElementById('goal-panel');
  if (!panel) return;
  panel.classList.remove('size-xs','size-sm','size-md','size-lg','size-hidden','collapsed');
  panel.classList.add('size-' + size);
  // 更新按鈕高亮
  ['md','lg'].forEach(s => {
    const btn = document.getElementById('gp-btn-' + s);
    if (btn) {
      btn.style.background = (s === size) ? 'rgba(255,255,255,0.25)' : 'none';
      btn.style.color = (s === size) ? '#fff' : 'rgba(255,255,255,0.6)';
    }
  });
  // 記住最後開啟的尺寸
  try { localStorage.setItem('goalPanelSize', size); } catch(e) {}
  // 確保面板可見，側邊標籤隱藏
  panel.style.removeProperty('display');
  const tab = document.getElementById('goal-panel-tab');
  if (tab) tab.classList.remove('visible');
}

// 收折面板（完全隱藏，顯示側邊標籤）
function hideGoalPanel() {
  const panel = document.getElementById('goal-panel');
  const tab = document.getElementById('goal-panel-tab');
  if (panel) panel.classList.add('size-hidden');
  if (tab) tab.classList.add('visible');
  try { localStorage.setItem('goalPanelHidden', '1'); } catch(e) {}
}

// 展開面板（從側邊標籤點開）
function openGoalPanel() {
  const panel = document.getElementById('goal-panel');
  const tab = document.getElementById('goal-panel-tab');
  if (panel) {
    panel.classList.remove('size-hidden');
    // 恢復上次的尺寸
    const lastSize = localStorage.getItem('goalPanelSize') || 'lg';
    setGoalPanelSize(lastSize);
  }
  if (tab) tab.classList.remove('visible');
  try { localStorage.removeItem('goalPanelHidden'); } catch(e) {}
}

// 相容舊呼叫
function toggleGoalPanel() { hideGoalPanel(); }

// 初始化（永遠展開，恢復記憶尺寸）
function initGoalPanelState() {
  try {
    localStorage.removeItem('goalPanelCollapsed');
    const wasHidden = localStorage.getItem('goalPanelHidden') === '1';
    if (wasHidden) {
      hideGoalPanel();
    } else {
      const lastSize = localStorage.getItem('goalPanelSize') || 'lg';
      setGoalPanelSize(lastSize);
    }
  } catch(e) {
    setGoalPanelSize('lg');
  }
}

// 計算進度（每層只算直接子項）
// 年度：看月度完成數/月度總數
// 月度：看週完成數/週總數
// 週：看右側子任務完成數/總數
function calcGoalProgress(goalCard) {
  const goalChildren = state.goal.filter(c => c.parentId === goalCard.id);
  const allCards = [...state.lib, ...state.week, ...state.focus, ...state.done];
  const directTasks = allCards.filter(c => c.parentId === goalCard.id);

  // 有左側子目標（月度、週）→ 以子目標完成率計算
  if (goalChildren.length > 0) {
    let done = 0;
    const total = goalChildren.length;
    goalChildren.forEach(child => {
      const childP = calcGoalProgress(child);
      // 子目標「完成」的定義：childP.total > 0 且 childP.done === childP.total
      if (childP.total > 0 && childP.done === childP.total) done++;
      // 或者子目標沒有任何子項但有被標記完成（暫不支援，保留擴充）
    });
    return { done, total };
  }

  // 沒有左側子目標 → 看右側子任務
  if (directTasks.length > 0) {
    const done = directTasks.filter(c => c.col === 'done' || !!c.completedAt).length;
    return { done, total: directTasks.length };
  }

  return { done: 0, total: 0 };
}

// 渲染戰略樹
function renderGoalTree() {
  const container = document.getElementById('goal-panel-body');
  const mobileContainer = document.getElementById('mobile-goal-body');

  const goalCards = state.goal || [];
  const yearCards  = goalCards.filter(c => c.level === 'year');
  // 獨立月度：沒有父層或父層不在 goal 裡
  const looseMonths = goalCards.filter(c => c.level === 'month' && !goalCards.find(p => p.id === c.parentId));
  // 獨立週目標：沒有父層月度
  const looseWeeks  = goalCards.filter(c => c.level === 'week'  && !goalCards.find(p => p.id === c.parentId));

  const isEmpty = yearCards.length === 0 && looseMonths.length === 0 && looseWeeks.length === 0;

  if (container) {
    container.innerHTML = '';
    if (isEmpty) {
      container.innerHTML = `<div style="text-align:center;padding:24px 16px;color:rgba(255,255,255,0.3);font-size:12px;line-height:1.8;">
        還沒有目標<br>點下方按鈕建立第一個
      </div>`;
    } else {
      yearCards.forEach(year => container.appendChild(buildYearCard(year, goalCards)));
      looseMonths.forEach(month => container.appendChild(buildMonthCard(month, goalCards, null)));
      looseWeeks.forEach(week => container.appendChild(buildWeekCard(week, null, null)));
    }
  }

  if (mobileContainer) {
    mobileContainer.innerHTML = '';
    if (isEmpty) {
      mobileContainer.innerHTML = `<div style="text-align:center;padding:32px 16px;color:var(--text-muted);font-size:13px;">還沒有目標</div>`;
    } else {
      yearCards.forEach(year => mobileContainer.appendChild(buildMobileYearCard(year, goalCards)));
      looseMonths.forEach(month => mobileContainer.appendChild(buildMobileMonthCard(month, goalCards)));
      looseWeeks.forEach(week => mobileContainer.appendChild(buildMobileWeekCard(week)));
    }
  }
}

// 手機版年度計畫（淺色主題）
function buildMobileYearCard(year, allGoalCards) {
  const monthCards = allGoalCards.filter(c => c.parentId === year.id && c.level === 'month');
  const progress = calcGoalProgress(year);
  const pct = progress.total > 0 ? Math.round(progress.done / progress.total * 100) : 0;
  const isComplete = progress.total > 0 && progress.done === progress.total;

  const div = document.createElement('div');
  div.style.cssText = 'background:var(--surface);border:1px solid var(--border-strong);border-radius:12px;margin-bottom:12px;overflow:hidden;';

  div.innerHTML = `
    <div style="padding:12px 14px;background:linear-gradient(135deg,#534AB7,#7C3AED);cursor:pointer;display:flex;align-items:center;gap:10px;"
      onclick="this.closest('div').querySelector('.m-year-body').style.display=this.closest('div').querySelector('.m-year-body').style.display==='none'?'block':'none'">
      <span style="font-size:16px;">📌</span>
      <div style="flex:1;min-width:0;">
        <div style="font-size:14px;font-weight:700;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escHtml(year.title)}</div>
        <div style="font-size:11px;color:rgba(255,255,255,0.7);margin-top:2px;">${pct}% 完成 · ${progress.done}/${progress.total} 任務</div>
      </div>
      <button onclick="openGoalModal('month',${year.id});event.stopPropagation()" style="background:rgba(255,255,255,0.2);border:none;color:#fff;border-radius:6px;padding:4px 10px;font-size:12px;cursor:pointer;flex-shrink:0;">＋月</button>
    </div>
    <div style="height:4px;background:rgba(0,0,0,0.1);"><div style="height:100%;background:${isComplete?'#22c55e':'#7C3AED'};width:${pct}%;transition:width 0.6s;border-radius:0 2px 2px 0;"></div></div>
    <div class="m-year-body" style="display:block;padding:8px;">
      <div id="m-year-children-${year.id}"></div>
    </div>
  `;

  // 渲染月度卡
  const monthWeight = monthCards.length > 0 ? Math.round(100 / monthCards.length) : 0;
  const childrenEl = div.querySelector(`#m-year-children-${year.id}`);
  monthCards.forEach(month => childrenEl.appendChild(buildMobileMonthCard(month, allGoalCards, monthWeight)));
  if (monthCards.length === 0) {
    childrenEl.innerHTML = `<div style="text-align:center;padding:16px;color:var(--text-muted);font-size:12px;">點上方「＋月」新增月度目標</div>`;
  }

  return div;
}

function buildMobileMonthCard(month, allGoalCards, parentWeight) {
  const weekCards = allGoalCards.filter(c => c.parentId === month.id && c.level === 'week');
  const progress = calcGoalProgress(month);
  const pct = progress.total > 0 ? Math.round(progress.done / progress.total * 100) : 0;
  const isComplete = progress.total > 0 && progress.done === progress.total;
  const weekCount = weekCards.length;
  const weekWeight = weekCount > 0 ? Math.round(100 / weekCount) : 0;

  const div = document.createElement('div');
  div.style.cssText = 'margin-bottom:4px;';

  const header = document.createElement('div');
  header.style.cssText = `display:flex;align-items:center;gap:6px;padding:7px 10px;border-radius:8px;background:${isComplete?'rgba(34,197,94,0.08)':'rgba(99,102,241,0.06)'};border:1px solid ${isComplete?'rgba(34,197,94,0.3)':'rgba(99,102,241,0.2)'};cursor:pointer;user-select:none;`;
  header.innerHTML = `
    <span style="font-size:10px;color:#4338CA;flex-shrink:0;" id="m-month-chev-${month.id}">▼</span>
    <span style="font-size:13px;flex-shrink:0;">${isComplete?'✅':'📅'}</span>
    <span style="font-size:13px;font-weight:600;color:${isComplete?'#16803C':'#3730A3'};flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escHtml(month.title)}</span>
    <span style="font-size:11px;color:#4338CA;">${pct}%</span>
    <button onclick="openGoalModal('week',${month.id});event.stopPropagation()" style="background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.25);color:#3730A3;border-radius:6px;padding:3px 8px;font-size:11px;cursor:pointer;flex-shrink:0;font-weight:700;">＋週</button>
  `;
  const bar = document.createElement('div');
  bar.style.cssText = 'height:2px;background:rgba(99,102,241,0.1);margin:0 1px 1px;';
  bar.innerHTML = `<div style="height:100%;width:${pct}%;background:${isComplete?'#16803C':'#6366f1'};transition:width 0.6s;"></div>`;

  const body = document.createElement('div');
  body.id = `m-month-children-${month.id}`;
  body.style.cssText = 'padding-left:12px;border-left:2px solid rgba(99,102,241,0.2);margin-left:10px;margin-top:2px;';

  let open = true;
  header.addEventListener('click', e => {
    if (e.target.closest('button')) return;
    open = !open;
    body.style.display = open ? 'block' : 'none';
    const chev = document.getElementById('m-month-chev-' + month.id);
    if (chev) chev.style.transform = open ? '' : 'rotate(90deg)';
  });

  weekCards.forEach(week => body.appendChild(buildMobileWeekCard(week, weekWeight)));  if (weekCards.length === 0) {
    body.innerHTML = `<div style="padding:6px 4px;color:var(--text-muted);font-size:11px;">點「＋週」新增週目標</div>`;
  }

  div.appendChild(header);
  div.appendChild(bar);
  div.appendChild(body);
  return div;
}

function buildMobileWeekCard(week, weekWeight) {
  const allCards = [...state.lib, ...state.week, ...state.focus, ...state.done];
  const children = allCards.filter(c => c.parentId === week.id);
  const doneCount = children.filter(c => c.col === 'done' || !!c.completedAt).length;
  const total = children.length;
  const pct = total > 0 ? Math.round(doneCount / total * 100) : 0;
  const isComplete = total > 0 && doneCount === total;

  const div = document.createElement('div');
  div.style.cssText = 'margin-bottom:3px;';

  const header = document.createElement('div');
  header.style.cssText = `display:flex;align-items:center;gap:5px;padding:6px 8px;border-radius:5px;background:${isComplete?'rgba(22,128,60,0.06)':'rgba(249,115,22,0.05)'};border-left:3px solid ${isComplete?'#16803C':'#C2560A'};border-top:1px solid ${isComplete?'rgba(22,128,60,0.15)':'rgba(194,86,10,0.12)'};border-right:1px solid ${isComplete?'rgba(22,128,60,0.15)':'rgba(194,86,10,0.12)'};border-bottom:1px solid ${isComplete?'rgba(22,128,60,0.15)':'rgba(194,86,10,0.12)'};cursor:pointer;user-select:none;`;
  header.innerHTML = `
    <span style="font-size:9px;color:#C2560A;flex-shrink:0;" id="m-week-chev-${week.id}">${total>0?'▼':'─'}</span>
    <span style="font-size:12px;flex-shrink:0;">${isComplete?'✅':'📋'}</span>
    <span style="font-size:12px;font-weight:600;color:${isComplete?'#16803C':'var(--text)'};flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escHtml(week.title)}</span>
    <span style="font-size:10px;color:${isComplete?'#16803C':'#C2560A'};flex-shrink:0;">${doneCount}/${total}</span>
    ${weekWeight?`<span style="font-size:10px;color:#7A4F00;flex-shrink:0;">月+${weekWeight}%</span>`:''}
    <button onclick="event.stopPropagation();spawnProjectCard(${week.id},'${escHtml(week.title)}')" style="background:rgba(194,86,10,0.1);border:none;color:#C2560A;border-radius:3px;padding:2px 6px;font-size:11px;cursor:pointer;flex-shrink:0;font-weight:700;">＋</button>
    <button onclick="filterByParent(${week.id});switchMobileTab('lib');event.stopPropagation()" style="background:rgba(0,0,0,0.05);border:none;color:var(--text-secondary);border-radius:3px;padding:2px 5px;font-size:11px;cursor:pointer;flex-shrink:0;">🔍</button>
  `;

  const bar = document.createElement('div');
  bar.style.cssText = 'height:2px;background:rgba(249,115,22,0.1);margin:0 1px 1px;';
  bar.innerHTML = `<div style="height:100%;width:${pct}%;background:${isComplete?'#16803C':'#f97316'};transition:width 0.6s;"></div>`;

  const taskList = document.createElement('div');
  taskList.style.cssText = 'padding-left:8px;border-left:2px solid rgba(249,115,22,0.12);margin-left:10px;margin-top:2px;';
  if (children.length > 0) {
    children.forEach(c => {
      const isDone = c.col === 'done' || !!c.completedAt;
      const row = document.createElement('div');
      row.style.cssText = `display:flex;align-items:center;gap:5px;padding:3px 4px;font-size:11px;color:${isDone?'#16803C':'var(--text-secondary)'};${isDone?'text-decoration:line-through;opacity:0.65;':''}`;
      row.innerHTML = `<span style="flex-shrink:0;">${isDone?'✅':'⬜'}</span><span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escHtml(c.title)}</span>`;
      taskList.appendChild(row);
    });
  }

  let open = true;
  header.addEventListener('click', e => {
    if (e.target.closest('button')) return;
    if (total === 0) return;
    open = !open;
    taskList.style.display = open ? 'block' : 'none';
    const chev = document.getElementById('m-week-chev-' + week.id);
    if (chev) chev.style.transform = open ? '' : 'rotate(90deg)';
  });

  div.appendChild(header);
  div.appendChild(bar);
  if (children.length > 0) div.appendChild(taskList);
  return div;
}

// 建立年度卡（縮排清單樹）
function buildYearCard(year, allGoalCards) {
  const monthCards = allGoalCards.filter(c => c.parentId === year.id && c.level === 'month');
  const progress = calcGoalProgress(year);
  const pct = progress.total > 0 ? Math.round(progress.done / progress.total * 100) : 0;
  const isComplete = progress.total > 0 && progress.done === progress.total;
  const monthCount = monthCards.length;
  const monthWeight = monthCount > 0 ? Math.round(100 / monthCount) : 0;

  const div = document.createElement('div');
  div.id = 'goal-year-' + year.id;
  div.style.cssText = 'margin-bottom:8px;';

  // 年度列
  const header = document.createElement('div');
  header.style.cssText = `
    display:flex;align-items:center;gap:6px;
    padding:6px 8px;border-radius:8px;
    background:rgba(255,215,0,0.08);
    border:1px solid rgba(180,140,0,${isComplete?'0.5':'0.2'});
    cursor:pointer;user-select:none;
  `;
  header.innerHTML = `
    <span style="font-size:9px;font-weight:700;background:#92700A;color:#FFF8DC;border-radius:3px;padding:1px 5px;flex-shrink:0;letter-spacing:0.5px;">年</span>
    <span id="year-chevron-${year.id}" style="font-size:10px;color:#B8860B;flex-shrink:0;transition:transform 0.2s;">▼</span>
    <span style="font-size:12px;flex-shrink:0;">${isComplete?'🏆':'📌'}</span>
    <span style="font-size:12px;font-weight:700;color:${isComplete?'#16803C':'#92700A'};flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escHtml(year.title)}">${escHtml(year.title)}</span>
    <span style="font-size:10px;color:${isComplete?'#16803C':'#92700A'};flex-shrink:0;font-weight:700;">${pct}%</span>
    <button onclick="event.stopPropagation();editGoalCard(${year.id},event)" style="background:rgba(0,0,0,0.06);border:none;color:var(--text-secondary);border-radius:4px;padding:1px 5px;font-size:10px;cursor:pointer;flex-shrink:0;">✏️</button>
    <button onclick="event.stopPropagation();deleteGoalCard(${year.id},event)" style="background:rgba(226,75,74,0.1);border:none;color:#C0392B;border-radius:4px;padding:1px 5px;font-size:10px;cursor:pointer;flex-shrink:0;">🗑</button>
    <button onclick="event.stopPropagation();openGoalModal('month',${year.id})" style="background:rgba(180,140,0,0.12);border:none;color:#92700A;border-radius:4px;padding:1px 5px;font-size:10px;cursor:pointer;flex-shrink:0;font-weight:700;">＋月</button>
  `;

  // 進度條
  const bar = document.createElement('div');
  bar.style.cssText = 'height:3px;background:rgba(255,215,0,0.1);border-radius:0 0 4px 4px;overflow:hidden;margin:0 1px;';
  bar.innerHTML = `<div style="height:100%;width:${pct}%;background:${isComplete?'#22c55e':'#FFD700'};transition:width 0.6s;"></div>`;

  // 摘要列
  const meta = document.createElement('div');
  meta.style.cssText = 'font-size:10px;color:#7A6000;padding:3px 8px 5px;display:flex;gap:8px;';
  meta.innerHTML = monthCount > 0
    ? `<span>共 ${monthCount} 個月度</span><span style="opacity:0.5">·</span><span>每月完成貢獻 <b style="color:#92700A">${monthWeight}%</b> 年度進度</span>`
    : `<span style="color:var(--text-muted);">尚未新增月度目標</span>`;

  // 子層容器
  const children = document.createElement('div');
  children.id = 'year-children-' + year.id;
  children.style.cssText = 'padding-left:14px;border-left:2px solid rgba(255,215,0,0.2);margin-left:10px;margin-top:2px;';

  // 收折切換
  let open = true;
  header.addEventListener('click', (e) => {
    if (e.target.closest('button')) return;
    open = !open;
    children.style.display = open ? 'block' : 'none';
    meta.style.display = open ? 'flex' : 'none';
    document.getElementById('year-chevron-' + year.id).style.transform = open ? '' : 'rotate(90deg)';
  });

  monthCards.forEach(month => children.appendChild(buildMonthCard(month, allGoalCards, monthWeight)));

  div.appendChild(header);
  div.appendChild(bar);
  div.appendChild(meta);
  div.appendChild(children);
  return div;
}

// 建立月度卡（縮排清單樹）
function buildMonthCard(month, allGoalCards, parentWeight) {
  const weekCards = allGoalCards.filter(c => c.parentId === month.id && c.level === 'week');
  const progress = calcGoalProgress(month);
  const pct = progress.total > 0 ? Math.round(progress.done / progress.total * 100) : 0;
  const isComplete = progress.total > 0 && progress.done === progress.total;
  const weekCount = weekCards.length;
  const weekWeight = weekCount > 0 ? Math.round(100 / weekCount) : 0;

  const div = document.createElement('div');
  div.id = 'goal-month-' + month.id;
  div.style.cssText = 'margin-bottom:4px;margin-top:4px;';

  const header = document.createElement('div');
  header.style.cssText = `
    display:flex;align-items:center;gap:5px;
    padding:5px 8px;border-radius:6px;
    background:${isComplete?'rgba(34,197,94,0.08)':'rgba(99,102,241,0.06)'};
    border:1px solid ${isComplete?'rgba(34,197,94,0.35)':'rgba(99,102,241,0.25)'};
    cursor:pointer;user-select:none;
  `;
  header.innerHTML = `
    <span style="font-size:9px;font-weight:700;background:#3730A3;color:#EEF2FF;border-radius:3px;padding:1px 5px;flex-shrink:0;letter-spacing:0.5px;">月</span>
    <span id="month-chevron-${month.id}" style="font-size:10px;color:#4338CA;flex-shrink:0;transition:transform 0.2s;">▼</span>
    <span style="font-size:11px;flex-shrink:0;">${isComplete?'✅':'📅'}</span>
    <span style="font-size:11px;font-weight:700;color:${isComplete?'#16803C':'#3730A3'};flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escHtml(month.title)}">${escHtml(month.title)}</span>
    <span style="font-size:10px;color:${isComplete?'#16803C':'#4338CA'};flex-shrink:0;">${progress.done}/${progress.total}</span>
    ${parentWeight ? `<span style="font-size:10px;color:#92700A;flex-shrink:0;margin-left:2px;font-weight:600;">年+${parentWeight}%</span>` : ''}
    <button onclick="event.stopPropagation();editGoalCard(${month.id},event)" style="background:rgba(0,0,0,0.05);border:none;color:var(--text-secondary);border-radius:3px;padding:1px 4px;font-size:10px;cursor:pointer;flex-shrink:0;">✏️</button>
    <button onclick="event.stopPropagation();deleteGoalCard(${month.id},event)" style="background:rgba(226,75,74,0.08);border:none;color:#C0392B;border-radius:3px;padding:1px 4px;font-size:10px;cursor:pointer;flex-shrink:0;">🗑</button>
    <button onclick="event.stopPropagation();openGoalModal('week',${month.id})" style="background:rgba(99,102,241,0.12);border:none;color:#3730A3;border-radius:3px;padding:1px 4px;font-size:10px;cursor:pointer;flex-shrink:0;font-weight:700;">＋週</button>
  `;

  // 月度進度條
  const bar = document.createElement('div');
  bar.style.cssText = 'height:2px;background:rgba(99,102,241,0.1);border-radius:1px;overflow:hidden;margin:0 1px 1px;';
  bar.innerHTML = `<div style="height:100%;width:${pct}%;background:${isComplete?'#22c55e':'#818cf8'};transition:width 0.6s;"></div>`;

  // 週的子層
  const children = document.createElement('div');
  children.id = 'month-children-' + month.id;
  children.style.cssText = 'padding-left:12px;border-left:2px solid rgba(99,102,241,0.2);margin-left:10px;margin-top:2px;';

  let open = true;
  header.addEventListener('click', (e) => {
    if (e.target.closest('button')) return;
    open = !open;
    children.style.display = open ? 'block' : 'none';
    document.getElementById('month-chevron-' + month.id).style.transform = open ? '' : 'rotate(90deg)';
  });

  weekCards.forEach(week => children.appendChild(buildWeekCard(week, weekWeight, parentWeight)));

  div.appendChild(header);
  div.appendChild(bar);
  div.appendChild(children);
  return div;
}

// 建立週卡（縮排清單樹）
function buildWeekCard(week, weekWeight, monthWeight) {
  const allCards = [...state.lib, ...state.week, ...state.focus, ...state.done];
  const children = allCards.filter(c => c.parentId === week.id);
  const doneCount = children.filter(c => c.col === 'done' || !!c.completedAt).length;
  const total = children.length;
  const pct = total > 0 ? Math.round(doneCount / total * 100) : 0;
  const isComplete = total > 0 && doneCount === total;
  const isFiltered = goalFilterParentId === week.id;
  const yearContrib = (weekWeight && monthWeight) ? Math.round(weekWeight * monthWeight / 100) : null;

  const div = document.createElement('div');
  div.id = 'goal-week-' + week.id;
  div.dataset.weekId = week.id;
  div.style.cssText = 'margin-bottom:3px;margin-top:3px;';

  // 週標題列
  const header = document.createElement('div');
  header.style.cssText = `
    display:flex;align-items:center;gap:5px;
    padding:4px 8px;border-radius:5px;
    background:${isFiltered ? 'rgba(22,128,60,0.08)' : isComplete ? 'rgba(22,128,60,0.06)' : 'rgba(249,115,22,0.06)'};
    border-left:3px solid ${isFiltered ? '#16803C' : isComplete ? '#16803C' : '#C2560A'};
    border-top:1px solid ${isFiltered ? 'rgba(22,128,60,0.2)' : 'rgba(194,86,10,0.15)'};
    border-right:1px solid ${isFiltered ? 'rgba(22,128,60,0.2)' : 'rgba(194,86,10,0.15)'};
    border-bottom:1px solid ${isFiltered ? 'rgba(22,128,60,0.2)' : 'rgba(194,86,10,0.15)'};
    cursor:pointer;user-select:none;
  `;
  header.innerHTML = `
    <span style="font-size:9px;font-weight:700;background:#C2560A;color:#FFF7ED;border-radius:3px;padding:1px 5px;flex-shrink:0;letter-spacing:0.5px;">週</span>
    <span id="week-chevron-${week.id}" style="font-size:9px;color:#C2560A;flex-shrink:0;transition:transform 0.2s;">${total > 0 ? '▼' : '─'}</span>
    <span style="font-size:11px;flex-shrink:0;">${isComplete ? '✅' : '📋'}</span>
    <span style="font-size:11px;font-weight:600;color:${isComplete ? '#16803C' : 'var(--text)'};flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escHtml(week.title)}">${escHtml(week.title)}</span>
    <span style="font-size:10px;color:${isComplete ? '#16803C' : '#C2560A'};flex-shrink:0;font-weight:600;">${doneCount}/${total}</span>
    ${weekWeight ? `<span style="font-size:10px;color:#7A4F00;flex-shrink:0;margin-left:2px;">月+${weekWeight}%</span>` : ''}
    ${yearContrib ? `<span style="font-size:10px;color:#92700A;flex-shrink:0;">年+${yearContrib}%</span>` : ''}
    <button onclick="event.stopPropagation();spawnProjectCard(${week.id},'${escHtml(week.title)}')" style="background:rgba(194,86,10,0.12);border:none;color:#C2560A;border-radius:3px;padding:1px 5px;font-size:11px;cursor:pointer;flex-shrink:0;font-weight:700;">＋</button>
    <button onclick="event.stopPropagation();filterByParent(${week.id})" style="background:rgba(0,0,0,0.05);border:none;color:var(--text-secondary);border-radius:3px;padding:1px 4px;font-size:10px;cursor:pointer;flex-shrink:0;">🔍</button>
    <button onclick="event.stopPropagation();editGoalCard(${week.id},event)" style="background:rgba(0,0,0,0.05);border:none;color:var(--text-secondary);border-radius:3px;padding:1px 4px;font-size:10px;cursor:pointer;flex-shrink:0;">✏️</button>
    <button onclick="event.stopPropagation();deleteGoalCard(${week.id},event)" style="background:rgba(226,75,74,0.08);border:none;color:#C0392B;border-radius:3px;padding:1px 4px;font-size:10px;cursor:pointer;flex-shrink:0;">🗑</button>
  `;

  // 週進度條
  const bar = document.createElement('div');
  bar.style.cssText = 'height:2px;background:rgba(249,115,22,0.1);border-radius:1px;overflow:hidden;margin:0 1px 1px;';
  bar.innerHTML = `<div style="height:100%;width:${pct}%;background:${isComplete ? '#22c55e' : '#f97316'};transition:width 0.6s;"></div>`;

  // 子任務列表
  const taskList = document.createElement('div');
  taskList.id = 'week-tasks-' + week.id;
  taskList.style.cssText = 'padding-left:10px;border-left:2px solid rgba(249,115,22,0.15);margin-left:10px;margin-top:2px;';

  if (children.length > 0) {
    children.forEach(c => {
      const isDone = c.col === 'done' || !!c.completedAt;
      const row = document.createElement('div');
      row.style.cssText = `
        display:flex;align-items:center;gap:6px;
        padding:3px 6px;font-size:10px;
        color:${isDone ? '#16803C' : 'var(--text-secondary)'};
        border-radius:3px;cursor:pointer;
        ${isDone ? 'text-decoration:line-through;opacity:0.65;' : ''}
      `;
      row.innerHTML = `
        <span style="flex-shrink:0;font-size:11px;">${isDone ? '✅' : '⬜'}</span>
        <span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escHtml(c.title)}</span>
      `;
      row.onclick = () => filterByParent(week.id);
      taskList.appendChild(row);
    });
  }

  // 收折切換
  let open = true;
  header.addEventListener('click', (e) => {
    if (e.target.closest('button')) return;
    if (total === 0) return;
    open = !open;
    taskList.style.display = open ? 'block' : 'none';
    bar.style.display = open ? 'block' : 'none';
    const chev = document.getElementById('week-chevron-' + week.id);
    if (chev) chev.style.transform = open ? '' : 'rotate(90deg)';
  });

  div.appendChild(header);
  div.appendChild(bar);
  if (children.length > 0) div.appendChild(taskList);
  return div;
}

// ==========================================
// 目標樹 匯入 / 匯出
// ==========================================

let _importData = null; // 暫存解析後的匯入資料

// 讀取 JSON 檔案
function handleGoalImportFile(input) {
  const file = input.files[0];
  if (!file) { alert('沒有選到檔案'); return; }

  const reader = new FileReader();
  reader.onerror = () => alert('FileReader 錯誤：' + reader.error);
  reader.onload = (e) => {
    try {
      openGoalImportModal();
      const text = e.target.result;
      let data;
      try {
        data = JSON.parse(text);
      } catch(parseErr) {
        showGoalImportError('JSON 解析失敗：' + parseErr.message);
        return;
      }
      const inputArea = document.getElementById('goal-import-input-area');
      if (inputArea) inputArea.style.display = 'none';
      showGoalImportPreview(data);
    } catch(err) {
      alert('匯入發生錯誤：' + err.message + '\n' + err.stack);
    }
  };
  reader.readAsText(file);
  // 重置 value 放最後，避免影響 FileReader
  setTimeout(() => { input.value = ''; }, 1000);
}

function openGoalImportModal() {
  const overlay = document.getElementById('goal-import-overlay');
  if (!overlay) { alert('找不到匯入 Modal，請重新整理頁面'); return; }
  overlay.style.display = 'flex';
  resetGoalImport();
}

function resetGoalImport() {
  ['goal-import-preview','goal-import-error','goal-import-progress','goal-import-done','goal-import-conflict'].forEach(id => {
    const el = document.getElementById(id); if (el) el.style.display = 'none';
  });
  const btn = document.getElementById('goal-import-confirm-btn');
  if (btn) btn.style.display = 'none';
  const inputArea = document.getElementById('goal-import-input-area');
  if (inputArea) inputArea.style.display = 'block';
  const ta = document.getElementById('goal-import-textarea');
  if (ta) { ta.value = ''; ta.style.borderColor = 'var(--border)'; }
  _importData = null;
}

function switchImportTab(tab) {
  const pasteArea = document.getElementById('gim-paste-area');
  const fileArea = document.getElementById('gim-file-area');
  const pasteBtn = document.getElementById('gim-tab-paste');
  const fileBtn = document.getElementById('gim-tab-file');
  if (tab === 'paste') {
    pasteArea.style.display = 'block';
    fileArea.style.display = 'none';
    pasteBtn.style.background = '#534AB7'; pasteBtn.style.color = '#fff'; pasteBtn.style.borderColor = '#534AB7';
    fileBtn.style.background = 'var(--surface2)'; fileBtn.style.color = 'var(--text-secondary)'; fileBtn.style.borderColor = 'var(--border)';
  } else {
    pasteArea.style.display = 'none';
    fileArea.style.display = 'block';
    fileBtn.style.background = '#534AB7'; fileBtn.style.color = '#fff'; fileBtn.style.borderColor = '#534AB7';
    pasteBtn.style.background = 'var(--surface2)'; pasteBtn.style.color = 'var(--text-secondary)'; pasteBtn.style.borderColor = 'var(--border)';
  }
}

function parseImportTextarea() {
  const ta = document.getElementById('goal-import-textarea');
  const text = ta ? ta.value.trim() : '';
  if (!text) { showGoalImportError('請先貼上 JSON 內容'); return; }
  try {
    const data = JSON.parse(text);
    _importData = null;
    document.getElementById('goal-import-input-area').style.display = 'none';
    showGoalImportPreview(data);
  } catch(err) {
    showGoalImportError('JSON 格式錯誤：' + err.message);
  }
}

async function fetchImportUrl() {
  const urlInput = document.getElementById('goal-import-url');
  const url = urlInput ? urlInput.value.trim() : '';
  if (!url) { showGoalImportError('請先貼上 JSON 檔案的網址'); return; }

  // 直接用 fetch 抓（同網域或 CORS 允許的連結）
  const errEl = document.getElementById('goal-import-error');
  errEl.style.display = 'none';
  urlInput.disabled = true;

  try {
    // 先嘗試直接 fetch
    const res = await fetch(url);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const text = await res.text();
    const data = JSON.parse(text);
    urlInput.disabled = false;
    document.getElementById('goal-import-input-area').style.display = 'none';
    showGoalImportPreview(data);
  } catch(err) {
    urlInput.disabled = false;
    // 如果直接抓失敗（CORS），把內容填到 textarea 讓使用者手動貼
    showGoalImportError('無法直接抓取該網址（可能是權限問題）。請改用「複製原始內容」再貼到下方文字框。');
  }
}

function closeGoalImport() {
  document.getElementById('goal-import-overlay').style.display = 'none';
  _importData = null;
}

// 解析並預覽匯入資料
function showGoalImportPreview(raw) {
  try {
  // 支援兩種格式：
  // 格式A：{ years: [...] }
  // 格式B：直接一個 { title, summary, months: [...] }
  let years = [];
  if (Array.isArray(raw.years)) {
    years = raw.years;
  } else if (raw.title && Array.isArray(raw.months)) {
    years = [raw];
  } else if (Array.isArray(raw)) {
    years = raw;
  } else {
    showGoalImportError('找不到有效的目標資料，請確認 JSON 格式是否正確。');
    return;
  }

  if (years.length === 0) {
    showGoalImportError('JSON 內沒有任何目標資料。');
    return;
  }

  // 統計
  let totalMonths = 0, totalWeeks = 0;
  let conflicts = [];
  const existingTitles = (state.goal || []).map(c => c.title.trim().toLowerCase());

  let previewHTML = '';
  years.forEach(year => {
    const yConflict = existingTitles.includes((year.title||'').trim().toLowerCase());
    if (yConflict) conflicts.push({ level: '年度', title: year.title });
    previewHTML += `<div style="display:flex;align-items:center;gap:6px;margin-bottom:4px;">
      <span style="font-size:9px;font-weight:700;background:#92700A;color:#FFF8DC;border-radius:3px;padding:1px 5px;">年</span>
      <span style="font-weight:700;color:#7A5C00;">${escHtml(year.title||'')}</span>
      ${yConflict ? '<span style="font-size:10px;color:#C2560A;">⚠️ 同名</span>' : ''}
    </div>`;

    (year.months || []).forEach(month => {
      totalMonths++;
      const mConflict = existingTitles.includes((month.title||'').trim().toLowerCase());
      if (mConflict) conflicts.push({ level: '月度', title: month.title });
      previewHTML += `<div style="display:flex;align-items:center;gap:6px;margin-bottom:3px;padding-left:16px;">
        <span style="font-size:9px;font-weight:700;background:#3730A3;color:#EEF2FF;border-radius:3px;padding:1px 5px;">月</span>
        <span style="color:#3730A3;">${escHtml(month.title||'')}</span>
        ${mConflict ? '<span style="font-size:10px;color:#C2560A;">⚠️ 同名</span>' : ''}
      </div>`;

      (month.weeks || []).forEach(week => {
        totalWeeks++;
        const wConflict = existingTitles.includes((week.title||'').trim().toLowerCase());
        if (wConflict) conflicts.push({ level: '週', title: week.title });
        previewHTML += `<div style="display:flex;align-items:center;gap:6px;margin-bottom:2px;padding-left:32px;">
          <span style="font-size:9px;font-weight:700;background:#C2560A;color:#FFF7ED;border-radius:3px;padding:1px 5px;">週</span>
          <span style="color:#C2560A;">${escHtml(week.title||'')}</span>
          ${wConflict ? '<span style="font-size:10px;color:#C2560A;">⚠️ 同名</span>' : ''}
        </div>`;
      });
    });
  });

  _importData = years;

  // 隱藏輸入區，顯示預覽
  const inputArea = document.getElementById('goal-import-input-area');
  if (inputArea) inputArea.style.display = 'none';

  // 顯示預覽
  document.getElementById('goal-import-preview').style.display = 'block';
  document.getElementById('goal-import-preview-body').innerHTML = previewHTML;
  document.getElementById('goal-import-summary').textContent =
    `共 ${years.length} 個年度目標、${totalMonths} 個月度、${totalWeeks} 個週目標`;

  // 衝突警告
  if (conflicts.length > 0) {
    document.getElementById('goal-import-conflict').style.display = 'block';
    document.getElementById('goal-import-conflict-list').innerHTML =
      conflicts.map(c => `• ${c.level}：${escHtml(c.title)}`).join('<br>');
  } else {
    document.getElementById('goal-import-conflict').style.display = 'none';
  }

  document.getElementById('goal-import-confirm-btn').style.display = 'inline-block';

  } catch(err) {
    alert('showGoalImportPreview 錯誤：' + err.message + '\n' + err.stack);
  }
}

function showGoalImportError(msg) {
  document.getElementById('goal-import-error').style.display = 'block';
  document.getElementById('goal-import-error').textContent = '❌ ' + msg;
  openGoalImportModal();
}

// 執行匯入
async function confirmGoalImport() {
  if (!_importData) return;
  const years = _importData;

  document.getElementById('goal-import-confirm-btn').style.display = 'none';
  document.getElementById('goal-import-preview').style.display = 'none';
  document.getElementById('goal-import-progress').style.display = 'block';

  // 計算總步驟
  let totalSteps = 0;
  years.forEach(y => {
    totalSteps++;
    (y.months||[]).forEach(m => {
      totalSteps++;
      totalSteps += (m.weeks||[]).length;
    });
  });
  let doneSteps = 0;

  function updateProgress(msg) {
    doneSteps++;
    const pct = Math.round(doneSteps / totalSteps * 100);
    document.getElementById('goal-import-bar').style.width = pct + '%';
    document.getElementById('goal-import-status').textContent = msg;
  }

  async function saveGoal(data) {
    const res = await fetch('api/cards.php?action=save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.error || '儲存失敗');
    return json.id;
  }

  try {
    for (const year of years) {
      const yearId = await saveGoal({
        col: 'goal', level: 'year',
        title: year.title,
        summary: year.summary || null,
        isPrivate: 0
      });
      updateProgress(`年度：${year.title}`);

      for (const month of (year.months || [])) {
        const monthId = await saveGoal({
          col: 'goal', level: 'month',
          title: month.title,
          summary: month.summary || null,
          parentId: yearId, isPrivate: 0
        });
        updateProgress(`月度：${month.title}`);

        for (const week of (month.weeks || [])) {
          await saveGoal({
            col: 'goal', level: 'week',
            title: week.title,
            summary: week.summary || null,
            parentId: monthId, isPrivate: 0
          });
          updateProgress(`週：${week.title}`);
        }
      }
    }

    document.getElementById('goal-import-progress').style.display = 'none';
    document.getElementById('goal-import-done').style.display = 'block';
    document.getElementById('goal-import-done').textContent =
      `✅ 匯入完成！共建立 ${years.length} 年度、${years.reduce((a,y)=>a+(y.months||[]).length,0)} 月度、${years.reduce((a,y)=>(y.months||[]).reduce((b,m)=>b+(m.weeks||[]).length,a),0)} 週目標`;

    await loadCards();
    _importData = null;

  } catch (err) {
    document.getElementById('goal-import-progress').style.display = 'none';
    showGoalImportError('匯入過程發生錯誤：' + err.message);
  }
}

// 匯出目前目標樹為 JSON
function exportGoalTree() {
  const goalCards = state.goal || [];
  const years = goalCards.filter(c => c.level === 'year').map(year => ({
    title: year.title,
    summary: year.summary || '',
    months: goalCards.filter(m => m.parentId === year.id && m.level === 'month').map(month => ({
      title: month.title,
      summary: month.summary || '',
      weeks: goalCards.filter(w => w.parentId === month.id && w.level === 'week').map(week => ({
        title: week.title,
        summary: week.summary || ''
      }))
    }))
  }));

  // 加上獨立月度（沒有父層年度的）
  const looseMonths = goalCards.filter(m => m.level === 'month' && !goalCards.find(y => y.id === m.parentId));
  if (looseMonths.length > 0) {
    years.push({
      title: '（未分類月度目標）',
      summary: '',
      months: looseMonths.map(month => ({
        title: month.title,
        summary: month.summary || '',
        weeks: goalCards.filter(w => w.parentId === month.id && w.level === 'week').map(week => ({
          title: week.title,
          summary: week.summary || ''
        }))
      }))
    });
  }

  const json = JSON.stringify({ years }, null, 2);
  const blob = new Blob([json], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const a = document.getElementById('goal-export-link');
  a.href = url;
  a.download = `目標樹_${new Date().toISOString().slice(0,10)}.json`;
  a.click();
  URL.revokeObjectURL(url);
  toast('✅ 目標樹已匯出');
}

// 開啟新增/編輯目標 Modal
// ==========================================
// 戰略目標 Modal（正式版）
// ==========================================
let _goalModalState = {}; // 記錄目前 modal 的狀態

// 選層級後新增目標
function openGoalLevelPicker() {
  // 移除舊的
  const old = document.getElementById('goal-level-picker');
  if (old) { old.remove(); return; }

  const picker = document.createElement('div');
  picker.id = 'goal-level-picker';
  picker.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:999999;display:flex;align-items:center;justify-content:center;';
  picker.innerHTML = `
    <div style="background:#1e1e1c;border-radius:16px;padding:24px;width:88%;max-width:340px;box-shadow:0 24px 64px rgba(0,0,0,0.5);" onclick="event.stopPropagation()">
      <div style="font-size:15px;font-weight:700;color:#fff;margin-bottom:6px;">＋ 新增目標</div>
      <div style="font-size:12px;color:rgba(255,255,255,0.5);margin-bottom:18px;">選擇目標層級：</div>
      <div style="display:flex;flex-direction:column;gap:10px;">
        <button onclick="document.getElementById('goal-level-picker').remove();openGoalModal('year',null)"
          style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:rgba(255,215,0,0.1);border:1px solid rgba(255,215,0,0.4);border-radius:10px;cursor:pointer;font-family:inherit;text-align:left;width:100%;">
          <span style="font-size:20px;">📌</span>
          <div>
            <div style="font-size:14px;font-weight:700;color:#FFD700;">年度目標</div>
            <div style="font-size:11px;color:rgba(255,255,255,0.4);margin-top:2px;">長期願景，1年以上</div>
          </div>
        </button>
        <button onclick="document.getElementById('goal-level-picker').remove();openGoalModal('month',null)"
          style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:rgba(99,102,241,0.1);border:1px solid rgba(99,102,241,0.4);border-radius:10px;cursor:pointer;font-family:inherit;text-align:left;width:100%;">
          <span style="font-size:20px;">📅</span>
          <div>
            <div style="font-size:14px;font-weight:700;color:#c4b5fd;">月度目標</div>
            <div style="font-size:11px;color:rgba(255,255,255,0.4);margin-top:2px;">中期專案，1個月左右</div>
          </div>
        </button>
        <button onclick="document.getElementById('goal-level-picker').remove();openGoalModal('week',null)"
          style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:rgba(249,115,22,0.1);border:1px solid rgba(249,115,22,0.4);border-radius:10px;cursor:pointer;font-family:inherit;text-align:left;width:100%;">
          <span style="font-size:20px;">📋</span>
          <div>
            <div style="font-size:14px;font-weight:700;color:#fb923c;">週目標</div>
            <div style="font-size:11px;color:rgba(255,255,255,0.4);margin-top:2px;">短期衝刺，這週要完成</div>
          </div>
        </button>
      </div>
      <button onclick="document.getElementById('goal-level-picker').remove()"
        style="width:100%;margin-top:14px;padding:10px;border:1px solid rgba(255,255,255,0.15);border-radius:8px;background:none;cursor:pointer;font-size:13px;font-family:inherit;color:rgba(255,255,255,0.5);">取消</button>
    </div>
  `;
  picker.addEventListener('click', (e) => { if (e.target === picker) picker.remove(); });
  document.body.appendChild(picker);
}

function openGoalModal(level, parentId, editId = null) {
  const levelNames = { year: '年度目標', month: '月度目標', week: '週目標' };
  const levelIcons = { year: '📌', month: '📅', week: '📋' };

  _goalModalState = { level, parentId, editId };

  // 移除舊的（如果有）
  const old = document.getElementById('gm-dynamic');
  if (old) old.remove();

  // 取父層名稱
  let parentInfo = '';
  if (parentId) {
    const parentCard = state.goal.find(c => c.id === parentId);
    if (parentCard) parentInfo = `<div style="font-size:12px;color:rgba(255,255,255,0.6);margin-top:4px;">隸屬：${escHtml(parentCard.title)}</div>`;
  }

  // 取現有資料（編輯模式）
  let existingTitle = '', existingSummary = '';
  if (editId) {
    const card = state.goal.find(c => c.id === editId);
    if (card) { existingTitle = card.title || ''; existingSummary = card.summary || ''; }
  }

  // 年份欄位
  const showYear = (level === 'year' || level === 'month');
  const yearField = showYear ? `
    <div style="margin-bottom:12px;">
      <label style="display:block;font-size:12px;font-weight:600;color:#ccc;margin-bottom:5px;">年份（選填）</label>
      <input id="gm-year-input" type="number" value="${new Date().getFullYear()}" min="2020" max="2035"
        style="width:100%;padding:9px 12px;border:1.5px solid #444;border-radius:8px;font-size:14px;background:#2a2a2a;color:#eee;box-sizing:border-box;outline:none;">
    </div>` : '';

  // 建立全新 overlay
  const overlay = document.createElement('div');
  overlay.id = 'gm-dynamic';
  overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:999999;display:flex;align-items:center;justify-content:center;';
  overlay.innerHTML = `
    <div style="background:#1e1e1c;border-radius:16px;width:90%;max-width:440px;box-shadow:0 24px 64px rgba(0,0,0,0.5);overflow:hidden;" onclick="event.stopPropagation()">
      <div style="padding:16px 20px;background:linear-gradient(135deg,#534AB7,#7C3AED);">
        <div style="font-size:16px;font-weight:700;color:#fff;">${editId ? '✏️ 編輯' : '＋ 新增'} ${levelIcons[level]||''} ${levelNames[level]||'目標'}</div>
        ${parentInfo}
      </div>
      <div style="padding:20px 20px 8px;">
        <div style="margin-bottom:14px;">
          <label style="display:block;font-size:12px;font-weight:600;color:#ccc;margin-bottom:6px;">標題 *</label>
          <input id="gm-title-input" type="text" value="${escHtml(existingTitle)}" placeholder="輸入目標標題..."
            style="width:100%;padding:10px 12px;border:1.5px solid #444;border-radius:8px;font-size:14px;background:#2a2a2a;color:#eee;box-sizing:border-box;outline:none;"
            onkeydown="if(event.key==='Enter'){event.preventDefault();document.getElementById('gm-confirm-btn').click();}">
        </div>
        <div style="margin-bottom:14px;">
          <label style="display:block;font-size:12px;font-weight:600;color:#ccc;margin-bottom:6px;">摘要（選填）</label>
          <input id="gm-summary-input" type="text" value="${escHtml(existingSummary)}" placeholder="一句話說明這個目標..."
            style="width:100%;padding:10px 12px;border:1.5px solid #444;border-radius:8px;font-size:13px;background:#2a2a2a;color:#eee;box-sizing:border-box;outline:none;">
        </div>
        ${yearField}
      </div>
      <div style="padding:12px 20px 18px;display:flex;gap:8px;justify-content:flex-end;">
        <button onclick="closeGoalModal()" style="padding:9px 20px;border:1px solid #555;border-radius:8px;background:none;cursor:pointer;font-size:13px;font-family:inherit;color:#ccc;">取消</button>
        <button id="gm-confirm-btn" onclick="confirmGoalModal()" style="padding:9px 24px;border:none;border-radius:8px;background:#534AB7;color:#fff;cursor:pointer;font-size:13px;font-family:inherit;font-weight:600;">確認</button>
      </div>
    </div>
  `;
  overlay.addEventListener('click', (e) => { if (e.target === overlay) closeGoalModal(); });
  document.body.appendChild(overlay);

  // 聚焦
  setTimeout(() => {
    const inp = document.getElementById('gm-title-input');
    if (inp) { inp.focus(); inp.setSelectionRange(inp.value.length, inp.value.length); }
  }, 50);
}

function closeGoalModal() {
  const overlay = document.getElementById('gm-dynamic');
  if (overlay) overlay.remove();
  // 同時隱藏舊的 overlay（如果存在）
  const old = document.getElementById('goal-modal-overlay');
  if (old) old.style.display = 'none';
  _goalModalState = {};
}

async function confirmGoalModal() {
  const titleInput = document.getElementById('gm-title-input');
  const title = titleInput ? titleInput.value.trim() : '';
  if (!title) { if (titleInput) titleInput.focus(); return; }

  const { level, parentId, editId } = _goalModalState;
  const summaryInput = document.getElementById('gm-summary-input');
  const summary = summaryInput ? summaryInput.value.trim() : '';

  const data = {
    col: 'goal', title, level,
    parentId: parentId || null,
    summary: summary || null,
    isPrivate: 0
  };
  if (editId) data.id = editId;

  const btn = document.getElementById('gm-confirm-btn');
  if (btn) { btn.disabled = true; btn.textContent = '儲存中...'; }

  try {
    const res = await fetch('api/cards.php?action=save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const d = await res.json();
    if (d.success) {
      const levelNames = { year: '年度目標', month: '月度目標', week: '週目標' };
      toast(`✅ ${levelNames[level]||'目標'}已${editId ? '更新' : '建立'}`);
      closeGoalModal();
      await loadCards();
    } else {
      toast('❌ ' + (d.error || '儲存失敗'));
      if (btn) { btn.disabled = false; btn.textContent = '確認'; }
    }
  } catch(e) {
    toast('❌ 連線錯誤');
    if (btn) { btn.disabled = false; btn.textContent = '確認'; }
  }
}

// 從週卡發射子任務到策略筆記區（用正式 Modal）
function spawnProjectCard(weekId, weekTitle) {
  // 重用主卡片 Modal，設定好 col=lib, level=project, parentId=weekId
  document.getElementById('input-col').value = 'lib';
  document.getElementById('input-edit-id').value = '';
  document.getElementById('input-title').value = '';
  document.getElementById('input-project').value = '';
  document.getElementById('input-source').value = '';
  document.getElementById('input-summary').value = '';
  document.getElementById('input-nextstep').value = '';
  document.getElementById('input-body').value = '';
  const bodyEditor = document.getElementById('input-body-editor');
  if (bodyEditor) bodyEditor.innerHTML = '';
  document.getElementById('checklist-container').innerHTML = '';
  toggleModalChecklist(false);
  document.getElementById('input-priority').value = '';
  document.querySelectorAll('.priority-btn').forEach(b => b.classList.remove('active'));
  initSwatches('', '');

  // 設定 modal 標題
  document.getElementById('modal-title').textContent = `🚀 新增子任務 → ${weekTitle}`;

  // 儲存週ID供儲存時使用（存到 hidden input，不用全域變數，避免儲存後清掉）
  document.getElementById('input-parent-id').value = weekId;
  document.getElementById('input-level').value = 'project';

  const backBtn = document.getElementById('modal-back-btn'); if (backBtn) backBtn.style.display = 'none';
  const bb = document.getElementById('bottom-bar'); if (bb) bb.style.display = 'none';
  document.getElementById('overlay').classList.add('open');
  setTimeout(() => document.getElementById('input-title').focus(), 60);
}

// 點擊週卡「查看」，高亮右側看板的子任務
function filterByParent(parentId) {
  goalFilterParentId = (parentId && goalFilterParentId !== parentId) ? parentId : null;
  render();

  // 更新週卡高亮
  document.querySelectorAll('.goal-week-card').forEach(el => {
    el.classList.toggle('goal-active-filter', el.dataset.weekId == goalFilterParentId);
  });

  // 更新篩選提示列
  const bar = document.getElementById('goal-filter-bar');
  const label = document.getElementById('goal-filter-label');
  if (goalFilterParentId) {
    const weekCard = state.goal.find(c => c.id === goalFilterParentId);
    const weekTitle = weekCard ? weekCard.title : '週目標';
    if (label) label.textContent = `🔍 篩選：${weekTitle} 的子任務`;
    if (bar) bar.classList.add('show');
    toast('🔍 已篩選：只顯示此週目標的子任務');
  } else {
    if (bar) bar.classList.remove('show');
  }
}

// 編輯目標卡
function editGoalCard(id, e) {
  if (e) e.stopPropagation();
  const card = state.goal.find(c => c.id === id);
  if (!card) return;
  openGoalModal(card.level, card.parentId, id);
}

// 刪除目標卡
async function deleteGoalCard(id, e) {
  if (e) e.stopPropagation();
  if (!confirm('確定刪除這個目標嗎？子目標不會一起刪除。')) return;
  try {
    const res = await fetch(`api/cards.php?action=delete&id=${id}`);
    const d = await res.json();
    if (d.success) { toast('🗑 已刪除'); await loadCards(); }
    else toast('❌ ' + (d.error || '刪除失敗'));
  } catch(e) { toast('❌ 連線錯誤'); }
}

function render() {
  // ⭐ 雙重保險：確保專案標籤是最新的
  updateProjectLabels();
  injectProjectStyles();
  
  // 優先級排序權重
  const PRIORITY_ORDER = {
    'urgent_important': 1,
    'important_not_urgent': 2,
    'urgent_not_important': 3,
    'not_urgent_not_important': 4,
    null: 5, undefined: 5, '': 5
  };

  ['lib', 'week', 'focus', 'done'].forEach(col => {
    const area = document.getElementById('cards-' + col); area.innerHTML = '';
    let visibleCards = state[col].filter(shouldShowCard);

    if (col === 'lib') {
      // 排序：篩選中的子任務最前，然後有 parentId 的，再按重要緊急排
      const sortCards = arr => [...arr].sort((a, b) => {
        // 篩選中的週目標子任務排第一
        const aFiltered = (goalFilterParentId && a.parentId === goalFilterParentId) ? 0 : 1;
        const bFiltered = (goalFilterParentId && b.parentId === goalFilterParentId) ? 0 : 1;
        if (aFiltered !== bFiltered) return aFiltered - bFiltered;
        const aIsProject = a.parentId ? 0 : 1;
        const bIsProject = b.parentId ? 0 : 1;
        if (aIsProject !== bIsProject) return aIsProject - bIsProject;
        const pa = a.priority === 'urgent_important' ? 0 : 1;
        const pb = b.priority === 'urgent_important' ? 0 : 1;
        return pa - pb;
      });
      const myCards = sortCards(visibleCards.filter(c => c.createdByUsername === CURRENT_USERNAME || !c.createdByUsername));
      const otherCards = sortCards(visibleCards.filter(c => c.createdByUsername && c.createdByUsername !== CURRENT_USERNAME));

      if (visibleCards.length === 0) {
        const empty = document.createElement('div'); empty.className = 'empty';
        empty.textContent = searchQuery || currentFilter ? '沒有符合的卡片' : '把筆記、策略存在這裡';
        area.appendChild(empty);
      }

      // 顯示自己的卡片
      let myUrgentNo = 0;
      myCards.forEach(card => {
        if (card.priority === 'urgent_important') myUrgentNo++;
        area.appendChild(buildCard(card, col, card.priority === 'urgent_important' ? myUrgentNo : null));
      });

      // 分隔線（只有兩邊都有卡片時才顯示）
      if (myCards.length > 0 && otherCards.length > 0) {
        const divider = document.createElement('div');
        divider.style.cssText = 'display:flex;align-items:center;gap:8px;padding:8px 12px;margin:4px 0;';
        divider.innerHTML = `<div style="flex:1;height:1px;background:var(--border);"></div><span style="font-size:10px;color:var(--text-muted);white-space:nowrap;">👥 共用卡片</span><div style="flex:1;height:1px;background:var(--border);"></div>`;
        area.appendChild(divider);
      }

      // 顯示別人的卡片
      let otherUrgentNo = 0;
      otherCards.forEach(card => {
        if (card.priority === 'urgent_important') otherUrgentNo++;
        area.appendChild(buildCard(card, col, card.priority === 'urgent_important' ? otherUrgentNo : null));
      });

    } else {
      if (visibleCards.length === 0) {
        const empty = document.createElement('div'); empty.className = 'empty';
        if (searchQuery || currentFilter) empty.textContent = '沒有符合的卡片';
        else empty.textContent = { week: '設定這週最重要的事', focus: '選一件事，現在就去做', done: '完成的事情會出現在這' }[col];
        area.appendChild(empty);
      }
      visibleCards.forEach((card, idx) => area.appendChild(buildCard(card, col, idx + 1)));
    }
  });

  document.getElementById('badge-lib').textContent = state.lib.length + ' 則';
  const wc = state.week.length; document.getElementById('badge-week').textContent = wc + ' / 3'; document.getElementById('badge-week').className = 'badge badge-week' + (wc >= 3 ? ' full' : '');
  const fc = state.focus.length; document.getElementById('badge-focus').textContent = fc + ' / 1'; document.getElementById('badge-focus').className = 'badge badge-focus' + (fc >= 1 ? ' full' : '');
  document.getElementById('badge-done').textContent = state.done.length + ' 件';
  document.getElementById('add-week').style.display = wc >= 3 ? 'none' : 'block';
  document.getElementById('add-focus').style.display = fc >= 1 ? 'none' : 'block';
  document.getElementById('clear-done-btn').style.display = state.done.length > 0 ? 'block' : 'none';
  renderFilterTags();
}

function renderFilterTags() {
  const container = document.getElementById('filter-tags'); container.innerHTML = '';
  const mobileContainer = document.getElementById('mobile-filter-bar');
  if (mobileContainer) mobileContainer.innerHTML = '';

  const projects = new Set();
  ['lib','week','focus','done'].forEach(col => state[col].forEach(c => { if (c.project) projects.add(c.project); }));

  // 建立一個 tag 的工廠函數，桌面和手機共用
  function makeTag(label, isActive, color, bg, onClick) {
    const tag = document.createElement('div');
    tag.className = 'filter-tag' + (isActive ? ' active' : '');
    tag.textContent = label;
    if (isActive) {
      tag.style.background = color || 'var(--accent-lib)';
      tag.style.color = '#fff';
      tag.style.borderColor = color || 'var(--accent-lib)';
    } else if (color) {
      tag.style.background = bg;
      tag.style.color = color;
      tag.style.borderColor = color + '60';
    }
    tag.onclick = onClick;
    return tag;
  }

  const allOnClick = () => { currentFilter = null; render(); };
  container.appendChild(makeTag('全部', !currentFilter, null, null, allOnClick));
  if (mobileContainer) mobileContainer.appendChild(makeTag('全部', !currentFilter, null, null, allOnClick));

  projects.forEach(p => {
    const proj = ALL_PROJECTS[p];
    const label = (proj && proj.label) ? proj.label : (PROJECT_LABELS[p] || null);
    if (!label) return;
    const color = (proj && (proj.color || proj.textColor)) || '#666';
    const bg    = (proj && proj.bg) || color + '20';
    const onClick = () => { currentFilter = p; render(); };
    container.appendChild(makeTag(label, currentFilter === p, color, bg, onClick));
    if (mobileContainer) mobileContainer.appendChild(makeTag(label, currentFilter === p, color, bg, onClick));
  });
}

function buildCard(card, col, cardNo) {
  const div = document.createElement('div');
  // 有 parentId = 從目標發射的子任務（專案筆記）
  const isProjectCard = !!(card.parentId) || (card.checklist && Array.isArray(card.checklist) && card.checklist.length > 0);
  // goalFilterParentId 高亮篩選
  const isFiltered = goalFilterParentId !== null && card.parentId !== goalFilterParentId;
  div.className = 'card' + (isProjectCard ? ' is-project' : '') + (isFiltered ? ' goal-dimmed' : '');
  div.id = 'card-' + card.id; div.draggable = true; div.dataset.cardId = card.id; div.dataset.col = col;
  if (card.bgcolor) div.style.background = card.bgcolor; if (card.textcolor) div.style.color = card.textcolor;

  // 動態設定卡片標籤文字和顏色
  if (isProjectCard) {
    let tagLabel = '筆記';
    let tagBg = '#C8922A';
    let tagColor = '#FFF8EE';

    if (card.parentId) {
      // 追溯完整祖先鏈
      const ancestors = [];
      let currentId = card.parentId;
      let safety = 0;
      while (currentId && safety < 5) {
        const found = state.goal.find(c => c.id === currentId);
        if (!found) break;
        ancestors.unshift(found); // 最高層放前面
        currentId = found.parentId;
        safety++;
      }
      if (ancestors.length > 0) {
        const levelIcons = { year: '📌', month: '📅', week: '📋' };
        const levelBg    = { year: '#92750A', month: '#4338ca', week: '#c2410c' };
        // 顯示完整路徑
        const topCard = ancestors[0];
        const parts = ancestors.map(a => {
          const name = a.title.length > 8 ? a.title.slice(0,8)+'…' : a.title;
          return (levelIcons[a.level]||'') + ' ' + name;
        });
        tagLabel = parts.join(' › ');
        tagBg = levelBg[topCard.level] || '#C8922A';
        tagColor = '#fff';
      }
    }
    div.style.setProperty('--card-tag-label', `"${tagLabel.replace(/"/g, '\\"')}"`);
    div.style.setProperty('--card-tag-bg', tagBg);
    div.style.setProperty('--card-tag-color', tagColor);
  }

  const hasBodyOrNext = (card.body && card.body.trim()) || (card.nextStep && card.nextStep.trim());
  let metaHTML = '';

  // parent-badge：追溯完整祖先鏈，顯示完整「年 › 月 › 週」路徑
  let parentBadgeHTML = '';
  if (card.parentId) {
    const ancestors = [];
    let currentId = card.parentId;
    let safety = 0;
    while (currentId && safety < 5) {
      const found = state.goal.find(c => c.id === currentId);
      if (!found) break;
      ancestors.unshift(found);
      currentId = found.parentId;
      safety++;
    }
    if (ancestors.length > 0) {
      const levelBadge = {
        year:  { bg: '#92700A', fg: '#FFF8DC', label: '年' },
        month: { bg: '#3730A3', fg: '#EEF2FF', label: '月' },
        week:  { bg: '#C2560A', fg: '#FFF7ED', label: '週' },
      };
      const levelIcons = { year: '📌', month: '📅', week: '📋' };
      // 組合完整路徑 HTML
      const pathHTML = ancestors.map((a, i) => {
        const bd = levelBadge[a.level] || { bg:'#555', fg:'#fff', label:'?' };
        const icon = levelIcons[a.level] || '';
        const indent = i > 0 ? `margin-left:${i * 6}px;` : '';
        return `<div style="display:flex;align-items:center;gap:4px;${indent}${i>0?'margin-top:2px;':''}">
          <span style="font-size:9px;font-weight:700;background:${bd.bg};color:${bd.fg};border-radius:3px;padding:1px 4px;flex-shrink:0;">${bd.label}</span>
          <span style="font-size:10px;color:var(--text-secondary);">${icon} ${escHtml(a.title)}</span>
        </div>`;
      }).join('');
      const pathText = ancestors.map(a => `${levelIcons[a.level]||''} ${a.title}`).join(' › ');
      parentBadgeHTML = `<div class="parent-badge" style="display:block;padding:5px 8px;margin-bottom:4px;" title="${escHtml(pathText)}">${pathHTML}</div>`;
    } else {
      parentBadgeHTML = `<span class="parent-badge">🎯 目標任務</span>`;
    }
  }

  if (card.priority || card.isPrivate || card.project || card.parentId) {
    metaHTML = '<div class="card-meta">';
    if (parentBadgeHTML) metaHTML += parentBadgeHTML;
    if (card.project) {
      const projData = ALL_PROJECTS[card.project];
      const projLabel = (projData && projData.label) ? projData.label : (PROJECT_LABELS[card.project] || card.project);
      const projBg = (projData && projData.bg) ? projData.bg : '#eee';
      const projColor = (projData && projData.color) ? projData.color : '#666';
      metaHTML += `<span class="project-tag ${card.project}" style="background:${projBg};color:${projColor};">${projLabel}</span>`;
    }
    if (card.priority && !(col === 'lib' && cardNo)) {
      const pLabels = { urgent_important:'🔥 重要緊急', important_not_urgent:'⭐ 重要不緊急', urgent_not_important:'⚡ 緊急不重要', not_urgent_not_important:'💤 不重要不緊急' };
      const pColors = { urgent_important:'#FF4444', important_not_urgent:'#FF9800', urgent_not_important:'#2196F3', not_urgent_not_important:'#9E9E9E' };
      const pc = pColors[card.priority] || '#666';
      metaHTML += `<span class="priority-tag" style="background:${pc}20;color:${pc};border:1px solid ${pc}40;">${pLabels[card.priority] || card.priority}</span>`;
    }
    if (card.isPrivate) metaHTML += `<span class="privacy-tag private">🔒</span>`;
    metaHTML += '</div>';
  }

  let sourceHTML = card.sourceLink ? `<a href="${escHtml(card.sourceLink)}" target="_blank" class="source-link" onclick="event.stopPropagation()">🔗 來源連結</a>` : '';
  // 摘要和下一步在康乃爾區塊處理
  

  
  let timerHTML = '';
  if (col === 'focus') {
    // 檢查是否有子任務專注
    const sf = getSubtaskFocus();
    const isSubtaskFocus = sf && sf.cardId === card.id;
    const focusLabel = isSubtaskFocus 
      ? `<div style="font-size:11px;color:var(--accent-focus-text);margin-bottom:6px;font-weight:600;">🎯 專注子任務：${escHtml(sf.title)}</div>`
      : '';
    const doneLabel = isSubtaskFocus
      ? `<button class="timer-btn" style="background:var(--accent-done);margin-top:6px;" onclick="completeSubtask(${card.id});event.stopPropagation()">✓ 完成子任務</button>`
      : `<button class="timer-btn done" onclick="moveAPI(${card.id},'done');event.stopPropagation()">✓ 完成</button>`;
    timerHTML = `<div class="focus-timer">${focusLabel}<div class="timer-display" id="timer-display-${card.id}">00:00:00</div><div class="timer-controls"><button class="timer-btn" id="timer-btn-${card.id}" onclick="toggleTimer(${card.id});event.stopPropagation()">開始專注</button><button class="timer-btn secondary" onclick="resetTimer(${card.id});event.stopPropagation()">重置</button></div></div>${doneLabel}`;
  }

  const myFocusCnt = state.focus.filter(c => c.createdByUsername === CURRENT_USERNAME || !c.createdByUsername).length;
  const menuId = 'menu-' + card.id;
  let menuItems = '';
  if (col !== 'done') {
    if (col === 'lib' && state.week.length < 3) menuItems += `<button class="card-action-item primary" onclick="moveAPI(${card.id},'week');closeCardMenu('${menuId}');event.stopPropagation()">→ 本週目標</button>`;
    if (col !== 'focus' && myFocusCnt < 1) menuItems += `<button class="card-action-item primary" onclick="moveAPI(${card.id},'focus');closeCardMenu('${menuId}');event.stopPropagation()">→ 今日專注</button>`;
    if (col === 'week' || col === 'focus') menuItems += `<button class="card-action-item" onclick="moveAPI(${card.id},'lib');closeCardMenu('${menuId}');event.stopPropagation()">↩ 退回策略庫</button>`;
    menuItems += `<button class="card-action-item" onclick="editCard(${card.id},'${col}');closeCardMenu('${menuId}');event.stopPropagation()">✏️ 編輯卡片</button>`;
    const sfCheck = getSubtaskFocus();
    const isSubtask = sfCheck && sfCheck.cardId === card.id;
    if (col === 'focus' && isSubtask) {
      menuItems += `<button class="card-action-item done-btn" onclick="completeSubtask(${card.id});closeCardMenu('${menuId}');event.stopPropagation()">✓ 完成子任務（回策略庫）</button>`;
    } else {
      menuItems += `<button class="card-action-item done-btn" onclick="moveAPI(${card.id},'done');closeCardMenu('${menuId}');event.stopPropagation()">✓ 完成</button>`;
    }
    menuItems += `<button class="card-action-item danger" onclick="deleteAPI(${card.id});closeCardMenu('${menuId}');event.stopPropagation()">🗑 刪除</button>`;
  } else {
    menuItems += `<button class="card-action-item danger" onclick="deleteAPI(${card.id});closeCardMenu('${menuId}');event.stopPropagation()">🗑 移除</button>`;
  }
  const actsHTML = `<div class="card-actions-menu"><button class="card-actions-toggle" onclick="toggleCardMenu('${menuId}');event.stopPropagation()">⋯</button><div class="card-actions-dropdown" id="${menuId}">${menuItems}</div></div>`;

  const pLabelsAll = { urgent_important:'🔥 重要緊急', important_not_urgent:'⭐ 重要不緊急', urgent_not_important:'⚡ 緊急不重要', not_urgent_not_important:'💤 不重要不緊急' };
  const pColorsAll = { urgent_important:'#FF4444', important_not_urgent:'#FF9800', urgent_not_important:'#2196F3', not_urgent_not_important:'#9E9E9E' };
  let noTag = '';
  if (col === 'lib' && cardNo && card.priority) {
    // 合併 No.X 和優先級
    const pc = pColorsAll[card.priority] || '#FF4444';
    const pl = pLabelsAll[card.priority] || '';
    noTag = `<span style="font-size:10px;font-weight:700;color:${pc};background:${pc}18;border:1px solid ${pc}50;border-radius:6px;padding:2px 8px;margin-right:4px;flex-shrink:0;white-space:nowrap;">No.${cardNo} ${pl}</span>`;
  } else if (col === 'lib' && cardNo) {
    noTag = `<span style="font-size:10px;font-weight:700;color:#CC0000;background:#FFF0F0;border:1px solid #FF444440;border-radius:6px;padding:2px 6px;margin-right:4px;flex-shrink:0;">No.${cardNo}</span>`;
  }

  // 康乃爾格式
  const hasChecklist = card.checklist && Array.isArray(card.checklist) && card.checklist.length > 0;
  const hasBody = card.body && card.body.trim();
  const hasSummary = card.summary && card.summary.trim();
  const hasNextStep = card.nextStep && card.nextStep.trim();
  const hasExpandable = hasChecklist || hasBody || hasSummary || hasNextStep;
  const hasCornell = hasChecklist || hasBody;

  // C區：摘要
  // 摘要顯示在卡片頂部，C區不再重複
  const cornellC = hasSummary ? `<div class="cornell-c"><div class="cornell-c-label">💡 摘要</div>${escHtml(card.summary)}</div>` : '';

  // 下一步
  let nsHTMLcornell = hasNextStep ? `<div class="card-next-step"><strong>下一步：</strong>${escHtml(card.nextStep)}</div>` : '';

  // A區可編輯：待辦清單 + 新增
  const cardIdStr = card.id;
  let editableA = '';
  if (hasChecklist) {
    const completed = card.checklist.filter(i => i.checked).length;
    editableA += `<div class="cornell-label" id="cl-label-${cardIdStr}">✓ 待辦 ${completed}/${card.checklist.length}</div>`;
    card.checklist.forEach((item, idx) => {
      const cbId = `cb-${cardIdStr}-${idx}`;
      editableA += `<div class="checklist-item" id="cli-${cardIdStr}-${idx}" style="background:transparent;padding:4px 2px;border-radius:0;margin-bottom:2px;border-bottom:1px solid var(--border);">`;
      editableA += `<input type="checkbox" id="${cbId}" ${item.checked ? 'checked' : ''} style="cursor:pointer;width:16px;height:16px;accent-color:var(--accent-lib);flex-shrink:0;margin-top:0;" onclick="event.stopPropagation();" onchange="cbSave(${cardIdStr},'${col}')">`;
      editableA += `<textarea style="flex:1;border:none;background:transparent;font-size:12px;font-family:inherit;resize:none;overflow:hidden;line-height:1.5;padding:0 4px;outline:none;${item.checked ? 'text-decoration:line-through;color:var(--text-muted);' : ''}" rows="1" onclick="event.stopPropagation()" onkeydown="if(event.key==='Enter'){event.preventDefault();}" oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'" onblur="cbSave(${cardIdStr},'${col}')">${escHtml(item.text)}</textarea>`;
      editableA += `<button class="inline-item-btn focus-btn" onclick="promoteSubtaskToFocus(${cardIdStr},${idx},'${col}');event.stopPropagation()">→ 今日專注</button>`;
      editableA += `<button class="inline-item-btn del-btn" onclick="cbDel(${cardIdStr},${idx},'${col}');event.stopPropagation()">🗑</button>`;
      editableA += `</div>`;
    });
  } else {
    editableA = `<div class="cornell-label" style="opacity:0.4;">待辦清單</div>`;
  }
  editableA += `<div class="cornell-add-item"><input class="cornell-add-input" id="add-item-${cardIdStr}" placeholder="新增項目..." onclick="event.stopPropagation()" onkeydown="if(event.key==='Enter'){cbAdd(${cardIdStr},'${col}');event.preventDefault();}"><button class="cornell-add-btn" onclick="cbAdd(${cardIdStr},'${col}');event.stopPropagation()">+</button></div>`;

  // B區可編輯：筆記 textarea + 編輯整張卡片按鈕
  const noteHTML = card.body || '';
  const editableB = `
    <div style="display:flex;gap:0;align-items:flex-start;height:100%;">
      <!-- 筆記欄 -->
      <div style="flex:1;min-width:0;position:relative;padding:4px;">
        <div class="note-editable" id="note-${cardIdStr}" contenteditable="true" placeholder="點此輸入筆記..."
          onfocus="const tb=document.getElementById('mtb-${cardIdStr}');if(tb){tb.classList.add('active');tb.classList.remove('hidden');}event.stopPropagation()"
          onblur="const _el=this;setTimeout(()=>inlineSaveNoteHTML(${cardIdStr},'${col}',_el.innerHTML),200);event.stopPropagation()"
          onclick="event.stopPropagation()"
          ondragstart="event.preventDefault();event.stopPropagation()"
          style="cursor:text;"
        >${noteHTML}</div>
      </div>
      <!-- 工具列 -->
      <div class="mini-toolbar" id="mtb-${cardIdStr}"
        style="flex-shrink:0;width:44px;padding:6px 4px;gap:6px;flex-direction:column;align-items:center;border-left:1px solid rgba(255,255,255,0.1);touch-action:manipulation;">
        <button class="mini-tb-btn" style="width:36px;height:36px;font-size:13px;padding:0;touch-action:manipulation;" onclick="toggleSubMenu('fmt-menu-${cardIdStr}',this);event.stopPropagation()">¶</button>
        <div id="fmt-menu-${cardIdStr}" style="display:none;position:fixed;background:#111110;border-radius:12px;padding:10px;flex-direction:row;gap:8px;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,0.6);">
          <button class="mini-tb-btn" style="width:36px;height:36px;font-size:15px;padding:0;touch-action:manipulation;" onclick="applyFormatBefore('bold');hideSubMenu('fmt-menu-${cardIdStr}');event.stopPropagation()"><b>B</b></button>
          <button class="mini-tb-btn" style="width:36px;height:36px;font-size:13px;padding:0;touch-action:manipulation;" onclick="miniCmd('insertOrderedList');hideSubMenu('fmt-menu-${cardIdStr}');event.stopPropagation()">1.</button>
          <button class="mini-tb-btn" style="width:36px;height:36px;font-size:16px;padding:0;touch-action:manipulation;" onclick="miniCmd('insertUnorderedList');hideSubMenu('fmt-menu-${cardIdStr}');event.stopPropagation()">•</button>
        </div>
        <div style="width:22px;height:1px;background:rgba(255,255,255,0.3);"></div>
        <button id="color-btn-${cardIdStr}" class="mini-tb-btn" style="width:36px;height:36px;font-size:14px;padding:0;font-weight:900;border-bottom:3px solid #E24B4A;touch-action:manipulation;" onclick="toggleSubMenu('color-menu-${cardIdStr}',this);event.stopPropagation()">A</button>
        <div id="color-menu-${cardIdStr}" style="display:none;position:fixed;background:#111110;border-radius:12px;padding:12px;flex-direction:row;gap:10px;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,0.6);">
          <button style="width:32px;height:32px;border-radius:50%;background:#E24B4A;border:2px solid rgba(255,255,255,0.5);cursor:pointer;padding:0;touch-action:manipulation;" onclick="setNoteColor(this,'#E24B4A');setLastColor('${cardIdStr}','#E24B4A');hideSubMenu('color-menu-${cardIdStr}');event.stopPropagation()"></button>
          <button style="width:32px;height:32px;border-radius:50%;background:#185FA5;border:2px solid rgba(255,255,255,0.5);cursor:pointer;padding:0;touch-action:manipulation;" onclick="setNoteColor(this,'#185FA5');setLastColor('${cardIdStr}','#185FA5');hideSubMenu('color-menu-${cardIdStr}');event.stopPropagation()"></button>
          <button style="width:32px;height:32px;border-radius:50%;background:#1A1A18;border:2px solid rgba(255,255,255,0.5);cursor:pointer;padding:0;touch-action:manipulation;" onclick="setNoteColor(this,'#1A1A18');setLastColor('${cardIdStr}','#1A1A18');hideSubMenu('color-menu-${cardIdStr}');event.stopPropagation()"></button>
          <button style="width:32px;height:32px;border-radius:50%;background:#FFFFFF;border:2px solid rgba(255,255,255,0.5);cursor:pointer;padding:0;touch-action:manipulation;" onclick="setNoteColor(this,'#FFFFFF');setLastColor('${cardIdStr}','#FFFFFF');hideSubMenu('color-menu-${cardIdStr}');event.stopPropagation()"></button>
        </div>
        <button id="bg-btn-${cardIdStr}" style="width:36px;height:36px;background:transparent;border:none;cursor:pointer;padding:2px;touch-action:manipulation;" onclick="toggleSubMenu('bg-menu-${cardIdStr}',this);event.stopPropagation()">
          <svg id="bg-icon-${cardIdStr}" width="22" height="22" viewBox="0 0 18 18"><rect x="2" y="13" width="14" height="3" rx="1" fill="#FFFACC"/><polygon points="4,13 7,4 11,4 14,13" fill="rgba(255,255,255,0.4)"/><rect x="7" y="2" width="4" height="3" rx="0.5" fill="rgba(255,255,255,0.5)"/></svg>
        </button>
        <div id="bg-menu-${cardIdStr}" style="display:none;position:fixed;background:#111110;border-radius:12px;padding:12px;flex-direction:row;gap:10px;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,0.6);">
          <button style="width:32px;height:32px;border-radius:6px;background:#DAEEFF;border:2px solid rgba(255,255,255,0.5);cursor:pointer;padding:0;touch-action:manipulation;" onclick="setNoteBgColor(this,'#DAEEFF');setLastBgColor('${cardIdStr}','#DAEEFF');hideSubMenu('bg-menu-${cardIdStr}');event.stopPropagation()"></button>
          <button style="width:32px;height:32px;border-radius:6px;background:#FFFACC;border:2px solid rgba(255,255,255,0.5);cursor:pointer;padding:0;touch-action:manipulation;" onclick="setNoteBgColor(this,'#FFFACC');setLastBgColor('${cardIdStr}','#FFFACC');hideSubMenu('bg-menu-${cardIdStr}');event.stopPropagation()"></button>
          <button style="width:32px;height:32px;border-radius:6px;background:#FFE4EC;border:2px solid rgba(255,255,255,0.5);cursor:pointer;padding:0;touch-action:manipulation;" onclick="setNoteBgColor(this,'#FFE4EC');setLastBgColor('${cardIdStr}','#FFE4EC');hideSubMenu('bg-menu-${cardIdStr}');event.stopPropagation()"></button>
          <button style="width:32px;height:32px;border-radius:6px;background:#1A1A18;border:2px solid rgba(255,255,255,0.5);cursor:pointer;padding:0;touch-action:manipulation;" onclick="setNoteBgColor(this,'#1A1A18');setLastBgColor('${cardIdStr}','#1A1A18');hideSubMenu('bg-menu-${cardIdStr}');event.stopPropagation()"></button>
          <button style="width:32px;height:32px;border-radius:6px;background:#FFFFFF;border:2px solid rgba(255,255,255,0.5);cursor:pointer;padding:0;touch-action:manipulation;" onclick="setNoteBgColor(this,'#FFFFFF');setLastBgColor('${cardIdStr}','#FFFFFF');hideSubMenu('bg-menu-${cardIdStr}');event.stopPropagation()"></button>
          <button style="width:32px;height:32px;border-radius:6px;background:transparent;border:2px dashed rgba(255,255,255,0.4);cursor:pointer;padding:0;touch-action:manipulation;font-size:14px;color:rgba(255,255,255,0.6);line-height:1;" onclick="setNoteBgColor(this,'transparent');setLastBgColor('${cardIdStr}','transparent');hideSubMenu('bg-menu-${cardIdStr}');event.stopPropagation()">✕</button>
        </div>
        <div style="width:22px;height:1px;background:rgba(255,255,255,0.3);"></div>
        <button class="mini-tb-btn" style="width:36px;height:36px;font-size:16px;padding:0;touch-action:manipulation;" onclick="miniCmd('undo');event.stopPropagation()">↩</button>
        <button class="mini-tb-btn" style="width:36px;height:36px;font-size:16px;padding:0;touch-action:manipulation;" onclick="miniCmd('redo');event.stopPropagation()">↪</button>
        <div style="width:22px;height:1px;background:rgba(255,255,255,0.3);"></div>
        <button class="mini-tb-btn" style="width:36px;height:36px;font-size:11px;padding:0;touch-action:manipulation;line-height:1.2;" title="全選" onclick="noteSelectAll('note-${cardIdStr}');event.stopPropagation()">全選</button>
        <button class="mini-tb-btn" style="width:36px;height:36px;font-size:11px;padding:0;touch-action:manipulation;line-height:1.2;" title="選取一行" onclick="noteSelectLine('note-${cardIdStr}');event.stopPropagation()">選行</button>
        <div style="width:22px;height:1px;background:rgba(255,255,255,0.3);"></div>
        <button class="mini-tb-btn" style="width:36px;height:36px;font-size:14px;padding:0;touch-action:manipulation;" title="儲存筆記"
          onclick="(function(){const n=document.getElementById('note-${cardIdStr}');if(n){inlineSaveNoteHTML(${cardIdStr},'${col}',n.innerHTML);const b=document.getElementById('save-btn-${cardIdStr}');if(b){b.textContent='✓';setTimeout(()=>{b.textContent='💾';},800);}}})();event.stopPropagation()" id="save-btn-${cardIdStr}">💾</button>
        <div style="width:22px;height:1px;background:rgba(255,255,255,0.3);"></div>
        <button class="mini-tb-btn" style="width:36px;height:36px;font-size:15px;padding:0;color:#aaa;touch-action:manipulation;" title="關閉工具列"
          onclick="const tb=document.getElementById('mtb-${cardIdStr}');if(tb){tb.classList.remove('active');tb.classList.add('hidden');}event.stopPropagation()">✕</button>
      </div>
    </div>`;

  // 康乃爾展開區塊

  // 康乃爾展開區塊（可編輯版）
  // 左右箭頭邏輯
  const colOrder = ['lib','week','focus','done'];
  const colIdx = colOrder.indexOf(col);
  const prevCol = colIdx > 0 ? colOrder[colIdx-1] : null;
  const nextCol = colIdx < colOrder.length-1 ? colOrder[colIdx+1] : null;
  const colNames = {lib:'策略',week:'本週',focus:'今日',done:'完成'};
  // lib 欄專案卡（有 checklist）：只顯示「→ 本週」，不顯示「→ 今日」（子任務各自有今日專注按鈕）
  // lib 欄一般筆記卡：顯示「→ 本週」＋額外「→ 今日」（直接跳兩格）
  // week 欄：不管哪種卡，保留「→ 今日」（nextBtn 正常顯示）
  const prevBtn = (col !== 'lib' && prevCol) ? `<button style="font-size:12px;padding:4px 10px;border:1px solid var(--border);border-radius:6px;background:var(--surface);cursor:pointer;color:var(--text-secondary);" onclick="moveAPI(${cardIdStr},'${prevCol}');event.stopPropagation()">← ${colNames[prevCol]}</button>` : '';
  const nextBtn = nextCol ? `<button style="font-size:12px;padding:4px 10px;border:1px solid var(--border);border-radius:6px;background:var(--surface);cursor:pointer;color:var(--text-secondary);" onclick="moveAPI(${cardIdStr},'${nextCol}');event.stopPropagation()">→ ${colNames[nextCol]}</button>` : '';
  const focusDirectBtn = (col === 'lib' && !isProjectCard) ? `<button style="font-size:12px;padding:4px 10px;border:1px solid var(--border);border-radius:6px;background:var(--surface);cursor:pointer;color:var(--text-secondary);" onclick="moveAPI(${cardIdStr},'focus');event.stopPropagation()">→ 今日</button>` : '';
  const editBtn = `<div style="padding:6px 10px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:6px;flex-wrap:wrap;">
    <div style="display:flex;gap:6px;">${prevBtn}${nextBtn}${focusDirectBtn}</div>
    <button style="font-size:12px;padding:4px 12px;border:1px solid var(--accent-week);border-radius:6px;background:none;cursor:pointer;color:var(--accent-week);font-weight:500;" onclick="editCard(${cardIdStr},'${col}');event.stopPropagation()">✏️ 編輯卡片內容</button>
  </div>`;
  const cornellHTML = `<div class="cornell-layout" data-col="${col}" style="position:relative;"><div class="cornell-top"><div class="cornell-a">${editableA}</div><div class="cornell-b">${editableB}</div></div>${cornellC}${editBtn}</div>`;

  // 收合預覽（只顯示摘要或body首行）
  let previewHTML = '';
  if (hasSummary) previewHTML = `<div class="card-summary">${escHtml(card.summary)}</div>`;
  else if (hasBody) previewHTML = `<div class="card-preview">${card.body}</div>`;

  // 筆記指示圖示（收合時顯示）
  // 標題右側資訊（與 ⋯ 選單之間加 flex 間距）
  const noteIndicator = '';

  // 摘要永遠顯示在卡片上（康乃爾外面）
  const summaryHTML = hasSummary ? `<div class="card-summary">${escHtml(card.summary)}</div>` : '';

  const _cardOpenKey = 'card_open_' + card.id;
  const _cardInitOpen = localStorage.getItem(_cardOpenKey) === '1';
  if (_cardInitOpen) div.classList.add('open');

  div.innerHTML = `<div class="card-top" style="cursor:pointer;" onclick="(function(el){const card=el.closest('.card');card.classList.toggle('open');localStorage.setItem('card_open_${card.id}', card.classList.contains('open')?'1':'0');})(this);event.stopPropagation()"><span class="drag-handle">⋮⋮</span>${noTag}<div class="card-title">${col === 'done' ? '✓ ' : ''}${escHtml(card.title)}</div>${col === 'done' && card.completedAt ? `<div style="font-size:10px;color:var(--text-muted);white-space:nowrap;margin-left:auto;padding-right:4px;flex-shrink:0;">${formatDateTime(card.completedAt)}</div>` : ''}${noteIndicator}${actsHTML}</div><div class="card-collapse-body">${metaHTML}${sourceHTML}${summaryHTML}${nsHTMLcornell}${timerHTML}${cornellHTML}</div>`;

  // div.onclick 已移除，不攔截任何點擊事件
  // 筆記區選文字時停止拖移
  div.addEventListener('mousedown', (e) => {
    const noteEl = e.target.closest('.note-editable, .cornell-b, [contenteditable]');
    if (noteEl) { div.draggable = false; }
    else { div.draggable = true; }
  });
  div.addEventListener('mouseup', () => { div.draggable = true; });
  // 手機長按筆記區時，停止卡片拖曳讓系統選字
  div.addEventListener('touchstart', (e) => {
    const noteEl = e.target.closest('.note-editable, [contenteditable]');
    if (noteEl) { div.draggable = false; }
  }, { passive: true });
  div.addEventListener('touchend', () => { div.draggable = true; });
  div.addEventListener('dragstart', handleDragStart); div.addEventListener('dragend', handleDragEnd);
  return div;
}

// 插入分頁線
function insertPageBreak() {
  const ed = document.getElementById('input-body-editor');
  if (!ed) return;
  ed.focus();
  const sel = window.getSelection();
  if (!sel || !sel.rangeCount) return;
  const range = sel.getRangeAt(0);
  range.deleteContents();

  // 用 div 取代 hr，避免瀏覽器預設綠色 focus 框
  const divider = document.createElement('div');
  divider.className = 'page-break-divider';
  divider.contentEditable = 'false';
  divider.setAttribute('data-page-break', '1');
  range.insertNode(divider);

  // 在分頁線後插入新段落
  const p = document.createElement('p');
  p.innerHTML = '<br>';
  divider.after(p);

  // 把游標移到新段落
  const newRange = document.createRange();
  newRange.setStart(p, 0);
  newRange.collapse(true);
  sel.removeAllRanges();
  sel.addRange(newRange);
}

// Modal 編輯器工具列函數
function modalExecCmd(cmd, val) {
  const ed = document.getElementById('input-body-editor');
  if (!ed) return;
  ed.focus();
  document.execCommand(cmd, false, val || null);
}
function modalSetColor(color) {
  const ed = document.getElementById('input-body-editor');
  if (!ed) return;
  ed.focus();
  document.execCommand('foreColor', false, color);
  document.getElementById('modal-color-menu').style.display = 'none';
}
function modalSetBgColor(color) {
  const ed = document.getElementById('input-body-editor');
  if (!ed) return;
  ed.focus();
  document.execCommand('hiliteColor', false, color === 'transparent' ? 'transparent' : color);
  document.getElementById('modal-bg-menu').style.display = 'none';
}
function toggleModalColorMenu(menuId, btn) {
  const menu = document.getElementById(menuId);
  if (!menu) return;
  const isOpen = menu.style.display === 'flex';
  // 關閉所有 modal 選單
  ['modal-color-menu','modal-bg-menu'].forEach(id => {
    const m = document.getElementById(id);
    if (m) m.style.display = 'none';
  });
  if (!isOpen) {
    menu.style.display = 'flex';
    const rect = btn.getBoundingClientRect();
    requestAnimationFrame(() => {
      menu.style.left = rect.left + 'px';
      menu.style.top = (rect.bottom + 4) + 'px';
    });
  }
}
document.addEventListener('mousedown', (e) => {
  ['modal-color-menu','modal-bg-menu'].forEach(id => {
    const menu = document.getElementById(id);
    if (menu && !menu.contains(e.target) && !e.target.closest('[onmousedown*="toggleModalColorMenu"]')) {
      menu.style.display = 'none';
    }
  });
});

function escHtml(str) { return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function formatDateTime(ts) {
  const d = new Date(ts.replace ? ts.replace(' ', 'T') : ts); if(isNaN(d)) return ts;
  return `${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

// ==========================================
// 拖曳處理
// ==========================================
let draggedCard = null;
function handleDragStart(e) {
  // 如果選取了文字，不觸發拖移
  const sel = window.getSelection();
  if (sel && sel.toString().length > 0) { e.preventDefault(); return; }
  // 如果點擊來自 contenteditable、checkbox、input、button 區域，不觸發拖移
  if (e.target.closest('[contenteditable]') || e.target.closest('.note-editable') || 
      e.target.closest('.cornell-b') || e.target.closest('.checklist-text-edit') ||
      e.target.tagName === 'INPUT' || e.target.tagName === 'BUTTON' ||
      e.target.tagName === 'A' || e.target.closest('.card-checklist-item') ||
      e.target.closest('.cornell-a')) {
    e.preventDefault(); return;
  }
  draggedCard = { id: parseInt(this.dataset.cardId), fromCol: this.dataset.col };
  this.classList.add('dragging');
  e.dataTransfer.effectAllowed = 'move';
}
function handleDragEnd(e) { this.classList.remove('dragging'); document.querySelectorAll('.col').forEach(c => c.classList.remove('drag-over')); }
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.cards-area').forEach(a => { a.addEventListener('dragover', handleDragOver); a.addEventListener('drop', handleDrop); a.addEventListener('dragleave', handleDragLeave); });
});

// 事件委派處理卡片上的 checkbox
function handleInlineCb(e) {
  const t = e.target;
  if (t.classList.contains('inline-cb') && t.dataset.card) {
    const cardId = parseInt(t.dataset.card);
    const idx = parseInt(t.dataset.idx);
    const col = t.dataset.col;
    if (cardId && !isNaN(cardId)) {
      e.stopPropagation();
      inlineToggleChecklist(cardId, idx, col);
    }
  }
}
document.addEventListener('change', handleInlineCb);
document.addEventListener('click', (e) => {
  // 手機上 checkbox 點擊有時只觸發 click 不觸發 change
  if (e.target.classList.contains('inline-cb') && e.target.dataset.card) {
    // change 事件會緊接著觸發，稍微延遲避免重複執行
    clearTimeout(e.target._cbTimer);
    e.target._cbTimer = setTimeout(() => handleInlineCb(e), 50);
  }
});
function handleDragOver(e) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; this.closest('.col').classList.add('drag-over'); }
function handleDragLeave(e) { if (e.target === this) this.closest('.col').classList.remove('drag-over'); }
function handleDrop(e) {
  e.preventDefault(); if (!draggedCard) return;
  const toCol = this.closest('.col').dataset.col;
  const myFocus = state.focus.filter(c => c.createdByUsername === CURRENT_USERNAME || !c.createdByUsername).length;
  if (toCol === 'focus' && myFocus >= 1 && draggedCard.fromCol !== 'focus') { toast('你的今日專注已有 1 張！'); this.closest('.col').classList.remove('drag-over'); return; }
  if (toCol === 'week' && state.week.length >= 3 && draggedCard.fromCol !== 'week') { toast('本週目標已滿 3 張！'); this.closest('.col').classList.remove('drag-over'); return; }
  if (draggedCard.fromCol !== toCol) moveAPI(draggedCard.id, toCol);
  this.closest('.col').classList.remove('drag-over'); draggedCard = null;
}
function postponeCard() { toast('⏭ 已標記延到下週'); }
async function clearDone() {
  if (!confirm('確定永久清空已完成區塊嗎？')) return;
  toast('清空中...'); for (const c of state.done) await fetch(`api/cards.php?action=delete&id=${c.id}`);
  toast('已清除完成區'); await loadCards();
}

// ==========================================
// 表單與 UI
// ==========================================
function openModal(col) {
  const myFocusCount = state.focus.filter(c => c.createdByUsername === CURRENT_USERNAME || !c.createdByUsername).length;
  if (col === 'focus' && myFocusCount >= 1) { toast('你的今日專注已有 1 張！'); return; }
  if (col === 'week' && state.week.length >= 3) { toast('本週目標已滿 3 張！'); return; }
  document.getElementById('input-col').value = col; document.getElementById('input-edit-id').value = '';
  document.getElementById('input-title').value = ''; document.getElementById('input-project').value = ''; document.getElementById('input-source').value = ''; document.getElementById('input-summary').value = ''; document.getElementById('input-nextstep').value = ''; document.getElementById('input-body').value = ''; const bodyEditorClr = document.getElementById('input-body-editor'); if(bodyEditorClr) bodyEditorClr.innerHTML = '';
  
  // 清空待辦清單並收起
  document.getElementById('checklist-container').innerHTML = '';
  toggleModalChecklist(false);
  // 清空優先級
  document.getElementById('input-priority').value = '';
  document.querySelectorAll('.priority-btn').forEach(b => b.classList.remove('active'));
  // 清空父子關係（一般新增卡片，不是從戰略樹發射的）
  document.getElementById('input-parent-id').value = '';
  document.getElementById('input-level').value = 'general';
  
  initSwatches('', ''); document.getElementById('modal-title').textContent = { lib: '新增策略筆記', week: '新增本週目標', focus: '設定今日專注' }[col];
  const backBtn = document.getElementById('modal-back-btn'); if (backBtn) backBtn.style.display = 'none';
  const bb = document.getElementById('bottom-bar'); if (bb) bb.style.display = 'none';
  document.getElementById('overlay').classList.add('open'); setTimeout(() => document.getElementById('input-title').focus(), 60);
}

function editCard(id, col) {
  const card = state[col].find(c => c.id === id); if (!card) return;
  document.getElementById('input-col').value = col; document.getElementById('input-edit-id').value = id;
  document.getElementById('input-title').value = card.title; document.getElementById('input-project').value = card.project || ''; setTimeout(renderProjectSelect, 10); document.getElementById('input-source').value = card.sourceLink || ''; document.getElementById('input-summary').value = card.summary || ''; document.getElementById('input-nextstep').value = card.nextStep || ''; document.getElementById('input-body').value = card.body || ''; const bodyEditor = document.getElementById('input-body-editor'); if(bodyEditor) bodyEditor.innerHTML = card.body || '';
  
  if (card.isPrivate) {
    document.getElementById('privacy-private').checked = true;
  } else {
    document.getElementById('privacy-shared').checked = true;
  }
  
  renderChecklistEdit(card.checklist);
  toggleModalChecklist(card.checklist && card.checklist.length > 0);
  document.getElementById('input-priority').value = card.priority || '';
  document.querySelectorAll('.priority-btn').forEach(b => {
    b.classList.toggle('active', b.dataset.value === card.priority);
  });
  // 帶入卡片原本的父子關係（編輯時保留）
  document.getElementById('input-parent-id').value = card.parentId || '';
  document.getElementById('input-level').value = card.level || 'general';
  
  initSwatches(card.bgcolor || '', card.textcolor || ''); document.getElementById('modal-title').textContent = '編輯卡片';
  const backBtn2 = document.getElementById('modal-back-btn'); if (backBtn2) backBtn2.style.display = 'none';
  const bb2 = document.getElementById('bottom-bar'); if (bb2) bb2.style.display = 'none';
  document.getElementById('overlay').classList.add('open'); setTimeout(() => document.getElementById('input-title').focus(), 60);
}

async function saveCard() {
  const t = document.getElementById('input-title').value.trim(); if (!t) { document.getElementById('input-title').focus(); return; }
  const isPrivate = document.getElementById('privacy-private').checked ? 1 : 0;
  const checklist = getChecklistData();
  const priority = document.getElementById('input-priority').value || null;
  const eid = document.getElementById('input-edit-id').value;
  let completedAt = null;
  if (eid) {
    const found = getCard(parseInt(eid));
    if (found && found.card.completedAt) completedAt = found.card.completedAt;
  }
  const data = { col: document.getElementById('input-col').value, title: t, project: document.getElementById('input-project').value, priority: priority, sourceLink: document.getElementById('input-source').value.trim(), summary: document.getElementById('input-summary').value.trim(), nextStep: document.getElementById('input-nextstep').value.trim(), body: (document.getElementById('input-body-editor') ? document.getElementById('input-body-editor').innerHTML.trim() : document.getElementById('input-body').value.trim()), bgcolor: document.getElementById('input-bgcolor').value, textcolor: document.getElementById('input-textcolor').value, isPrivate: isPrivate, checklist: checklist, completedAt: completedAt };
  if (eid) data.id = eid;
  // 子任務發射：從 hidden input 讀取 parentId 和 level（不用全域變數，避免儲存後清掉）
  const spawnParentId = document.getElementById('input-parent-id').value;
  const spawnLevel = document.getElementById('input-level').value;
  if (spawnParentId) {
    data.parentId = parseInt(spawnParentId);
    data.level = spawnLevel || 'project';
  }
  // 相容舊的全域變數（保留向後相容）
  if (!spawnParentId && window._spawnParentId) {
    data.parentId = window._spawnParentId;
    data.level = window._spawnLevel || 'project';
    window._spawnParentId = null;
    window._spawnLevel = null;
  }
  const btn = document.querySelector('.modal-btn.primary'); btn.disabled = true; btn.textContent = '儲存中...';
  await saveCardToAPI(data);
  btn.disabled = false; btn.textContent = '儲存';
}

// 儲存後不關閉（同 saveCard，但不會觸發 closeModal）
const saveCardNoClose = saveCard;

function closeModal() {
  document.getElementById('overlay').classList.remove('open');
  // 恢復底部管理/登出列
  const bb = document.getElementById('bottom-bar');
  if (bb) bb.style.display = 'flex';
  // 重置返回按鈕
  const backBtn = document.getElementById('modal-back-btn');
  if (backBtn) backBtn.style.display = 'none';
}
function handleOverlayClick(e) { if (e.target === document.getElementById('overlay')) closeModal(); }
document.addEventListener('keydown', e => { 
  if (e.key === 'Escape' && !document.getElementById('fullscreen-editor').classList.contains('open') && !document.getElementById('project-settings-overlay').classList.contains('open')) closeModal(); 
});
document.getElementById('input-title').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); document.getElementById('input-summary').focus(); } });

function openSearchDropdown() {
  const inp = document.getElementById('search-input');
  inp.removeAttribute('readonly');
  inp.focus();
  renderSearchDropdown('');
  document.getElementById('search-close-btn').style.display = 'block';
}
function closeSearchDropdown() {
  document.getElementById('search-dropdown').classList.remove('show');
  document.getElementById('search-close-btn').style.display = 'none';
  const inp = document.getElementById('search-input');
  inp.value = '';
  inp.setAttribute('readonly', true);
  inp.blur();
  searchQuery = '';
  render();
}
document.getElementById('search-input').addEventListener('click', openSearchDropdown);
document.getElementById('search-input').addEventListener('input', e => {
  searchQuery = e.target.value.trim();
  render();
  renderSearchDropdown(searchQuery);
});
document.addEventListener('click', e => {
  if (!e.target.closest('.search-box')) {
    closeSearchDropdown();
  }
});

function renderSearchDropdown(q) {
  const dropdown = document.getElementById('search-dropdown');
  if (!dropdown) return;
  const colNames = {lib:'策略', week:'本週', focus:'今日', done:'完成'};
  let results = [];
  ['lib','week','focus','done'].forEach(col => {
    state[col].forEach(card => {
      const t = (card.title||'').toLowerCase();
      if (!q || t.includes(q.toLowerCase())) {
        results.push({card, col});
      }
    });
  });
  if (results.length === 0) { dropdown.classList.remove('show'); return; }
  dropdown.innerHTML = '';
  results.forEach(({card, col}) => {
    const item = document.createElement('div');
    item.className = 'search-result-item';
    const projInfo = card.project && ALL_PROJECTS[card.project]
      ? `<span style="font-size:10px;padding:1px 6px;border-radius:10px;background:${ALL_PROJECTS[card.project].bg||'#eee'};color:${ALL_PROJECTS[card.project].color||'#666'};font-weight:600;flex-shrink:0;">${ALL_PROJECTS[card.project].label}</span>`
      : '';
    const isProj = card.checklist && card.checklist.length > 0
      ? `<span style="font-size:10px;color:#C8922A;font-weight:700;flex-shrink:0;">📁</span>`
      : '';
    item.innerHTML = `<span class="search-result-col">${colNames[col]}</span>${isProj}<span class="search-result-title">${escHtml(card.title)}</span>${projInfo}`;
    item.onclick = () => {
      closeSearchDropdown();
      // 手機版自動切換到對應 tab
      if (window.innerWidth <= 768) {
        switchMobileTab(col);
      }
      setTimeout(() => {
        const el = document.getElementById('card-' + card.id);
        if (el) { el.scrollIntoView({behavior:'smooth', block:'center'}); el.style.outline='2px solid var(--accent-week)'; setTimeout(()=>el.style.outline='',1500); }
      }, 150);
    };
    dropdown.appendChild(item);
  });
  dropdown.classList.add('show');
}

function setPrivacyFilter(filter) {
  privacyFilter = filter;
  document.querySelectorAll('.privacy-filter-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.filter === filter);
  });
  render();
}

function toggleHelp() { document.getElementById('sidebar').classList.toggle('open'); document.getElementById('help-btn').classList.toggle('active'); }
function toggleExportMenu() { document.getElementById('export-dropdown').classList.toggle('open'); }
document.addEventListener('click', e => { if (!e.target.closest('.export-btn') && !e.target.closest('.export-dropdown')) document.getElementById('export-dropdown').classList.remove('open'); });

// 色票
const BG_COLORS = [{val:'',label:'預設'},{val:'#FFFFFF',label:'白'},{val:'#FFF9C4',label:'黃'},{val:'#FAECE7',label:'橘'},{val:'#EEEDFE',label:'紫'},{val:'#E1F5EE',label:'綠'},{val:'#E6F1FB',label:'藍'},{val:'#FBEAF0',label:'粉'},{val:'#EFEDE8',label:'米'},{val:'#2C2C2A',label:'黑'}];
const TEXT_COLORS = [{val:'',label:'預設'},{val:'#1A1A18',label:'黑'},{val:'#FFFFFF',label:'白'},{val:'#534AB7',label:'紫'},{val:'#D85A30',label:'橘'},{val:'#1D9E75',label:'綠'},{val:'#185FA5',label:'藍'},{val:'#993556',label:'粉'},{val:'#BA7517',label:'金'},{val:'#E24B4A',label:'紅'}];
function initSwatches(curBg, curText) {
  const bgEl = document.getElementById('bg-swatches'), textEl = document.getElementById('text-swatches'); bgEl.innerHTML = ''; textEl.innerHTML = '';
  BG_COLORS.forEach(({val,label}) => { const s = document.createElement('div'); s.className = 'swatch'+(val===curBg?' selected':''); s.title=label; s.style.background=val||'#F5F3EE'; if(!val) s.style.border='2px dashed #ccc'; s.onclick=()=>{ bgEl.querySelectorAll('.swatch').forEach(x=>x.classList.remove('selected')); s.classList.add('selected'); document.getElementById('input-bgcolor').value=val; }; bgEl.appendChild(s); });
  TEXT_COLORS.forEach(({val,label}) => { const s = document.createElement('div'); s.className = 'swatch'+(val===curText?' selected':''); s.title=label; s.style.background=val||'#1A1A18'; if(!val){ s.style.background='linear-gradient(135deg, #fff 50%, #333 50%)'; s.style.border='2px dashed #ccc'; } s.onclick=()=>{ textEl.querySelectorAll('.swatch').forEach(x=>x.classList.remove('selected')); s.classList.add('selected'); document.getElementById('input-textcolor').value=val; }; textEl.appendChild(s); });
}

// ==========================================
// 專注計時與匯出
// ==========================================
function toggleTimer(id) { timerRunning ? pauseTimer(id) : startTimer(id); }
function startTimer(id) { timerRunning = true; document.getElementById(`timer-btn-${id}`).textContent = '暫停'; focusTimer = setInterval(() => { timerSeconds++; updateTimerDisplay(id); }, 1000); }
function pauseTimer(id) { timerRunning = false; document.getElementById(`timer-btn-${id}`).textContent = '繼續'; clearInterval(focusTimer); }
function resetTimer(id) { timerRunning = false; timerSeconds = 0; clearInterval(focusTimer); updateTimerDisplay(id); document.getElementById(`timer-btn-${id}`).textContent = '開始專注'; }
function updateTimerDisplay(id) { const el = document.getElementById(`timer-display-${id}`); if(el) el.textContent = String(Math.floor(timerSeconds/3600)).padStart(2,'0')+':'+String(Math.floor((timerSeconds%3600)/60)).padStart(2,'0')+':'+String(timerSeconds%60).padStart(2,'0'); }

function printModalContent() {
  const title = document.getElementById('input-title')?.value || '卡片內容';
  const summary = document.getElementById('input-summary')?.value || '';
  const nextStep = document.getElementById('input-nextstep')?.value || '';
  const body = document.getElementById('input-body-editor')?.innerHTML || '';

  // 待辦清單
  const items = document.querySelectorAll('#checklist-container .checklist-item');
  const hasChecklist = items.length > 0;
  const hasBody = body && body.replace(/<[^>]+>/g,'').trim();

  // 建立選項對話框
  const dlg = document.createElement('div');
  dlg.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;';
  dlg.innerHTML = `
    <div style="background:#fff;border-radius:12px;padding:24px;min-width:300px;max-width:420px;box-shadow:0 8px 32px rgba(0,0,0,0.2);">
      <div style="font-size:16px;font-weight:700;margin-bottom:16px;">🖨️ 選擇列印內容</div>
      <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;">
          <input type="checkbox" id="print-title" checked style="width:16px;height:16px;"> 標題
        </label>
        ${summary ? `<label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;">
          <input type="checkbox" id="print-summary" checked style="width:16px;height:16px;"> 摘要
        </label>` : ''}
        ${nextStep ? `<label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;">
          <input type="checkbox" id="print-nextstep" checked style="width:16px;height:16px;"> 下一步
        </label>` : ''}
        ${hasChecklist ? `<label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;">
          <input type="checkbox" id="print-checklist" checked style="width:16px;height:16px;"> 待辦清單
        </label>` : ''}
        ${hasBody ? `
        <div style="border-top:1px solid #eee;padding-top:10px;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:14px;">
            <input type="checkbox" id="print-body" checked style="width:16px;height:16px;"> 筆記內容
          </label>
          <div style="margin-left:24px;margin-top:8px;display:flex;flex-direction:column;gap:7px;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:#555;">
              <input type="checkbox" id="print-body-header" checked style="width:14px;height:14px;"> 在筆記前加標題列（📝 ${title} — 筆記）
            </label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:#555;">
              <input type="checkbox" id="print-body-newpage" checked style="width:14px;height:14px;"> 筆記從新的一頁開始印（不接在待辦後面）
            </label>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:#555;">
              <input type="checkbox" id="print-h-break" style="width:14px;height:14px;"> 遇到大標題（H1/H2）自動換頁
            </label>
          </div>
        </div>` : ''}
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;">
        <button id="print-cancel" style="padding:8px 16px;border:1px solid #ddd;border-radius:8px;background:#f5f5f5;cursor:pointer;font-size:14px;">取消</button>
        <button id="print-confirm" style="padding:8px 20px;border:none;border-radius:8px;background:#534AB7;color:#fff;cursor:pointer;font-size:14px;font-weight:600;">列印</button>
      </div>
    </div>
  `;
  document.body.appendChild(dlg);

  document.getElementById('print-cancel').onclick = () => document.body.removeChild(dlg);

  document.getElementById('print-confirm').onclick = () => {
    const printTitle      = document.getElementById('print-title')?.checked;
    const printSummary    = document.getElementById('print-summary')?.checked;
    const printNext       = document.getElementById('print-nextstep')?.checked;
    const printCl         = document.getElementById('print-checklist')?.checked;
    const printBody       = document.getElementById('print-body')?.checked;
    const printNewPage    = document.getElementById('print-body-newpage')?.checked;
    const printBodyHeader = document.getElementById('print-body-header')?.checked;
    const printHBreak     = document.getElementById('print-h-break')?.checked;

    let checklistHTML = '';
    if (printCl && hasChecklist) {
      checklistHTML = '<div style="margin-bottom:16px;border:1px solid #eee;border-radius:8px;padding:12px;"><strong>待辦清單：</strong><ul style="margin:8px 0 0 20px;">';
      items.forEach(item => {
        const cb = item.querySelector('input[type="checkbox"]');
        const ta = item.querySelector('textarea');
        const text = ta ? ta.value.trim() : '';
        if (text) {
          const done = cb && cb.checked;
          checklistHTML += `<li style="${done ? 'text-decoration:line-through;color:#999;' : ''}">${text}</li>`;
        }
      });
      checklistHTML += '</ul></div>';
    }

    // H1/H2 自動換頁：在 body HTML 裡的 h1/h2 前插入 page-break（第一個不換）
    let processedBody = body;
    if (printHBreak && hasBody) {
      let isFirst = true;
      processedBody = body.replace(/<(h1|h2)(\s[^>]*)?>/gi, (match, tag, attrs) => {
        if (isFirst) { isFirst = false; return match; }
        return `<div class="page-break"></div><${tag}${attrs || ''}>`;
      });
    }

    const printWindow = window.open('', '_blank');
    printWindow.document.write(`<!DOCTYPE html><html><head>
      <meta charset="UTF-8">
      <title>${title}</title>
      <style>
        body { font-family: 'Noto Sans TC', 'Microsoft JhengHei', sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; color: #1A2C3A; line-height: 1.8; }
        h1 { font-size: 20px; margin: 0 0 8px; border-bottom: 2px solid #E8763E; padding-bottom: 8px; }
        h2 { font-size: 15px; font-weight: 700; color: #534AB7; border-bottom: 1px solid #e0e0e0; padding-bottom: 5px; margin: 12px 0 8px; }
        h3 { font-size: 13px; font-weight: 700; margin: 10px 0 6px; }
        .section { margin-bottom: 14px; }
        .label { font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }
        .summary { background: #FFFEF0; border-left: 4px solid #E8763E; padding: 8px 12px; border-radius: 4px; font-style: italic; }
        .nextstep { background: #F0FFF4; border-left: 4px solid #22c55e; padding: 8px 12px; border-radius: 4px; font-weight: 600; }
        .note-header { font-size: 14px; font-weight: 700; color: #534AB7; margin-bottom: 12px; padding-bottom: 6px; border-bottom: 1px solid #e0e0e0; }
        .body-content { padding: 4px 0; }
        .page-break { page-break-before: always; break-before: always; padding-top: 16px; }
        div.page-break-divider { page-break-after: always; break-after: always; border: none; margin: 0; height: 0; visibility: hidden; }
        ul, ol { margin-left: 20px; margin-bottom: 8px; }
        li { margin-bottom: 4px; }
        @media print { body { margin: 10px; } }
      </style>
    </head><body>
      ${printTitle ? `<h1>${title}</h1>` : ''}
      ${printSummary && summary ? `<div class="section"><div class="summary">💡 ${summary}</div></div>` : ''}
      ${printNext && nextStep ? `<div class="section"><div class="label">下一步</div><div class="nextstep">→ ${nextStep}</div></div>` : ''}
      ${checklistHTML}
      ${printBody && hasBody ? `
        <div class="${printNewPage ? 'page-break' : 'section'}">
          ${printBodyHeader ? `<div class="note-header">📝 ${title} — 筆記</div>` : ''}
          <div class="body-content">${processedBody}</div>
        </div>` : ''}
      <script>window.onload = function(){ window.print(); }<\/script>
    </body></html>`);
    printWindow.document.close();
    document.body.removeChild(dlg);
  };
}

function exportToExcel() {
  let csv = '\uFEFF區域,專案,標題,摘要,下一步,詳細說明,來源連結,完成時間\n';
  ['lib','week','focus','done'].forEach(col => state[col].forEach(c => { 
    const plainBody = stripHtml(c.body || '');
    csv += `"${{lib:'策略',week:'目標',focus:'今日',done:'完成'}[col]}","${c.project?PROJECT_LABELS[c.project]:''}","${(c.title||'').replace(/"/g,'""')}","${(c.summary||'').replace(/"/g,'""')}","${(c.nextStep||'').replace(/"/g,'""').replace(/\n/g,' ')}","${plainBody.replace(/"/g,'""').replace(/\n/g,' ')}","${(c.sourceLink||'').replace(/"/g,'""')}","${c.completedAt?formatDateTime(c.completedAt):''}"\n`; 
  }));
  downloadFile(csv, 'kanban.csv', 'text/csv;charset=utf-8;'); toast('✅ Excel 匯出成功'); toggleExportMenu();
}
function exportToPDF() {
  const w = window.open('','_blank'); let h = `<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:sans-serif;padding:20px}.sec{margin-bottom:30px}.st{font-size:18px;font-weight:bold;color:#534AB7;border-bottom:2px solid #534AB7;margin-bottom:10px}.c{background:#f9f9f9;border:1px solid #ddd;border-radius:8px;padding:12px;margin-bottom:10px}</style></head><body><h2>專注看板匯出</h2>`;
  ['lib','week','focus','done'].forEach(col => { h += `<div class="sec"><div class="st">${col} (${state[col].length})</div>`; state[col].forEach(c => { h+=`<div class="c"><b>${escHtml(c.title)}</b><br><small>${c.summary||''}</small></div>`; }); h+='</div>'; }); h+='</body></html>';
  w.document.write(h); w.document.close(); setTimeout(()=>w.print(),500); toast('✅ 準備列印'); toggleExportMenu();
}
function backupData() { downloadFile(JSON.stringify({v:'2.0',data:state},null,2), `kanban-backup.json`, 'application/json;charset=utf-8;'); toast('✅ 備份下載'); toggleExportMenu(); }
function restoreData(e) { alert("已升級為 MySQL 雲端版，請聯絡管理員手動匯入。"); e.target.value = ''; toggleExportMenu(); }
function downloadFile(content, filename, mime) { const b = new Blob([content], {type:mime}), a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = filename; a.click(); }

// ==========================================
// 待办清单相关函数
// ==========================================

// 添加待办项到编辑界面
// Modal 內待辦清單收折
function toggleModalChecklist(forceOpen) {
  const body = document.getElementById('modal-checklist-body');
  const chevron = document.getElementById('modal-checklist-chevron');
  const col = document.getElementById('modal-checklist-col');
  if (!body) return;
  const isOpen = body.style.display !== 'none';
  const shouldOpen = forceOpen !== undefined ? forceOpen : !isOpen;
  body.style.display = shouldOpen ? 'flex' : 'none';
  if (chevron) chevron.style.transform = shouldOpen ? '' : 'rotate(180deg)';
  if (col) {
    col.style.width = shouldOpen ? 'fit-content' : '36px';
    col.style.minWidth = shouldOpen ? '140px' : '36px';
    col.style.maxWidth = shouldOpen ? '260px' : '36px';
  }
}

function addChecklistItem(text = '', checked = false) {
  const container = document.getElementById('checklist-container');
  const id = 'checklist-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
  
  const item = document.createElement('div');
  item.className = 'checklist-item';
  item.dataset.id = id;
  item.innerHTML = `
    <input type="checkbox" ${checked ? 'checked' : ''}>
    <textarea placeholder="輸入待辦事項..." rows="1" onkeydown="if(event.key==='Enter'){event.preventDefault();addChecklistItem();}" oninput="this.style.height='auto';this.style.height=this.scrollHeight+'px'">${escHtml(text)}</textarea>
    <button class="checklist-item-delete" onclick="deleteChecklistItem('${id}'); event.stopPropagation();" style="padding:2px 6px;font-size:15px;background:none;border:none;color:#FCA5A5;cursor:pointer;">🗑</button>
  `;
  
  container.appendChild(item);

  // 初始化高度
  const ta = item.querySelector('textarea');
  if (ta) { ta.style.height = 'auto'; ta.style.height = ta.scrollHeight + 'px'; }

  // 初始刪除線（已勾選時）
  if (ta && checked) {
    ta.style.textDecoration = 'line-through';
    ta.style.color = 'var(--text-muted)';
  }

  // checkbox 勾選/取消時動態更新刪除線
  const cb = item.querySelector('input[type="checkbox"]');
  if (cb && ta) {
    cb.addEventListener('change', () => {
      ta.style.textDecoration = cb.checked ? 'line-through' : 'none';
      ta.style.color = cb.checked ? 'var(--text-muted)' : '';
    });
  }

  if (!text && ta) ta.focus();
}

// 删除待办项
function deleteChecklistItem(id) {
  const item = document.querySelector(`.checklist-item[data-id="${id}"]`);
  if (item) {
    item.remove();
  }
}

// 获取当前编辑界面的待办清单数据
function getChecklistData() {
  const items = [];
  document.querySelectorAll('#checklist-container .checklist-item').forEach(item => {
    const checkbox = item.querySelector('input[type="checkbox"]');
    const textInput = item.querySelector('textarea') || item.querySelector('input[type="text"]');
    const text = textInput ? textInput.value.trim() : '';
    
    if (text) { // 只保存有内容的项目
      items.push({
        text: text,
        checked: checkbox.checked
      });
    }
  });
  return items.length > 0 ? items : null;
}

// 在编辑界面渲染待办清单
function renderChecklistEdit(checklist) {
  const container = document.getElementById('checklist-container');
  container.innerHTML = '';
  
  if (checklist && Array.isArray(checklist)) {
    checklist.forEach(item => {
      addChecklistItem(item.text, item.checked);
    });
  }
}

// 在卡片上切换待办项的勾选状态
async function toggleChecklistItem(cardId, itemIndex, col) {
  const card = state[col].find(c => c.id === cardId);
  if (!card || !card.checklist || !card.checklist[itemIndex]) return;
  
  // 切换状态
  card.checklist[itemIndex].checked = !card.checklist[itemIndex].checked;
  
  // 保存到服务器
  await saveCardToAPI({
    id: cardId,
    col: col,
    title: card.title,
    project: card.project,
    sourceLink: card.sourceLink,
    summary: card.summary,
    nextStep: card.nextStep,
    body: card.body,
    bgcolor: card.bgcolor,
    textcolor: card.textcolor,
    isPrivate: card.isPrivate ? 1 : 0,
    checklist: card.checklist
  });
  
  // 重新渲染
  render();
}

// ==========================================
// 通知功能
// ==========================================

let notificationPanel = null;
let notificationCheckInterval = null;
let lastNotificationCount = 0; // 记录上次的通知数量
let notificationSoundEnabled = true; // 是否启用提示音

// 播放通知提示音
function playNotificationSound() {
  if (!notificationSoundEnabled) return;
  
  try {
    // 使用 Web Audio API 生成简单的"叮"声
    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    
    // 设置音调（800Hz，清脆的提示音）
    oscillator.frequency.value = 800;
    oscillator.type = 'sine';
    
    // 设置音量渐变（避免突兀）
    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
    
    // 播放 0.3 秒
    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + 0.3);
  } catch (err) {
    console.error('播放提示音失败:', err);
  }
}

// 切换提示音开关
function toggleNotificationSound() {
  notificationSoundEnabled = !notificationSoundEnabled;
  localStorage.setItem('notificationSoundEnabled', notificationSoundEnabled);
  const btn = document.getElementById('notification-sound-toggle');
  if (btn) {
    btn.textContent = notificationSoundEnabled ? '🔔' : '🔕';
    btn.title = notificationSoundEnabled ? '點擊關閉提示音' : '點擊開啟提示音';
  }
  toast(notificationSoundEnabled ? '✓ 已開啟提示音' : '✓ 已關閉提示音');
}

// 初始化通知系统
function initNotifications() {
  notificationPanel = document.getElementById('notification-panel');
  
  // 读取提示音设置
  const savedSound = localStorage.getItem('notificationSoundEnabled');
  if (savedSound !== null) {
    notificationSoundEnabled = savedSound === 'true';
  }
  
  // 更新按钮状态
  const soundBtn = document.getElementById('notification-sound-toggle');
  if (soundBtn) {
    soundBtn.textContent = notificationSoundEnabled ? '🔔' : '🔕';
    soundBtn.title = notificationSoundEnabled ? '點擊關閉提示音' : '點擊開啟提示音';
  }
  
  // 启动定期检查（每30秒）
  checkNotifications();
  notificationCheckInterval = setInterval(checkNotifications, 30000);
  
  // 点击外部关闭通知面板
  document.addEventListener('click', (e) => {
    if (notificationPanel && notificationPanel.classList.contains('open')) {
      if (!e.target.closest('.notification-panel') && !e.target.closest('.notification-btn')) {
        notificationPanel.classList.remove('open');
      }
    }
  });
}

// 检查未读通知数量
async function checkNotifications() {
  try {
    const res = await fetch('api/notifications.php?action=count');
    const data = await res.json();
    
    if (data.success && data.count > 0) {
      // 如果通知数量增加了，播放提示音
      if (data.count > lastNotificationCount && lastNotificationCount > 0) {
        playNotificationSound();
      }
      lastNotificationCount = data.count;
      showNotificationBadge(data.count);
    } else {
      lastNotificationCount = 0;
      hideNotificationBadge();
    }
  } catch (err) {
    console.error('检查通知失败:', err);
  }
}

// 显示通知红点
function showNotificationBadge(count) {
  const badge = document.getElementById('notification-badge');
  if (badge) {
    badge.textContent = count > 99 ? '99+' : count;
    badge.style.display = 'block';
  }
}

// 隐藏通知红点
function hideNotificationBadge() {
  const badge = document.getElementById('notification-badge');
  if (badge) {
    badge.style.display = 'none';
  }
}

// 切换通知面板
async function toggleNotifications() {
  if (!notificationPanel) return;
  
  if (notificationPanel.classList.contains('open')) {
    notificationPanel.classList.remove('open');
  } else {
    notificationPanel.classList.add('open');
    await loadNotifications();
  }
}

// 加载通知列表
async function loadNotifications() {
  try {
    const res = await fetch('api/notifications.php?action=list');
    const data = await res.json();
    
    const list = document.getElementById('notification-list');
    
    if (data.success && data.notifications && data.notifications.length > 0) {
      list.innerHTML = data.notifications.map(n => `
        <div class="notification-item ${n.is_read ? '' : 'unread'}" onclick="handleNotificationClick(${n.id})">
          <div class="notification-item-header">
            <span class="notification-item-actor">${escHtml(n.actor_username)}</span>
            <span class="notification-item-time">${formatNotificationTime(n.created_at)}</span>
          </div>
          <div class="notification-item-message">${escHtml(n.message)}</div>
        </div>
      `).join('');
    } else {
      list.innerHTML = '<div class="notification-empty">暫無通知</div>';
    }
  } catch (err) {
    console.error('加载通知失败:', err);
  }
}

// 处理通知点击
async function handleNotificationClick(notificationId) {
  try {
    await fetch('api/notifications.php?action=mark_read', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: notificationId })
    });
    
    // 重新加载通知和计数
    await loadNotifications();
    await checkNotifications();
  } catch (err) {
    console.error('标记通知失败:', err);
  }
}

// 全部标记为已读
async function markAllNotificationsRead() {
  try {
    await fetch('api/notifications.php?action=mark_all_read', {
      method: 'POST'
    });
    
    // 重新加载
    await loadNotifications();
    await checkNotifications();
    
    toast('✓ 已標記所有通知為已讀');
  } catch (err) {
    console.error('标记所有通知失败:', err);
  }
}

// 格式化通知时间
function formatNotificationTime(dateString) {
  const date = new Date(dateString);
  const now = new Date();
  const diff = Math.floor((now - date) / 1000); // 秒
  
  if (diff < 60) return '剛剛';
  if (diff < 3600) return Math.floor(diff / 60) + ' 分鐘前';
  if (diff < 86400) return Math.floor(diff / 3600) + ' 小時前';
  if (diff < 604800) return Math.floor(diff / 86400) + ' 天前';
  
  return date.toLocaleDateString('zh-TW', { month: 'numeric', day: 'numeric' });
}

// 页面加载时初始化通知
document.addEventListener('DOMContentLoaded', function() {
  initNotifications();
});

// 手機版 Tab 切換
function switchMobileTab(colName) {
  // 使用者手動切換：記住分頁
  setMobileTab(colName);
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

function initMobileTabs() {
  // 重整/首次載入：固定跳今日專注
  if (window.innerWidth <= 768) {
    setMobileTab('focus');
  }
}

function setMobileTab(colName) {
  document.querySelectorAll('.col').forEach(c => c.classList.remove('active'));
  document.querySelectorAll('.mobile-tab').forEach(t => t.classList.remove('active'));

  if (colName === 'goal') {
    // 顯示手機版戰略樹面板
    const goalMobilePanel = document.getElementById('mobile-goal-panel');
    if (goalMobilePanel) goalMobilePanel.style.display = 'block';
    const tab = document.querySelector('.mobile-tab[data-col="goal"]');
    if (tab) tab.classList.add('active');
  } else {
    const goalMobilePanel = document.getElementById('mobile-goal-panel');
    if (goalMobilePanel) goalMobilePanel.style.display = 'none';
    const col = document.querySelector(`.col[data-col="${colName}"]`);
    const tab = document.querySelector(`.mobile-tab[data-col="${colName}"]`);
    if (col) col.classList.add('active');
    if (tab) tab.classList.add('active');
  }
}

function openMobileSettings() {
  document.getElementById('mobile-settings-sheet').style.display = 'block';
}
function closeMobileSettings() {
  document.getElementById('mobile-settings-sheet').style.display = 'none';
}

// ==========================================
// 行內直接編輯函數
// ==========================================

// 取得卡片資料
function getCard(cardId) {
  for (const col of ['lib','week','focus','done']) {
    const card = state[col].find(c => c.id === cardId);
    if (card) return { card, col };
  }
  return null;
}

// 行內儲存（不重新渲染，保留展開狀態）
async function inlineSave(cardId, col, updates) {
  const found = getCard(cardId);
  if (!found) return;
  const card = found.card;
  // 用 getCard 找到的 col，比傳入的更可靠
  const actualCol = found.col || col;
  const data = {
    id: cardId, col: actualCol,
    title: card.title,
    project: card.project,
    priority: card.priority,
    sourceLink: card.sourceLink,
    summary: card.summary,
    nextStep: card.nextStep,
    body: card.body,
    bgcolor: card.bgcolor,
    textcolor: card.textcolor,
    isPrivate: card.isPrivate ? 1 : 0,
    checklist: card.checklist,
    parentId: card.parentId || null,
    level: card.level || 'general',
    ...updates
  };
  try {
    const res = await fetch('api/cards.php?action=save', {
      method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(data)
    });
    const result = await res.json();
    if (result.success) {
      // 更新本地 state，不重新渲染
      Object.assign(card, updates);
      // 只更新 checklist 標頭數字
      const cardEl = document.getElementById('card-' + cardId);
      if (cardEl) {
        const checkDone = (card.checklist||[]).filter(i=>i.checked).length;
        const checkTotal = (card.checklist||[]).length;
        const labels = cardEl.querySelectorAll('.cornell-label');
        labels.forEach(l => { if (l.textContent.startsWith('✓ 待辦')) l.textContent = `✓ 待辦 ${checkDone}/${checkTotal}`; });
      }
    } else { toast('❌ ' + (result.error || '儲存失敗')); }
  } catch(e) { toast('❌ 連線錯誤'); }
}

// 勾選待辦
// ===== 外部卡片 checklist 簡化版（跟 modal 一樣邏輯）=====
async function cbSave(cardId, col) {
  const found = getCard(cardId);
  if (!found) return;
  const actualCol = found.col || col;
  // 只從這張卡片的 DOM 讀取，用 card id 精確定位
  const cardEl = document.getElementById('card-' + cardId);
  if (!cardEl) return;
  const checklist = [];
  // 只選這張卡片內的 cli 項目
  for (let idx = 0; ; idx++) {
    const item = document.getElementById('cli-' + cardId + '-' + idx);
    if (!item) break;
    const cb = item.querySelector('input[type="checkbox"]');
    const ta = item.querySelector('textarea');
    const text = ta ? ta.value.trim() : '';
    if (text) {
      checklist.push({ text, checked: cb ? cb.checked : false });
      // 更新刪除線
      if (ta) {
        ta.style.textDecoration = cb.checked ? 'line-through' : 'none';
        ta.style.color = cb.checked ? 'var(--text-muted)' : 'var(--text)';
      }
    }
  }
  // 更新計數
  const done = checklist.filter(i => i.checked).length;
  const label = document.getElementById('cl-label-' + cardId);
  if (label) label.textContent = `✓ 待辦 ${done}/${checklist.length}`;
  await inlineSave(cardId, actualCol, { checklist });

  // 全部完成時彈出通知
  if (checklist.length > 0 && done === checklist.length && actualCol !== 'done') {
    setTimeout(() => {
      if (confirm(`🎉 「${found.card.title}」的待辦事項全部完成！\n要移到「已完成」嗎？`)) {
        moveAPI(cardId, 'done');
      }
    }, 200);
  }
}

async function cbAdd(cardId, col) {
  const input = document.getElementById('add-item-' + cardId);
  if (!input) return;
  const text = input.value.trim();
  if (!text) { input.focus(); return; }
  const found = getCard(cardId);
  if (!found) return;
  const actualCol = found.col || col;
  const checklist = JSON.parse(JSON.stringify(found.card.checklist || []));
  checklist.push({ text, checked: false });
  await inlineSave(cardId, actualCol, { checklist });
  input.value = '';
  const currentTab = document.querySelector('.mobile-tab.active')?.dataset?.col || null;
  await loadCards();
  if (currentTab && window.innerWidth <= 768) setMobileTab(currentTab);
  setTimeout(() => {
    const newInput = document.getElementById('add-item-' + cardId);
    if (newInput) newInput.focus();
  }, 200);
}

async function cbDel(cardId, idx, col) {
  const found = getCard(cardId);
  if (!found) return;
  const actualCol = found.col || col;
  const checklist = JSON.parse(JSON.stringify(found.card.checklist || []));
  checklist.splice(idx, 1);
  await inlineSave(cardId, actualCol, { checklist });
  const currentTab = document.querySelector('.mobile-tab.active')?.dataset?.col || null;
  await loadCards();
  if (currentTab && window.innerWidth <= 768) setMobileTab(currentTab);
}

async function inlineToggleChecklist(cardId, idx, col) {
  const found = getCard(cardId);
  if (!found) return;
  const actualCol = found.col || col;
  const checklist = JSON.parse(JSON.stringify(found.card.checklist || []));
  if (checklist[idx]) checklist[idx].checked = !checklist[idx].checked;
  await inlineSave(cardId, actualCol, { checklist });
  // 更新視覺樣式（不重新渲染）
  const cardEl = document.getElementById('card-' + cardId);
  if (cardEl) {
    const items = cardEl.querySelectorAll('.card-checklist-item');
    if (items[idx]) {
      const isChecked = checklist[idx].checked;
      items[idx].classList.toggle('checked', isChecked);
      // 更新文字刪除線
      const textInput = items[idx].querySelector('.checklist-text-edit');
      if (textInput) {
        textInput.style.textDecoration = isChecked ? 'line-through' : 'none';
        textInput.style.color = isChecked ? 'var(--text-muted)' : 'var(--text)';
      }
      // 更新計數
      const doneCount = checklist.filter(i => i.checked).length;
      const label = cardEl.querySelector('.cornell-label');
      if (label && label.textContent.startsWith('✓')) {
        label.textContent = `✓ 待辦 ${doneCount}/${checklist.length}`;
      }
    }
  }
}

// 刪除待辦項
async function inlineDeleteChecklist(cardId, idx, col) {
  const found = getCard(cardId);
  if (!found) return;
  const checklist = JSON.parse(JSON.stringify(found.card.checklist || []));
  checklist.splice(idx, 1);
  await inlineSave(cardId, col, { checklist });
  // 重新渲染這一欄
  const currentTab = document.querySelector('.mobile-tab.active')?.dataset?.col || null;
  await loadCards();
  if (currentTab && window.innerWidth <= 768) setMobileTab(currentTab);
  // 重新展開這張卡片
  setTimeout(() => {
    const cardEl = document.getElementById('card-' + cardId);
    if (cardEl) { cardEl.classList.add('open'); cardEl.scrollIntoView({behavior:'smooth', block:'nearest'}); }
  }, 150);
}

// 新增待辦項
async function inlineAddChecklist(cardId, col) {
  const input = document.getElementById('add-item-' + cardId);
  if (!input) return;
  const text = input.value.trim();
  if (!text) { input.focus(); return; }
  const found = getCard(cardId);
  if (!found) return;
  const actualCol = found.col || col;
  const checklist = JSON.parse(JSON.stringify(found.card.checklist || []));
  checklist.push({ text, checked: false });
  await inlineSave(cardId, actualCol, { checklist });
  input.value = '';
  // 重新渲染並展開
  const currentTab = document.querySelector('.mobile-tab.active')?.dataset?.col || null;
  await loadCards();
  if (currentTab && window.innerWidth <= 768) setMobileTab(currentTab);
  setTimeout(() => {
    const cardEl = document.getElementById('card-' + cardId);
    if (cardEl) { cardEl.scrollIntoView({behavior:'smooth', block:'nearest'}); }
    // 重新 focus 新增欄位
    const newInput = document.getElementById('add-item-' + cardId);
    if (newInput) newInput.focus();
  }, 200);
}

// 儲存筆記
async function inlineSaveNote(cardId, col, text) {
  const body = text ? text.split('\n').map(l => `<p>${escHtml(l) || '<br>'}</p>`).join('') : '';
  await inlineSave(cardId, col, { body });
  toast('✅ 筆記已儲存');
}

// 浮動迷你富文字工具列
function miniCmd(cmd, value) {
  document.execCommand(cmd, false, value || null);
}

// 全選筆記區文字
function noteSelectAll(noteId) {
  const note = document.getElementById(noteId);
  if (!note) return;
  note.focus();
  const range = document.createRange();
  range.selectNodeContents(note);
  const sel = window.getSelection();
  sel.removeAllRanges();
  sel.addRange(range);
}

// 選取游標所在行
function noteSelectLine(noteId) {
  const note = document.getElementById(noteId);
  if (!note) return;
  note.focus();
  const sel = window.getSelection();
  if (!sel || !sel.rangeCount) return;
  const lineEl = getCurrentLineEl(note);
  const range = document.createRange();
  range.selectNodeContents(lineEl);
  sel.removeAllRanges();
  sel.addRange(range);
}
// 選取游標所在行從行首到游標位置
// 找游標所在的行元素（li, p, div）
function getCurrentLineEl(note) {
  const sel = window.getSelection();
  if (!sel || !sel.rangeCount) return note;
  let node = sel.getRangeAt(0).startContainer;
  while (node && node !== note) {
    if (node.nodeType === 1 && ['LI','P','DIV'].includes(node.nodeName)) return node;
    node = node.parentNode;
  }
  return note;
}

// 選取整行文字並執行指令，再恢復游標
function applyToCurrentLine(note, cmd, value) {
  const sel = window.getSelection();
  if (!sel || !sel.rangeCount) return;
  const savedRange = sel.getRangeAt(0).cloneRange();
  const lineEl = getCurrentLineEl(note);
  const range = document.createRange();
  range.selectNodeContents(lineEl);
  sel.removeAllRanges();
  sel.addRange(range);
  document.execCommand(cmd, false, value || null);
  sel.removeAllRanges();
  sel.addRange(savedRange);
}

// 文字顏色：整行
function setNoteColor(btn, color) {
  // 可能在子選單裡，用 id 找對應的 note
  const menu = btn.closest('[id^="color-menu-"]');
  const toolbar = btn.closest('.mini-toolbar');
  let note = null;
  if (menu) {
    const cardId = menu.id.replace('color-menu-', '');
    note = document.getElementById('note-' + cardId);
  } else if (toolbar) {
    note = document.getElementById('note-' + toolbar.id.replace('mtb-', ''));
  }
  if (!note) return;
  note.focus();
  const sel = window.getSelection();
  if (!sel || !sel.rangeCount) return;
  if (sel.toString().length > 0) {
    document.execCommand('foreColor', false, color);
  } else {
    applyToCurrentLine(note, 'foreColor', color);
  }
}

// 底色筆：整行
function setNoteBgColor(btn, color) {
  const menu = btn.closest('[id^="bg-menu-"]');
  const toolbar = btn.closest('.mini-toolbar');
  let note = null;
  if (menu) {
    const cardId = menu.id.replace('bg-menu-', '');
    note = document.getElementById('note-' + cardId);
  } else if (toolbar) {
    note = document.getElementById('note-' + toolbar.id.replace('mtb-', ''));
  }
  if (!note) return;
  note.focus();
  const sel = window.getSelection();
  if (!sel || !sel.rangeCount) return;
  if (sel.toString().length > 0) {
    document.execCommand('hiliteColor', false, color);
  } else {
    applyToCurrentLine(note, 'hiliteColor', color);
  }
}

// 粗體：整行
function applyFormatBefore(cmd) {
  const sel = window.getSelection();
  if (!sel || !sel.rangeCount) { document.execCommand(cmd, false, null); return; }
  if (sel.toString().length > 0) {
    document.execCommand(cmd, false, null);
  } else {
    const note = document.activeElement;
    if (!note || !note.classList.contains('note-editable')) return;
    applyToCurrentLine(note, cmd, null);
  }
}


// 筆記欄開關
// toggleNoteEdit 已簡化，筆記欄永遠可編輯，點空白自動存
function toggleNoteEdit(cardId, col) {
  const note = document.getElementById('note-' + cardId);
  if (!note) return;
  note.focus();
  showMiniToolbar('mtb-' + cardId);
}

// 子選單開關
function toggleSubMenu(menuId, btn) {
  const menu = document.getElementById(menuId);
  if (!menu) return;
  const isOpen = menu.style.display === 'flex';
  document.querySelectorAll('[id^="color-menu-"],[id^="bg-menu-"],[id^="fmt-menu-"]').forEach(m => m.style.display = 'none');
  if (!isOpen) {
    menu.style.display = 'flex';
    window._miniToolbarLocked = true; // 子選單開啟，鎖定不隱藏 toolbar
    if (btn) {
      const rect = btn.getBoundingClientRect();
      requestAnimationFrame(() => {
        const menuW = menu.offsetWidth || 120;
        const menuH = menu.offsetHeight || 48;
        let left = rect.left - menuW - 8;
        if (left < 8) left = rect.right + 8;
        let top = rect.top + (rect.height / 2) - (menuH / 2);
        top = Math.max(8, Math.min(top, window.innerHeight - menuH - 8));
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
        menu.style.right = 'auto';
      });
    }
  } else {
    window._miniToolbarLocked = false;
  }
}
function hideSubMenu(menuId) {
  const menu = document.getElementById(menuId);
  if (menu) menu.style.display = 'none';
  // 解鎖並把焦點還給 note
  window._miniToolbarLocked = false;
  // 找目前展開的 toolbar 對應的 note，還回焦點
  setTimeout(() => {
    const activeNote = document.querySelector('.note-editable:focus, .note-editable[data-focused="true"]');
    if (!activeNote) {
      // 找最近被操作的 note（toolbar 還是 show 狀態的）
      const shownTb = document.querySelector('.mini-toolbar.show');
      if (shownTb) {
        const tbId = shownTb.id; // mtb-{cardId}
        const cardId = tbId.replace('mtb-', '');
        const noteEl = document.getElementById('note-' + cardId);
        if (noteEl) noteEl.focus();
      }
    }
  }, 50);
}

// 記憶上次選的顏色
const lastColors = {}; // cardId -> color
const lastBgColors = {}; // cardId -> bgColor

function setLastColor(cardId, color) {
  lastColors[cardId] = color;
  const btn = document.getElementById('color-btn-' + cardId);
  if (btn) btn.style.borderBottom = '3px solid ' + color;
}

function setLastBgColor(cardId, color) {
  lastBgColors[cardId] = color;
  const icon = document.getElementById('bg-icon-' + cardId);
  if (icon) {
    const rect = icon.querySelector('rect');
    const poly = icon.querySelector('polygon');
    if (rect) rect.setAttribute('fill', color);
    if (poly) poly.setAttribute('fill', color === '#1A1A18' ? 'rgba(255,255,255,0.3)' : color);
  }
}

function applyLastColor(cardId) {
  const color = lastColors[cardId] || '#E24B4A';
  const note = document.getElementById('note-' + cardId);
  if (!note) return;
  note.focus();
  const sel = window.getSelection();
  if (!sel || !sel.rangeCount) return;
  if (sel.toString().length > 0) {
    document.execCommand('foreColor', false, color);
  } else {
    applyToCurrentLine(note, 'foreColor', color);
  }
}

function applyLastBgColor(cardId) {
  const color = lastBgColors[cardId] || '#FFFACC';
  const note = document.getElementById('note-' + cardId);
  if (!note) return;
  note.focus();
  const sel = window.getSelection();
  if (!sel || !sel.rangeCount) return;
  if (sel.toString().length > 0) {
    document.execCommand('hiliteColor', false, color);
  } else {
    applyToCurrentLine(note, 'hiliteColor', color);
  }
}

function showMiniToolbar(id) {
  const tb = document.getElementById(id);
  if (tb) { tb.classList.add('show'); tb.classList.add('active'); tb.classList.remove('hidden'); }
}
function hideMiniToolbar(id) {
  setTimeout(() => {
    const tb = document.getElementById(id);
    if (!tb) return;
    if (window._miniToolbarLocked) return;
    if (tb.contains(document.activeElement)) return;
    tb.classList.remove('show');
    // 不自動移除 active，讓使用者點 ✕ 才關閉
  }, 300);
}

// 儲存富文字筆記（保留 HTML）
async function inlineSaveNoteHTML(cardId, col, html) {
  if (html === '<br>' || html === '') html = '';
  // col 防呆：從 state 找卡片在哪個欄位
  if (!col || col === 'undefined') {
    const found = getCard(parseInt(cardId));
    col = found ? found.col : 'lib';
  }
  await inlineSave(parseInt(cardId), col, { body: html });
}

// 編輯待辦項目文字
async function inlineEditChecklistText(cardId, idx, col, newText) {
  const found = getCard(cardId);
  if (!found) return;
  const checklist = JSON.parse(JSON.stringify(found.card.checklist || []));
  if (!checklist[idx] || checklist[idx].text === newText) return;
  checklist[idx].text = newText;
  await inlineSave(cardId, col, { checklist });
}

// 子任務選單
function toggleSubtaskMenu(menuId, e) {
  const menu = document.getElementById(menuId);
  if (!menu) return;
  const isOpen = menu.classList.contains('open');
  document.querySelectorAll('.subtask-dropdown.open').forEach(m => m.classList.remove('open'));
  if (!isOpen) {
    menu.classList.add('open');
    const btn = e.target;
    const rect = btn.getBoundingClientRect();
    menu.style.top = (rect.bottom + 4) + 'px';
    menu.style.left = Math.min(rect.left, window.innerWidth - 170) + 'px';
  }
}
function closeSubtaskMenu(menuId) {
  const menu = document.getElementById(menuId);
  if (menu) menu.classList.remove('open');
}
document.addEventListener('click', () => {
  document.querySelectorAll('.subtask-dropdown.open').forEach(m => m.classList.remove('open'));
});

// 子任務升格：把主任務移到今日專注，記住子任務 index
// 格式：localStorage 'subtaskFocus' = {cardId, idx, title, fromCol}
async function promoteSubtaskToFocus(cardId, idx, col) {
  const found = getCard(cardId);
  if (!found) return;
  const card = found.card;
  const item = card.checklist[idx];
  if (!item) return;

  // 檢查自己的今日專注是否已滿
  const myFocusCount = state.focus.filter(c => c.createdByUsername === CURRENT_USERNAME || !c.createdByUsername).length;
  if (myFocusCount >= 1) {
    toast('❌ 你的今日專注已有 1 張，請先完成或移出');
    return;
  }

  // 記住子任務資訊（包含原本在哪個欄位）
  try {
    localStorage.setItem('subtaskFocus', JSON.stringify({
      cardId: cardId,
      idx: idx,
      title: item.text,
      fromCol: col
    }));
  } catch(e) {}

  // 把主任務卡片移到今日專注
  await moveAPI(cardId, 'focus');
  toast('🎯 專注子任務：' + item.text);
}

// 取得目前的子任務專注資訊
function getSubtaskFocus() {
  try {
    const data = localStorage.getItem('subtaskFocus');
    return data ? JSON.parse(data) : null;
  } catch(e) { return null; }
}

// 清除子任務專注
function clearSubtaskFocus() {
  try { localStorage.removeItem('subtaskFocus'); } catch(e) {}
}

// 完成子任務：勾選待辦項目，主任務移回原欄位
async function completeSubtask(cardId) {
  const sf = getSubtaskFocus();
  if (!sf || sf.cardId !== cardId) return;

  // 勾選子任務
  await inlineToggleChecklist(cardId, sf.idx, 'focus');

  // 清除子任務專注記錄
  clearSubtaskFocus();

  // 把主任務移回原欄位
  const fromCol = sf.fromCol || 'lib';
  await moveAPI(cardId, fromCol);
  toast('✅ 子任務完成，主任務已移回' + {lib:'策略筆記區', week:'本週目標', focus:'今日專注'}[fromCol]);
}

// 卡片動作選單
function toggleCardMenu(menuId) {
  const menu = document.getElementById(menuId);
  if (!menu) return;
  const isOpen = menu.classList.contains('open');
  // 關閉所有其他選單
  document.querySelectorAll('.card-actions-dropdown.open').forEach(m => m.classList.remove('open'));
  if (!isOpen) menu.classList.add('open');
}
function closeCardMenu(menuId) {
  const menu = document.getElementById(menuId);
  if (menu) menu.classList.remove('open');
}
// 點擊其他地方關閉選單
document.addEventListener('click', () => {
  document.querySelectorAll('.card-actions-dropdown.open').forEach(m => m.classList.remove('open'));
});

// 四象限優先級選擇
function selectPriority(value) {
  const current = document.getElementById('input-priority').value;
  if (current === value) {
    document.getElementById('input-priority').value = '';
    document.querySelectorAll('.priority-btn').forEach(b => b.classList.remove('active'));
  } else {
    document.getElementById('input-priority').value = value;
    document.querySelectorAll('.priority-btn').forEach(b => {
      b.classList.toggle('active', b.dataset.value === value);
    });
  }
}

// 手機版選單
function toggleMobileMenu() {
  // 先關閉搜尋下拉
  const sd = document.getElementById('search-dropdown');
  if (sd) sd.classList.remove('show');
  document.getElementById('mobile-dropdown').classList.toggle('open');
}
function closeMobileMenu() { document.getElementById('mobile-dropdown').classList.remove('open'); }

</script>
<div id="bottom-bar" style="position:fixed;bottom:0;left:0;right:0;height:50px;background:white;border-top:2px solid #eee;display:flex;align-items:center;justify-content:flex-end;padding:0 16px;gap:12px;z-index:90;">
  <?php if (in_array($_SESSION['username'] ?? '', ['admin', 'chienyi'])): ?>
  <a href="users.php" style="padding:8px 16px;background:#534AB7;color:white;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;">⚙️ 管理</a>
  <?php endif; ?>
  <a href="index.php?action=logout" style="padding:8px 16px;background:#FEE2E2;color:#991B1B;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;">🚪 登出</a>
</div>

<!-- 匯入/匯出全域元素，放 body 底部確保不被隱藏容器影響 -->
<input type="file" id="goal-import-file" accept=".json" style="position:fixed;top:-9999px;left:-9999px;opacity:0;width:1px;height:1px;">
<a id="goal-export-link" style="display:none;"></a>

</body>
</html>
