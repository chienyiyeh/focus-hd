<?php
// 引入設定檔 (確保 KANBAN_SESSION 一致)
require_once 'api/config.php';

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
<title>我的專注看板</title>

<!-- PWA 支援 -->
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#667eea">
<meta name="description" content="蘋果印刷設計工坊 - 任務管理與專注看板系統">

<!-- iOS Safari PWA 支援 -->
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="專注看板">
<link rel="apple-touch-icon" href="/icon-192.png">

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<!-- Quill ImageResize 模組 -->
<link href="https://unpkg.com/quill-image-resize-module@3.0.0/image-resize.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    /* =========================================
       UI v2: Notion-like Theme Variables
       ========================================= */
    --bg-main: #fcfcfc;
    --bg-card: #ffffff;
    --border-light: #eaeaea;
    --text-main: #333333;
    --text-muted: #888888;

    --accent-strategy: #e3f2fd;
    --accent-weekly: #f3e5f5;
    --accent-today: #ff9800;
    --accent-done: #e8f5e9;

    --radius-soft: 8px;
    --radius-card: 6px;
    --shadow-hover: 0 4px 12px rgba(0, 0, 0, 0.05);

    /* Backward-compatible aliases */
    --bg: var(--bg-main);
    --surface: var(--bg-card);
    --surface2: #f5f5f5;
    --border: var(--border-light);
    --border-strong: #dddddd;
    --text: var(--text-main);
    --text-secondary: #666666;

    --accent-lib: #7BA5D6;
    --accent-lib-bg: var(--accent-strategy);
    --accent-lib-text: #3A5A7A;

    --accent-week: #9B8FD9;
    --accent-week-bg: var(--accent-weekly);
    --accent-week-text: #524A8C;

    --accent-focus: #E8763E;
    --accent-focus-bg: #FFF3ED;
    --accent-focus-text: #B85828;

    --accent-done-bg: var(--accent-done);
    --accent-done-text: #4A6B4A;

    --radius: var(--radius-soft);
    --radius-lg: 14px;
  }

  body { font-family: 'Noto Sans TC', sans-serif; background-color: var(--bg-main); color: var(--text-main); min-height: 100vh; padding: 0; -webkit-font-smoothing: antialiased; }

  .ui-v2-header { background: var(--bg-card); border-bottom: 1px solid var(--border-light); padding: 12px 24px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 50; gap: 16px; }
  .logo { font-size: 15px; font-weight: 700; letter-spacing: -0.3px; color: var(--text); }
  .logo span { color: var(--accent-focus); }
  
  .header-center { display: flex; gap: 12px; align-items: center; flex: 1; justify-content: center; min-width: 0; }
  .search-input { width: 100%; padding: 6px 12px; border: 1px solid var(--border-light); border-radius: var(--radius-soft); font-size: 13px; font-family: inherit; color: var(--text-main); background: var(--bg-main); }
  .search-input:focus { outline: none; border-color: var(--accent-week); }
  
  .filter-tags { display: flex; gap: 6px; flex-wrap: nowrap; overflow-x: auto; scrollbar-width: none; -ms-overflow-style: none; max-width: 100%; align-items: center; }
  .filter-tags::-webkit-scrollbar { display: none; }
  .filter-tag { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; cursor: pointer; border: 1px solid var(--border); background: var(--surface); color: var(--text-secondary); transition: all 0.12s; white-space: nowrap; flex-shrink: 0; }
  .filter-tag.custom { opacity: 0.88; }
  .filter-divider { width: 1px; height: 18px; background: var(--border-strong); margin: 0 2px; flex-shrink: 0; }
  .filter-tag:hover { background: var(--surface2); }
  .filter-tag.active { background: var(--accent-week); color: white; border-color: var(--accent-week); }

  .header-right { display: flex; gap: 8px; align-items: center; flex-wrap: nowrap; }
  .user-info { font-size: 12px; color: var(--text-secondary); padding: 6px 12px; background: var(--surface2); border-radius: var(--radius); white-space: nowrap; }
  
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

  .header-left { display: flex; align-items: center; gap: 16px; }
  .header-right { display: flex; align-items: center; gap: 12px; color: var(--text-muted); font-size: 0.9rem; }
  .icon-btn, .text-btn, .privacy-select {
    padding: 6px 10px;
    border: 1px solid var(--border-light);
    border-radius: var(--radius-soft);
    background: var(--bg-main);
    color: var(--text-main);
    cursor: pointer;
    font-size: 12px;
    font-family: inherit;
  }
  .text-btn { text-decoration: none; display: inline-flex; align-items: center; }
  .icon-btn:hover, .text-btn:hover, .privacy-select:hover { box-shadow: var(--shadow-hover); }
  .user-info { font-weight: 500; color: var(--text-main); }
  .logout-link { color: #d32f2f; }
  
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
  
  /* 計時閃爍動畫 - 純閃爍不晃動 */
  .col-focus.timer-active .col-accent {
    animation: pureFlash 2s ease-in-out infinite;
    box-shadow: 0 2px 8px rgba(232, 118, 62, 0.3);
  }
  
  @keyframes pureFlash {
    0%, 100% { 
      background: var(--accent-focus);
      opacity: 1;
    }
    50% { 
      background: #FFA64D;
      opacity: 0.6;
    }
  }
  
  .cards-area { padding: 10px; min-height: 120px; }
  .empty { text-align: center; padding: 24px 16px; font-size: 12px; color: var(--text-muted); line-height: 1.6; }

  /* Cards */
  .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 12px; margin-bottom: 8px; cursor: move; transition: all 0.15s; position: relative; }
  .card:hover { border-color: var(--border-strong); box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
  .card.dragging { opacity: 0.5; cursor: grabbing; }
  .card.open { padding-bottom: 6px; }
  .card.open .card-body { display: block; }
  .card.open .card-preview { display: none; }
  .card.open .chevron { transform: rotate(180deg); }

  .card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; margin-bottom: 6px; }
  .drag-handle { cursor: grab; color: var(--text-muted); font-size: 16px; margin-right: 4px; }
  .card-title { font-size: 14px; font-weight: 500; line-height: 1.45; flex: 1; }
  .card-meta { display: flex; gap: 6px; margin-bottom: 8px; flex-wrap: wrap; align-items: center; }
  
  .project-tag { padding: 3px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; display: inline-block; }
  .project-tag.seo { background: #F0F7FF; color: #6B9BD6; }
  .project-tag.product { background: #F9F3FC; color: #A384C4; }
  .project-tag.client { background: #FFF8F0; color: #E89B6B; }
  .project-tag.family { background: #FEF3F7; color: #D98CA8; }
  .project-tag.sop { background: #F3F9F4; color: #7AAE7A; }
  .project-tag.finance { background: #FFFCF0; color: #E8C46B; }
  .project-tag.other { background: #F5F6F7; color: #8D9BA8; }

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
  .checklist-item input[type="text"] { flex: 1; border: none; background: transparent; font-size: 13px; padding: 0; font-family: inherit; }
  .checklist-item input[type="text"]:focus { outline: none; }
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

  .add-card-btn { width: 100%; padding: 12px; border: 1px dashed var(--border-strong); background: none; border-radius: var(--radius); font-size: 13px; font-weight: 500; font-family: inherit; color: var(--text-muted); cursor: pointer; margin-top: 4px; }
  .add-card-btn:hover { background: var(--surface2); color: var(--text-secondary); border-color: var(--text-secondary); }
  .clear-done-btn { width: 100%; padding: 10px; border: 1px solid var(--border); background: var(--surface); border-radius: var(--radius); font-size: 12px; font-family: inherit; color: var(--text-muted); cursor: pointer; margin-top: 8px; }

  /* Modal & Overlay */
  .overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 100; padding: 16px; }
  .overlay.open { display: flex; }
  .modal { background: var(--surface); border-radius: var(--radius-lg); width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; display: flex; flex-direction: column; }
  .modal-header { padding: 16px 20px; border-bottom: 1px solid var(--border); flex-shrink: 0; display: flex; align-items: center; gap: 12px; }
  .modal-title { font-size: 16px; font-weight: 700; flex: 1; }
  .modal-back-btn { 
    display: none; 
    background: none; 
    border: none; 
    font-size: 24px; 
    color: var(--text); 
    cursor: pointer; 
    padding: 0; 
    width: 32px; 
    height: 32px; 
    display: flex; 
    align-items: center; 
    justify-content: center;
    border-radius: var(--radius);
  }
  .modal-back-btn:hover { background: var(--surface2); }
  .modal-body { padding: 20px; flex: 1; overflow-y: auto; }
  .field { margin-bottom: 16px; position: relative; }
  .field-label { display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 8px; }
  .field-label .optional { font-weight: 400; color: var(--text-muted); font-size: 12px; }
  .field-input, .field-textarea, .field-select { width: 100%; padding: 12px 14px; border: 1px solid var(--border); border-radius: var(--radius); font-family: inherit; font-size: 14px; color: var(--text); background: var(--surface); }
  .field-input:focus, .field-textarea:focus, .field-select:focus { outline: none; border-color: var(--accent-week); }
  .field-textarea { resize: vertical; min-height: 80px; line-height: 1.5; }
  .priority-group { display: flex; gap: 8px; flex-wrap: wrap; }
  .priority-btn { padding: 9px 12px; border: 1px solid var(--border-strong); background: var(--surface); border-radius: var(--radius); font-size: 13px; font-family: inherit; color: var(--text-secondary); cursor: pointer; transition: all 0.15s; }
  .priority-btn:hover { background: var(--surface2); }
  .priority-btn.active { background: var(--accent-week-bg); color: var(--accent-week-text); border-color: var(--accent-week); font-weight: 600; }

  /* 手機觸控優化：降低點擊延遲，避免按鈕難點 */
  button, .priority-btn, .add-checklist-item-btn, .checklist-item-delete, .card-checklist-item input[type="checkbox"] { touch-action: manipulation; }
  
  /* Word 編輯器預覽區 */
  .word-preview { min-height: 100px; max-height: 150px; overflow-y: auto; border: 1px solid var(--border); border-radius: var(--radius); padding: 12px; background: #FAFAFA; cursor: pointer; transition: all 0.2s; }
  .word-preview:hover { border-color: var(--accent-week); background: var(--surface); }
  .word-preview.empty { color: var(--text-muted); font-style: italic; }
  
  /* 全螢幕 Quill 編輯器 */
  .fullscreen-editor { position: fixed; inset: 0; background: var(--surface); z-index: 200; display: none; flex-direction: column; }
  .fullscreen-editor.open { display: flex; }
  .editor-header { background: var(--surface2); border-bottom: 1px solid var(--border); padding: 12px 20px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
  .editor-title { font-size: 14px; font-weight: 600; }
  .quill-container { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
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
  .swatches { display: flex; gap: 10px; flex-wrap: wrap; }
  .swatch { width: 36px; height: 36px; border-radius: 8px; cursor: pointer; border: 2px solid transparent; }
  .swatch.selected { border-color: var(--accent-week); box-shadow: 0 0 0 2px var(--surface), 0 0 0 4px var(--accent-week); }

  .modal-footer { padding: 16px 20px; border-top: 1px solid var(--border); display: flex; gap: 12px; justify-content: flex-end; flex-shrink: 0; }
  .modal-btn { padding: 12px 24px; border: none; border-radius: var(--radius); font-family: inherit; font-size: 14px; font-weight: 600; cursor: pointer; }
  .modal-btn.primary { background: var(--accent-week); color: white; }
  .modal-btn.secondary { background: var(--surface2); color: var(--text-secondary); }

  /* 專案管理 Modal */
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

  /* 說明菜单样式（与匯出菜单相同） */
  .help-menu { position: relative; display: inline-block; }
  .help-dropdown { display: none; position: absolute; right: 0; top: 100%; margin-top: 8px; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: 0 8px 24px rgba(0,0,0,0.15); min-width: 180px; z-index: 60; overflow: hidden; }
  .help-dropdown.open { display: block; }

  /* 侧边编辑栏（桌面版） */
  .kanban-container { display: flex; gap: 0; flex: 1; min-height: 0; }
  .board-section { flex: 1; min-width: 0; overflow: hidden; }
  .editor-sidebar { 
    width: 420px; 
    background: var(--surface); 
    border-left: 1px solid var(--border-strong); 
    overflow-y: auto; 
    display: none;
    flex-shrink: 0;
  }
  .editor-sidebar.open { display: block; }
  .editor-sidebar-header { 
    padding: 20px 24px; 
    border-bottom: 1px solid var(--border); 
    display: flex; 
    align-items: center; 
    justify-content: space-between;
    position: sticky;
    top: 0;
    background: var(--surface);
    z-index: 10;
  }
  .editor-sidebar-title { font-size: 18px; font-weight: 600; color: var(--text); }
  .editor-sidebar-close { 
    background: none; 
    border: none; 
    font-size: 24px; 
    color: var(--text-muted); 
    cursor: pointer; 
    padding: 0; 
    width: 32px; 
    height: 32px; 
    display: flex; 
    align-items: center; 
    justify-content: center;
    border-radius: var(--radius);
  }
  .editor-sidebar-close:hover { background: var(--surface2); color: var(--text); }
  .editor-sidebar-body { padding: 24px; }
  
  /* 卡片高亮状态（当在编辑时） */
  .card.editing { 
    border-color: var(--accent-week); 
    border-width: 2px; 
    background: var(--accent-week-bg);
  }

  /* 更新日志样式 */
  .changelog-version {
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
  }
  .changelog-version:last-of-type {
    border-bottom: none;
  }
  .changelog-version h3 {
    font-size: 16px;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .version-date {
    font-size: 12px;
    font-weight: 400;
    color: var(--text-muted);
    background: var(--surface2);
    padding: 4px 10px;
    border-radius: 12px;
  }
  .changelog-version ul {
    list-style: none;
    padding: 0;
    margin: 0;
  }
  .changelog-version li {
    padding: 6px 0;
    font-size: 14px;
    color: var(--text-secondary);
    line-height: 1.6;
  }

  /* 預設隱藏手機版元素（桌面版不顯示） */
  .mobile-tab-bar { display: none; }
  .mobile-more-menu { display: none; }

  /* 響應式 */
  @media (max-width: 1024px) { 
    .board-wrap { grid-template-columns: repeat(2, minmax(0, 1fr)); } 
  }

  @media (max-width: 768px) { 
    html { 
      margin: 0 !important; 
      padding: 0 !important; 
      width: 100% !important;
      overflow-x: hidden !important;
      
    }
    
    body { 
      background: var(--surface2); 
      padding: 0 !important; 
      padding-bottom: 70px !important; /* 只保留底部給 Tab */
      margin: 0 !important;
      width: 100% !important;
      overflow-x: hidden !important;
      
    }
    
    header { 
      padding: 12px 0; /* 上下 12px，左右 0 */
      gap: 10px; 
      flex-direction: row;
      flex-wrap: wrap;
      width: 100% !important;
      margin: 0 !important;
      
    }
    
    .logo { 
      font-size: 16px;
      flex: 0 0 auto;
      padding-left: 16px; /* logo 內部加 padding */
    }
    
    .header-center { 
      order: 3; 
      width: 100%; 
      margin-top: 8px;
      flex: 1 1 100%;
      padding: 0 16px; /* 內部元素有 padding */
    }
    
    .header-right { 
      order: 2; 
      flex: 1; 
      justify-content: flex-end;
      gap: 10px;
      padding-right: 16px; /* 右側 padding */
    }
    
    .user-info { display: none; }
    
    /* 手機版 header-center 只保留搜尋框 */
    .search-box { max-width: 100%; }
    .search-input {
      font-size: 15px; /* 搜尋框字體加大 */
      padding: 10px 12px;
    }
    .filter-tags { display: none !important; } /* 隱藏專案標籤 */
    #privacyFilter { display: none !important; } /* 隱藏隱私篩選 */
    
    .sidebar { 
      width: 100%; 
      position: relative; 
      top: 0; 
      border-radius: 0; 
      border-left: none; 
      border-right: none; 
      margin: 0;
      padding: 0 16px; /* 側邊欄內容有內邊距 */
    }
    
    .main-wrap { 
      flex-direction: column; 
      padding: 0 !important; 
      margin: 0 !important;
      gap: 0; 
      min-height: calc(100vh - 140px);
      width: 100% !important;
      
    }
    
    /* 手機版：預設隱藏所有欄位 */
    .board-wrap { 
      display: block !important; /* 改用 block 而不是 grid */
      grid-template-columns: none !important; 
      gap: 0 !important; 
      padding: 0 !important; 
      margin: 0 !important;
      width: 100% !important;
    }
    
    .col { 
      display: none;
      border-radius: 0 !important; 
      border: none !important;
      border-left: none !important;
      border-right: none !important;
      box-shadow: none !important;
      min-height: calc(100vh - 200px);
      width: 100vw !important; /* 使用 viewport 宽度 */
      max-width: 100vw !important;
      margin: 0 !important;
      padding: 0 !important;
      box-sizing: border-box !important;
      flex: none !important;
      position: relative;
      left: 0 !important;
      right: 0 !important;
    }
    
    /* 強制每個區域都是 100% 視口寬度 */
    .col-lib, .col-week, .col-focus, .col-done {
      width: 100vw !important;
      max-width: 100vw !important;
      min-width: 100vw !important;
      flex: none !important;
      border: none !important;
      border-left: none !important;
      border-right: none !important;
    }
    
    /* 顯示當前選中的欄位 */
    .col.active { display: block !important; }
    
    .col-header { 
      padding: 14px 16px; 
      background: var(--surface);
      margin: 0;
    }
    
    .col-title {
      font-size: 18px; /* 16px → 18px */
    }
    
    .badge {
      font-size: 13px; /* 12px → 13px */
    }
    
    .col-accent { width: 100%; margin: 0; }
    
    .cards-area { 
      padding: 0 !important; 
      margin: 0 !important;
      min-height: 300px; 
      background: var(--surface2);
      width: 100% !important;
    }
    
    .empty {
      padding: 24px 16px;
      margin: 0;
      width: 100%;
      font-size: 16px; /* 15px → 16px */
    }
    
    /* 手機版添加卡片按鈕滿版 */
    .add-card-btn {
      border-radius: 0;
      margin: 0;
      border-left: none;
      border-right: none;
      border-top: none;
      padding: 16px;
      font-size: 15px;
    }
    
    /* 手機版計時器滿版 */
    .focus-timer {
      border-radius: 0;
      margin: 0 0 1px 0;
      border-left: none;
      border-right: none;
      border-top: none;
      padding: 16px;
    }
    
    .filter-tags { 
      flex-wrap: nowrap; 
      overflow-x: auto; 
      padding: 0 16px 6px 16px; 
      -webkit-overflow-scrolling: touch; 
      scrollbar-width: none; 
      margin: 0;
    }
    .filter-tags::-webkit-scrollbar { display: none; }
    .filter-tag { flex-shrink: 0; }
    
    /* 手機版 header-actions 優化 */
    .header-right { 
      gap: 8px; 
      flex-wrap: nowrap; 
      overflow: visible; /* 改為 visible */
    }
    
    /* 手機版隱藏部分按鈕 */
    .header-right .export-menu,
    .header-right .help-menu,
    .header-right .settings-btn,
    .header-right .logout-btn,
    .header-right #notification-sound-toggle {
      display: none !important;
    }
    
    /* 手機版通知按鈕 */
    .header-right .notification-btn { 
      padding: 8px; 
      font-size: 18px;
    }
    
    /* 手機版統計按鈕 - 隱藏，移到選單 */
    .header-right .settings-btn[href*="stats"] {
      display: none !important;
    }
    
    /* 手機版卡片滿版 */
    .card { 
      width: 100% !important;
      max-width: 100% !important;
      padding: 16px !important; 
      border-radius: 0 !important;
      margin: 0 !important;
      border: none !important;
      border-bottom: 1px solid var(--border) !important;
      box-sizing: border-box !important;
      font-size: 16px; /* 15px → 16px */
    }
    
    .card-title {
      font-size: 18px; /* 16px → 18px */
      font-weight: 600;
    }
    
    .card-meta {
      font-size: 14px; /* 13px → 14px */
    }
    
    .card-summary {
      font-size: 16px; /* 14px → 16px */
    }
    
    .act-btn { 
      padding: 10px 14px; 
      font-size: 15px; /* 14px → 15px */
    }
    
    /* 手机版modal显示返回按钮 */
    .modal-back-btn { display: flex !important; }
    
    /* 手机版隐藏侧边栏，使用全屏modal */
    .editor-sidebar { display: none !important; }
    .kanban-container { display: block; }
    
    /* 底部 Tab Bar */
    .mobile-tab-bar {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background: var(--surface);
      border-top: 1px solid var(--border);
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      height: 64px;
      z-index: 50;
      box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
    }
    
    .mobile-tab {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 4px;
      cursor: pointer;
      border: none;
      background: none;
      padding: 8px;
      color: var(--text-muted);
      transition: all 0.2s;
    }
    
    .mobile-tab-icon {
      font-size: 24px;
      line-height: 1;
    }
    
    .mobile-tab-label {
      font-size: 11px;
      font-weight: 500;
    }
    
    .mobile-tab.active {
      color: var(--accent-focus);
      background: var(--accent-focus-bg);
    }
    
    .mobile-tab:active {
      transform: scale(0.95);
    }
    
    /* 手機版「更多」菜單 */
    .mobile-more-menu {
      display: block;
      position: relative;
    }
    
    .mobile-more-btn {
      padding: 8px 12px;
      font-size: 20px;
      font-weight: bold;
      background: none;
      border: none;
      cursor: pointer;
      color: var(--text);
      line-height: 1;
    }
    
    .mobile-more-dropdown {
      display: none;
      position: absolute;
      top: 100%;
      right: 0;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      min-width: 140px;
      z-index: 100;
      margin-top: 8px;
      overflow: hidden;
    }
    
    .mobile-more-dropdown.show {
      display: block;
    }
    
    .mobile-more-option {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 14px 16px;
      cursor: pointer;
      border-bottom: 1px solid var(--border);
      transition: background 0.2s;
    }
    
    .mobile-more-option:last-child {
      border-bottom: none;
    }
    
    .mobile-more-option:active {
      background: var(--surface2);
    }
    
    .mobile-more-icon {
      font-size: 18px;
      line-height: 1;
    }
    
    .mobile-more-label {
      font-size: 14px;
      font-weight: 500;
      color: var(--text);
    }
  }
</style>
</head>
<body>

<header class="ui-v2-header">
  <div class="header-left">
    <div class="logo">FOCUS <span>HD</span></div>
  </div>

  <div class="header-center">
    <input type="text" id="searchInput" class="search-input" placeholder="搜尋卡片...">
    <div id="projectFilterContainer" class="filter-tags"></div>
    <select id="privacyFilter" class="privacy-select">
      <option value="all">全部</option>
      <option value="shared">共用</option>
      <option value="private">私人</option>
    </select>
  </div>

  <div class="header-right">
    <button id="notification-sound-toggle" class="icon-btn" onclick="toggleNotificationSound()" title="點擊關閉提示音">🔔 通知音</button>
    <button class="icon-btn notification-btn" id="notification-btn" onclick="toggleNotifications()">
      🔔 通知
      <span class="notification-badge" id="notification-badge" style="display:none;">0</span>
    </button>
    <div class="user-info"><?php echo htmlspecialchars($username); ?></div>
    <a href="stats.php" class="text-btn settings-btn" style="text-decoration: none;">統計</a>
    <button class="text-btn settings-btn" onclick="openProjectSettings()">設定</button>
    <div class="export-menu">
      <button id="btnExport" class="text-btn export-btn" onclick="toggleExportMenu()">匯出 ▾</button>
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
    <div class="help-menu">
      <button class="text-btn help-toggle" onclick="toggleHelpMenu()">說明</button>
      <div class="help-dropdown" id="help-dropdown">
        <div class="export-option" onclick="openChangelog()">
          <div class="export-option-title">📋 更新日誌</div>
          <div class="export-option-desc">查看版本更新歷史</div>
        </div>
        <div class="export-option" onclick="toggleHelp()">
          <div class="export-option-title">💡 使用說明</div>
          <div class="export-option-desc">看板功能介紹</div>
        </div>
      </div>
    </div>

    <div class="mobile-more-menu">
      <button class="mobile-more-btn" onclick="toggleMobileMoreMenu()">⋯</button>
      <div class="mobile-more-dropdown" id="mobile-more-dropdown">
        <div class="mobile-more-option" onclick="window.location.href='stats.php';">
          <div class="mobile-more-icon">📊</div>
          <div class="mobile-more-label">統計</div>
        </div>
        <div class="mobile-more-option" onclick="openProjectSettings(); closeMobileMoreMenu();">
          <div class="mobile-more-icon">⚙️</div>
          <div class="mobile-more-label">設定</div>
        </div>
        <div class="mobile-more-option" onclick="toggleExportMenu(); closeMobileMoreMenu();">
          <div class="mobile-more-icon">📥</div>
          <div class="mobile-more-label">匯出</div>
        </div>
        <div class="mobile-more-option" onclick="openChangelog(); closeMobileMoreMenu();">
          <div class="mobile-more-icon">📋</div>
          <div class="mobile-more-label">更新</div>
        </div>
        <div class="mobile-more-option" onclick="toggleHelp(); closeMobileMoreMenu();">
          <div class="mobile-more-icon">💡</div>
          <div class="mobile-more-label">說明</div>
        </div>
        <div class="mobile-more-option" onclick="logout();">
          <div class="mobile-more-icon">🚪</div>
          <div class="mobile-more-label">登出</div>
        </div>
      </div>
    </div>

    <button class="text-btn logout-link" onclick="logout()">登出</button>
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

  <!-- 桌面版：看板 + 侧边编辑栏容器 -->
  <div class="kanban-container">
    <div class="board-section">
      <div class="board-wrap">
    <div class="col col-lib" data-col="lib">
      <div class="col-accent"></div>
      <div class="col-header">
        <div class="col-title-row">
          <div class="col-title">📚 策略筆記區</div>
          <div class="badge badge-lib" id="badge-lib">0 則</div>
        </div>
        <div class="col-sub">長期儲存 • 無限制</div>
      </div>
      <div class="cards-area" id="cards-lib"></div>
      <div style="padding: 0 16px 16px;"><button class="add-card-btn" onclick="openModal('lib')">+ 新增筆記</button></div>
    </div>

    <div class="col col-week" data-col="week">
      <div class="col-accent"></div>
      <div class="col-header">
        <div class="col-title-row">
          <div class="col-title">📅 本週目標</div>
          <div class="badge badge-week" id="badge-week">0 / 3</div>
        </div>
        <div class="col-sub">這週最重要的事</div>
      </div>
      <div class="cards-area" id="cards-week"></div>
      <div style="padding: 0 16px 16px;"><button class="add-card-btn" id="add-week" onclick="openModal('week')">+ 新增目標</button></div>
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
  </div><!-- board-section 结束 -->
  
  <!-- 侧边编辑栏（桌面版） -->
  <div class="editor-sidebar" id="editor-sidebar">
    <div class="editor-sidebar-header">
      <div class="editor-sidebar-title">編輯卡片</div>
      <button class="editor-sidebar-close" onclick="closeSidebar()">✕</button>
    </div>
    <div class="editor-sidebar-body" id="editor-sidebar-content">
      <!-- 编辑内容将通过 JavaScript 动态加载 -->
    </div>
  </div>
  
  </div><!-- kanban-container 结束 -->
  
  <!-- 手機版底部 Tab Bar -->
  <div class="mobile-tab-bar">
    <button class="mobile-tab" data-col="lib" onclick="switchMobileTab('lib')">
      <div class="mobile-tab-icon">📚</div>
      <div class="mobile-tab-label">策略</div>
    </button>
    <button class="mobile-tab" data-col="week" onclick="switchMobileTab('week')">
      <div class="mobile-tab-icon">📅</div>
      <div class="mobile-tab-label">本週</div>
    </button>
    <button class="mobile-tab active" data-col="focus" onclick="switchMobileTab('focus')">
      <div class="mobile-tab-icon">🎯</div>
      <div class="mobile-tab-label">今日</div>
    </button>
    <button class="mobile-tab" data-col="done" onclick="switchMobileTab('done')">
      <div class="mobile-tab-icon">✅</div>
      <div class="mobile-tab-label">完成</div>
    </button>
  </div>
  
</div>

<!-- 卡片編輯 Modal（手机版使用） -->
<div class="overlay" id="overlay" onclick="handleOverlayClick(event)">
  <div class="modal">
    <div class="modal-header">
      <button class="modal-back-btn" onclick="closeModalWithSave()" title="返回並儲存">←</button>
      <div class="modal-title" id="modal-title">新增卡片</div>
    </div>
    <div class="modal-body">
      <div class="field">
        <label class="field-label">標題 *</label>
        <input type="text" class="field-input" id="input-title" placeholder="輸入任務標題...">
      </div>
      <div class="field">
        <label class="field-label">專案類型 <span class="optional">(選填)</span> <a href="javascript:void(0)" onclick="openProjectSettings(); event.stopPropagation();" style="font-size:11px; color:var(--accent-week); text-decoration:underline; margin-left:8px;">⚙️ 管理專案</a></label>
        <select class="field-select" id="input-project"></select>
      </div>
      <div class="field">
        <label class="field-label">優先級（四象限） <span class="optional">(選填)</span></label>
        <input type="hidden" id="input-priority" value="">
        <div class="priority-group" id="input-priority-group">
          <button type="button" class="priority-btn" onclick="selectPriorityValue('input-priority','input-priority-group','urgent_important')">🔥 重要且緊急</button>
          <button type="button" class="priority-btn" onclick="selectPriorityValue('input-priority','input-priority-group','important_not_urgent')">⭐ 重要但不緊急</button>
          <button type="button" class="priority-btn" onclick="selectPriorityValue('input-priority','input-priority-group','urgent_not_important')">⚡ 緊急但不重要</button>
          <button type="button" class="priority-btn" onclick="selectPriorityValue('input-priority','input-priority-group','not_urgent_not_important')">💤 不重要不緊急</button>
        </div>
        <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">
          💡 第一象限：立即處理 | 第二象限：重點規劃 | 第三象限：授權處理 | 第四象限：盡量避免
        </div>
      </div>
      <div class="field">
        <label class="field-label">隱私設定</label>
        <div style="display: flex; gap: 12px; margin-top: 8px;">
          <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
            <input type="radio" name="privacy" id="privacy-shared" value="0" checked style="cursor: pointer;">
            <span style="font-size: 14px;">👥 共用（兩人都能看到）</span>
          </label>
          <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
            <input type="radio" name="privacy" id="privacy-private" value="1" style="cursor: pointer;">
            <span style="font-size: 14px;">🔒 私人（只有自己能看到）</span>
          </label>
        </div>
      </div>
      <div class="field">
        <label class="field-label">來源連結 <span class="optional">(選填)</span></label>
        <input type="url" class="field-input" id="input-source" placeholder="https://...">
      </div>
      <div class="field">
        <label class="field-label">一句摘要 <span class="optional">(選填)</span></label>
        <input type="text" class="field-input" id="input-summary" placeholder="用一句話說明這張卡的重點...">
      </div>
      <div class="field">
        <label class="field-label">下一步行動 <span class="optional">(選填)</span></label>
        <textarea class="field-textarea" id="input-nextstep" placeholder="明確的行動指令，例如：打電話給王先生確認規格"></textarea>
      </div>
      <div class="field">
        <label class="field-label">✓ 待辦清單 <span class="optional">(選填)</span></label>
        <div class="checklist-container" id="checklist-container">
          <!-- 待办项会动态添加到这里 -->
        </div>
        <button type="button" class="add-checklist-item-btn" data-checklist-container="checklist-container">+ 新增待辦項目</button>
      </div>
      <div class="field">
        <label class="field-label">詳細內容 <span class="optional">(點擊下方區域開啟 Word 編輯器)</span></label>
        <div class="word-preview" id="word-preview" onclick="openFullscreenEditor()">
          <span class="empty">點擊此處使用 Word 編輯器撰寫...</span>
        </div>
        <input type="hidden" id="input-body">
      </div>
      <div class="color-section"><label class="color-label">背景顏色</label><div class="swatches" id="bg-swatches"></div></div>
      <div class="color-section"><label class="color-label">文字顏色</label><div class="swatches" id="text-swatches"></div></div>
      <input type="hidden" id="input-col">
      <input type="hidden" id="input-edit-id">
      <input type="hidden" id="input-bgcolor">
      <input type="hidden" id="input-textcolor">
    </div>
    <div class="modal-footer">
      <button class="modal-btn secondary" onclick="closeModal()">取消</button>
      <button class="modal-btn primary" onclick="saveCard()">儲存</button>
    </div>
  </div>
</div>

<!-- Quill 全螢幕編輯器 -->
<div class="fullscreen-editor" id="fullscreen-editor">
  <div class="editor-header">
    <div class="editor-title">📝 詳細內容 (Word 編輯模式)</div>
    <button class="modal-btn secondary" onclick="closeFullscreenEditor()" style="background:#EEE; padding:8px 16px;">✕ 取消</button>
  </div>
  <div class="quill-container">
    <div id="editor-container"></div>
  </div>
  <div class="editor-footer">
    <button class="modal-btn secondary" onclick="closeFullscreenEditor()" style="padding:12px 24px;">取消</button>
    <button class="modal-btn primary" onclick="saveFullscreenContent()" style="padding:12px 32px; font-size:16px;">✓ 儲存內容並返回</button>
  </div>
</div>

<!-- 專案設定 Modal -->
<div class="overlay" id="project-settings-overlay" onclick="handleProjectSettingsClick(event)">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">⚙️ 專案類型管理</div>
    </div>
    <div class="modal-body">
      <div class="add-project-form">
        <input type="text" class="add-project-input" id="new-project-name" placeholder="專案名稱..." maxlength="20">
        <input type="color" class="color-input" id="new-project-color" value="#E3F2FD">
        <button class="add-project-btn" onclick="addCustomProject()">+ 新增</button>
      </div>
      <div class="project-list" id="project-list"></div>
    </div>
    <div class="modal-footer">
      <button class="modal-btn primary" onclick="closeProjectSettings()">完成</button>
    </div>
  </div>
</div>

<div id="toast"></div>
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

<!-- 更新日志弹窗 -->
<div class="overlay" id="changelog-overlay" onclick="if(event.target === this) closeChangelog()">
  <div class="modal" style="max-width: 700px; max-height: 80vh; overflow-y: auto;">
    <div class="modal-header">
      <div class="modal-title">📋 版本更新歷史</div>
      <button onclick="closeChangelog()" style="background: none; border: none; font-size: 24px; color: var(--text-muted); cursor: pointer;">✕</button>
    </div>
    <div class="modal-body" style="padding: 20px 24px;">
      
      <div class="changelog-version">
        <h3>v2.5.0 - 側邊編輯欄 <span class="version-date">2026-03-22</span></h3>
        <ul>
          <li>✅ 桌面版側邊編輯欄（Notion 風格）</li>
          <li>✅ 手機版保持全屏編輯</li>
          <li>✅ ESC 快捷鍵關閉側邊欄</li>
          <li>✅ 編輯中卡片高亮顯示</li>
        </ul>
      </div>

      <div class="changelog-version">
        <h3>v2.4.0 - 通知系統 <span class="version-date">2026-03-21 晚上</span></h3>
        <ul>
          <li>✅ 應用內通知功能</li>
          <li>✅ 提示音 + 靜音開關</li>
          <li>✅ 自動輪詢（每30秒）</li>
          <li>✅ 新增 notifications 資料表</li>
        </ul>
      </div>

      <div class="changelog-version">
        <h3>v2.3.0 - 待辦清單 <span class="version-date">2026-03-21 下午</span></h3>
        <ul>
          <li>✅ Google Keep 風格待辦清單</li>
          <li>✅ 卡片上直接勾選</li>
          <li>✅ 顯示完成進度</li>
          <li>✅ cards 表新增 checklist 欄位</li>
        </ul>
      </div>

      <div class="changelog-version">
        <h3>v2.2.0 - 隱私控制 <span class="version-date">2026-03-21 上午</span></h3>
        <ul>
          <li>✅ 私人/共用卡片功能</li>
          <li>✅ 隱私篩選按鈕</li>
          <li>✅ cards 表新增 is_private 欄位</li>
        </ul>
      </div>

      <div class="changelog-version">
        <h3>v2.1.0 - 多用戶協作 <span class="version-date">2026-03-21 凌晨</span></h3>
        <ul>
          <li>✅ 兩個帳號（chienyi + holly）</li>
          <li>✅ 卡片顯示創建者</li>
          <li>✅ cards 表新增 created_by 欄位</li>
        </ul>
      </div>

      <div class="changelog-version">
        <h3>v2.0.0 - 雲端數據庫 <span class="version-date">2026-03-21</span></h3>
        <ul>
          <li>✅ 從 localStorage 遷移到 MySQL</li>
          <li>✅ 部署到 Cloudways</li>
          <li>✅ RESTful API 架構</li>
          <li>✅ PWA 支援</li>
        </ul>
      </div>

      <div class="changelog-version">
        <h3>v1.5.0 - 富文本編輯器 <span class="version-date">2026-03-20</span></h3>
        <ul>
          <li>✅ Quill.js 整合</li>
          <li>✅ 圖片壓縮到 100KB</li>
          <li>✅ HTML 格式儲存</li>
        </ul>
      </div>

      <div class="changelog-version">
        <h3>v1.0.0 - 初始版本 <span class="version-date">2026-03-19</span></h3>
        <ul>
          <li>✅ 四欄看板布局</li>
          <li>✅ 卡片編輯功能</li>
          <li>✅ 本地存儲</li>
        </ul>
      </div>

      <div style="margin-top: 32px; padding-top: 20px; border-top: 1px solid var(--border); text-align: center; color: var(--text-muted); font-size: 13px;">
        <p>🚀 持續更新中...</p>
        <p style="margin-top: 8px;">下一版本計劃：自動保存 • 快捷鍵切換 • 可拖動分隔線</p>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<!-- Quill ImageResize 模組 -->
<script src="https://unpkg.com/quill-image-resize-module@3.0.0/image-resize.min.js"></script>

<script>
// ==========================================
// 狀態與基礎設定
// ==========================================
// 当前用户信息
const CURRENT_USERNAME = '<?php echo addslashes($username); ?>';

let state = { lib: [], week: [], focus: [], done: [] };
let privacyFilter = 'all'; // 隐私筛选：'all', 'shared', 'private'
let searchQuery = '';

// 預設專案類型（容錯備份）
const DEFAULT_PROJECTS = {
  finance: { label: '財務', bg: '#FFF9C4', color: '#F57F17', default: true },
  company: { label: '公司', bg: '#E3F2FD', color: '#1565C0', default: true },
  family: { label: '家庭', bg: '#FCE4EC', color: '#C2185B', default: true }
};

// 舊專案名稱顯示修正（不影響 project key）
const PROJECT_LABEL_MIGRATION = {
  '筆記': '商品頁'
};

// 專案資料（優先從資料庫載入，失敗則使用預設）
let ALL_PROJECTS = { ...DEFAULT_PROJECTS };
let PROJECT_LABELS = {};
let currentFilter = null, focusTimer = null, timerSeconds = 0, timerRunning = false;
let currentFocusLogId = null; // 当前专注记录ID
let currentFocusCardId = null; // 当前专注的卡片ID
let quill = null;
let isDBMode = false; // 追蹤是否成功使用資料庫
let currentEditingCard = null; // 当前正在编辑的卡片
let suppressSidebarAutosaveUntil = 0; // checklist 互動期間，暫停 blur 自動儲存

// ==========================================
// 侧边编辑栏相关函数
// ==========================================

// 检测是否为移动设备
function isMobile() {
  return window.innerWidth <= 768;
}

// 打开侧边编辑栏（桌面版）
function openSidebar(cardId, col) {
  if (isMobile()) {
    // 手机版继续使用全屏modal
    return false;
  }
  
  const card = state[col].find(c => c.id === cardId);
  if (!card) return false;
  
  currentEditingCard = { id: cardId, col: col };
  
  // 高亮当前卡片
  document.querySelectorAll('.card').forEach(c => c.classList.remove('editing'));
  const cardElement = document.querySelector(`.card[data-id="${cardId}"]`);
  if (cardElement) cardElement.classList.add('editing');
  
  // 填充编辑内容
  const sidebarContent = document.getElementById('editor-sidebar-content');
  sidebarContent.innerHTML = generateEditForm(card, cardId, col);
  
  // 显示侧边栏
  document.getElementById('editor-sidebar').classList.add('open');
  
  // 初始化表单
  setTimeout(() => {
    syncPriorityButtons('sidebar-priority-group', card.priority || '');
    renderChecklistEdit(card.checklist, 'sidebar-checklist-container');
    initSwatches(card.bgcolor || '', card.textcolor || '#1A1A18', 'sidebar');
    updateWordPreview(card.body || '');
    
    // 为侧边栏添加自动保存监听（Notion 方式）
    enableSidebarAutoSave();
  }, 50);
  
  return true;
}

// 关闭侧边编辑栏
async function closeSidebar() {
  // 关闭前先静默保存
  await silentSaveSidebar();
  
  document.getElementById('editor-sidebar').classList.remove('open');
  document.querySelectorAll('.card').forEach(c => c.classList.remove('editing'));
  currentEditingCard = null;
}

// 启用侧边栏自动保存监听（Notion 方式）
function enableSidebarAutoSave() {
  const inputs = ['sidebar-input-title', 'sidebar-input-project', 'sidebar-input-source', 'sidebar-input-summary', 'sidebar-input-nextstep'];
  
  inputs.forEach(id => {
    const element = document.getElementById(id);
    if (element) {
      // 失焦时立即保存
      element.addEventListener('blur', () => {
        silentSaveSidebar();
      });
    }
  });
  
  // select 选择后立即保存
  const projectSelect = document.getElementById('sidebar-input-project');
  if (projectSelect) {
    projectSelect.addEventListener('change', () => {
      silentSaveSidebar();
    });
  }
  
  // 隐私选项改变时立即保存
  const privacyInputs = document.querySelectorAll('input[name="sidebar-privacy"]');
  privacyInputs.forEach(input => {
    input.addEventListener('change', () => {
      silentSaveSidebar();
    });
  });
}

// 侧边栏静默保存
async function silentSaveSidebar() {
  if (!currentEditingCard) return;
  if (Date.now() < suppressSidebarAutosaveUntil) return;
  
  const title = document.getElementById('sidebar-input-title')?.value.trim();
  if (!title) return;
  
  try {
    const isPrivate = document.getElementById('sidebar-privacy-private')?.checked ? 1 : 0;
    const priority = document.getElementById('sidebar-input-priority')?.value || null;

    const data = {
      id: currentEditingCard.id,
      col: currentEditingCard.col,
      title: title,
      project: document.getElementById('sidebar-input-project')?.value || '',
      priority: priority,
      sourceLink: document.getElementById('sidebar-input-source')?.value.trim() || '',
      summary: document.getElementById('sidebar-input-summary')?.value.trim() || '',
      nextStep: document.getElementById('sidebar-input-nextstep')?.value.trim() || '',
      body: document.getElementById('sidebar-input-body')?.value.trim() || '',
      bgcolor: document.getElementById('sidebar-input-bgcolor')?.value || '',
      textcolor: document.getElementById('sidebar-input-textcolor')?.value || '',
      isPrivate: isPrivate,
      checklist: getChecklistData('sidebar-checklist-container')
    };
    
    // 静默保存：不显示 toast 提示
    const res = await fetch('api/cards.php?action=save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    
    const result = await res.json();
    if (result.success) {
      // 静默更新数据，不显示提示，不关闭侧边栏
      await loadCards();
    }
  } catch (err) {
    console.log('侧边栏自动保存失败:', err);
  }
}

// 生成编辑表单HTML
function generateEditForm(card, cardId, col) {
  return `
    <div class="field">
      <label class="field-label">標題 *</label>
      <input type="text" class="field-input" id="sidebar-input-title" value="${escHtml(card.title)}" placeholder="輸入任務標題...">
    </div>
    <div class="field">
      <label class="field-label">專案類型 <span class="optional">(選填)</span></label>
      <select class="field-select" id="sidebar-input-project">
        ${Object.keys(ALL_PROJECTS).map(key => `<option value="${key}" ${card.project === key ? 'selected' : ''}>${PROJECT_LABELS[key] || key}</option>`).join('')}
      </select>
    </div>
    <div class="field">
      <label class="field-label">優先級（四象限） <span class="optional">(選填)</span></label>
      <input type="hidden" id="sidebar-input-priority" value="${escHtml(card.priority || '')}">
      <div class="priority-group" id="sidebar-priority-group">
        <button type="button" class="priority-btn" onclick="selectPriorityValue('sidebar-input-priority','sidebar-priority-group','urgent_important')">🔥 重要且緊急</button>
        <button type="button" class="priority-btn" onclick="selectPriorityValue('sidebar-input-priority','sidebar-priority-group','important_not_urgent')">⭐ 重要但不緊急</button>
        <button type="button" class="priority-btn" onclick="selectPriorityValue('sidebar-input-priority','sidebar-priority-group','urgent_not_important')">⚡ 緊急但不重要</button>
        <button type="button" class="priority-btn" onclick="selectPriorityValue('sidebar-input-priority','sidebar-priority-group','not_urgent_not_important')">💤 不重要不緊急</button>
      </div>
    </div>
    <div class="field">
      <label class="field-label">隱私設定</label>
      <div style="display: flex; gap: 12px; margin-top: 8px;">
        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
          <input type="radio" name="sidebar-privacy" id="sidebar-privacy-shared" value="0" ${!card.isPrivate ? 'checked' : ''} style="cursor: pointer;">
          <span style="font-size: 14px;">👥 共用</span>
        </label>
        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
          <input type="radio" name="sidebar-privacy" id="sidebar-privacy-private" value="1" ${card.isPrivate ? 'checked' : ''} style="cursor: pointer;">
          <span style="font-size: 14px;">🔒 私人</span>
        </label>
      </div>
    </div>
    <div class="field">
      <label class="field-label">來源連結 <span class="optional">(選填)</span></label>
      <input type="url" class="field-input" id="sidebar-input-source" value="${escHtml(card.sourceLink || '')}" placeholder="https://...">
    </div>
    <div class="field">
      <label class="field-label">一句摘要 <span class="optional">(選填)</span></label>
      <input type="text" class="field-input" id="sidebar-input-summary" value="${escHtml(card.summary || '')}" placeholder="用一句話說明這張卡的重點...">
    </div>
    <div class="field">
      <label class="field-label">下一步行動 <span class="optional">(選填)</span></label>
      <textarea class="field-textarea" id="sidebar-input-nextstep" placeholder="明確的行動指令">${escHtml(card.nextStep || '')}</textarea>
    </div>
    <div class="field">
      <label class="field-label">✓ 待辦清單 <span class="optional">(選填)</span></label>
      <div class="checklist-container" id="sidebar-checklist-container"></div>
      <button type="button" class="add-checklist-item-btn" data-checklist-container="sidebar-checklist-container">+ 新增待辦項目</button>
    </div>
    <div class="field">
      <label class="field-label">詳細內容 <span class="optional">(點擊下方區域開啟編輯器)</span></label>
      <div class="word-preview" id="sidebar-word-preview" onclick="openFullscreenEditor()">
        <span class="empty">點擊此處使用 Word 編輯器撰寫...</span>
      </div>
      <input type="hidden" id="sidebar-input-body" value="${escHtml(card.body || '')}">
    </div>
    <div class="color-section">
      <label class="color-label">背景顏色</label>
      <div class="swatches" id="sidebar-bg-swatches"></div>
    </div>
    <div class="color-section">
      <label class="color-label">文字顏色</label>
      <div class="swatches" id="sidebar-text-swatches"></div>
    </div>
    <input type="hidden" id="sidebar-input-bgcolor" value="${card.bgcolor || ''}">
    <input type="hidden" id="sidebar-input-textcolor" value="${card.textcolor || ''}">
    <input type="hidden" id="sidebar-input-col" value="${col}">
    <input type="hidden" id="sidebar-input-edit-id" value="${cardId}">
    
    <div style="display: flex; gap: 8px; margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border);">
      <button class="modal-btn primary" onclick="saveSidebarCard()" style="flex: 1;">儲存</button>
      <button class="modal-btn secondary" onclick="closeSidebar()">取消</button>
    </div>
  `;
}

// 更新Word预览
function updateWordPreview(content) {
  const preview = document.getElementById('sidebar-word-preview');
  if (!preview) return;
  
  if (content && content.trim()) {
    preview.innerHTML = content;
    preview.classList.remove('empty');
  } else {
    preview.innerHTML = '<span class="empty">點擊此處使用 Word 編輯器撰寫...</span>';
    preview.classList.add('empty');
  }
}

// 保存侧边栏卡片
async function saveSidebarCard() {
  const title = document.getElementById('sidebar-input-title').value.trim();
  if (!title) {
    document.getElementById('sidebar-input-title').focus();
    return;
  }
  
  const isPrivate = document.getElementById('sidebar-privacy-private').checked ? 1 : 0;
  const checklist = getChecklistData('sidebar-checklist-container');
  const priority = document.getElementById('sidebar-input-priority')?.value || null; // 获取优先级
  
  const data = {
    id: document.getElementById('sidebar-input-edit-id').value,
    col: document.getElementById('sidebar-input-col').value,
    title: title,
    project: document.getElementById('sidebar-input-project').value,
    priority: priority, // 添加优先级
    sourceLink: document.getElementById('sidebar-input-source').value.trim(),
    summary: document.getElementById('sidebar-input-summary').value.trim(),
    nextStep: document.getElementById('sidebar-input-nextstep').value.trim(),
    body: document.getElementById('sidebar-input-body').value.trim(),
    bgcolor: document.getElementById('sidebar-input-bgcolor').value,
    textcolor: document.getElementById('sidebar-input-textcolor').value,
    isPrivate: isPrivate,
    checklist: checklist
  };
  
  await saveCardToAPI(data);
  closeSidebar();
}

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
    let projectsArray;

    if (data.error) {
      console.warn('API 錯誤:', data.error);
      return null;
    }

    if (Array.isArray(data)) {
      projectsArray = data;
    } else if (data.data && Array.isArray(data.data)) {
      projectsArray = data.data;
    } else {
      console.warn('API 回應格式錯誤:', data);
      return null;
    }

    const allowedDefaultKeys = ['finance', 'company', 'family'];
    const projects = { ...DEFAULT_PROJECTS };

    projectsArray.forEach(proj => {
      const normalized = {
        id: proj.id,
        label: proj.label,
        bg: proj.bgColor,
        color: proj.textColor,
        default: proj.isDefault
      };

      if (proj.isDefault) {
        if (allowedDefaultKeys.includes(proj.key)) {
          projects[proj.key] = { ...projects[proj.key], ...normalized };
        }
        return;
      }

      projects[proj.key] = normalized;
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
    if (!saved) return {};

    const parsed = JSON.parse(saved);
    const customProjects = {};

    Object.keys(parsed || {}).forEach(key => {
      if (!parsed[key]?.default) {
        customProjects[key] = parsed[key];
      }
    });

    return customProjects;
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

function normalizeProjectLabel(label) {
  return PROJECT_LABEL_MIGRATION[label] || label;
}

// 更新專案標籤
function updateProjectLabels() {
  PROJECT_LABELS = {};
  Object.keys(ALL_PROJECTS).forEach(key => {
    PROJECT_LABELS[key] = normalizeProjectLabel(ALL_PROJECTS[key].label);
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
  loadCards();
  
  // 初始化手機版 Tab（預設顯示「今日專注」）
  initMobileTabs();
  
  // 窗口大小改變時重新初始化
  window.addEventListener('resize', () => {
    if (window.innerWidth <= 768) {
      initMobileTabs();
    } else {
      // 桌面版：移除所有 active class（讓 CSS 正常工作）
      document.querySelectorAll('.col').forEach(col => {
        col.classList.remove('active');
      });
    }
  });
});

// ==========================================
// Quill 全螢幕編輯器
// ==========================================
function openFullscreenEditor() {
  // 检测是在侧边栏还是modal中
  const sidebarOpen = document.getElementById('editor-sidebar').classList.contains('open');
  const inputId = sidebarOpen ? 'sidebar-input-body' : 'input-body';
  
  const content = document.getElementById(inputId).value;
  quill.root.innerHTML = content || '';
  document.getElementById('fullscreen-editor').classList.add('open');
  quill.focus();
}

function closeFullscreenEditor() {
  document.getElementById('fullscreen-editor').classList.remove('open');
}

function saveFullscreenContent() {
  const html = quill.root.innerHTML;
  
  // 检测是在侧边栏还是modal中
  const sidebarOpen = document.getElementById('editor-sidebar').classList.contains('open');
  const inputId = sidebarOpen ? 'sidebar-input-body' : 'input-body';
  const previewId = sidebarOpen ? 'sidebar-word-preview' : 'word-preview';
  
  document.getElementById(inputId).value = html;
  
  // 更新預覽
  const preview = document.getElementById(previewId);
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

// ==========================================
// 更新日志功能
// ==========================================
function openChangelog() {
  document.getElementById('changelog-overlay').classList.add('open');
}

function closeChangelog() {
  document.getElementById('changelog-overlay').classList.remove('open');
}

function handleProjectSettingsClick(e) {
  if (e.target.id === 'project-settings-overlay') closeProjectSettings();
}

function renderProjectList() {
  const allProjects = getAllProjects();
  const list = document.getElementById('project-list');
  list.innerHTML = '';
  
  Object.keys(allProjects).forEach(key => {
    const proj = allProjects[key];
    const item = document.createElement('div');
    item.className = 'project-item';
    item.innerHTML = `
      <div class="project-info">
        <div class="project-color-preview" style="background: ${proj.bg}; color: ${proj.color};">●</div>
        <div>
          <div class="project-name">${normalizeProjectLabel(proj.label)}</div>
          ${proj.default ? '<div class="project-default">預設類型</div>' : ''}
        </div>
      </div>
      ${!proj.default ? `<button class="project-delete" onclick="deleteCustomProject('${key}')">刪除</button>` : ''}
    `;
    list.appendChild(item);
  });
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
  const select = document.getElementById('input-project');
  const allProjects = getAllProjects();
  
  select.innerHTML = '<option value="">無</option>';
  Object.keys(allProjects).forEach(key => {
    const option = document.createElement('option');
    option.value = key;
    option.textContent = normalizeProjectLabel(allProjects[key].label);
    select.appendChild(option);
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
    const res = await fetch('api/cards.php?action=list');
    const data = await res.json();
    if (data.error) { toast(data.error); return; }
    state = { lib: data.lib || [], week: data.week || [], focus: data.focus || [], done: data.done || [] };
    
    // ⭐ 重要：在渲染前更新專案標籤
    updateProjectLabels();
    
    render();
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
      await loadCards(); closeModal();
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
  // 查找卡片信息
  let card = null;
  for (const col of ['lib', 'week', 'focus', 'done']) {
    card = state[col].find(c => c.id === id);
    if (card) break;
  }
  
  // 检查是否是自己创建的卡片
  let confirmMessage = '確定要刪除這張卡片嗎？此動作無法復原。';
  if (card && card.createdByUsername && card.createdByUsername !== CURRENT_USERNAME) {
    confirmMessage = `⚠️ 注意：這張卡片是 ${card.createdByUsername} 創建的！\n\n確定要刪除嗎？此動作無法復原。`;
  }
  
  if (!confirm(confirmMessage)) return;
  
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

function render() {
  // ⭐ 雙重保險：確保專案標籤是最新的
  updateProjectLabels();
  injectProjectStyles();
  
  ['lib', 'week', 'focus', 'done'].forEach(col => {
    const area = document.getElementById('cards-' + col); area.innerHTML = '';
    const visibleCards = state[col].filter(shouldShowCard);
    
    if (visibleCards.length === 0) {
      const empty = document.createElement('div'); empty.className = 'empty';
      if (searchQuery || currentFilter) empty.textContent = '沒有符合的卡片';
      else empty.textContent = { lib: '把筆記、策略存在這裡', week: '設定這週最重要的事', focus: '選一件事，現在就去做', done: '完成的事情會出現在這' }[col];
      area.appendChild(empty);
    }
    visibleCards.forEach(card => area.appendChild(buildCard(card, col)));
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
  const container = document.getElementById('projectFilterContainer');
  container.innerHTML = '';

  const fixedOrder = [
    { key: null, label: '全部' },
    { key: 'company', label: '公司' },
    { key: 'finance', label: '財務' },
    { key: 'family', label: '家庭' }
  ];

  fixedOrder.forEach(item => {
    const tag = document.createElement('div');
    tag.className = 'filter-tag' + (((item.key === null && !currentFilter) || currentFilter === item.key) ? ' active' : '');
    tag.textContent = item.label;
    tag.onclick = () => { currentFilter = item.key; render(); };
    container.appendChild(tag);
  });

  const customKeys = Object.keys(ALL_PROJECTS).filter(key => !['company', 'finance', 'family'].includes(key));
  if (customKeys.length) {
    const divider = document.createElement('div');
    divider.className = 'filter-divider';
    container.appendChild(divider);

    customKeys.forEach(key => {
      const tag = document.createElement('div');
      tag.className = 'filter-tag custom' + (currentFilter === key ? ' active' : '');
      tag.textContent = PROJECT_LABELS[key] || key;
      tag.onclick = () => { currentFilter = key; render(); };
      container.appendChild(tag);
    });
  }
}

// 手機版 Tab 切換功能
function switchMobileTab(colName) {
  // 移除所有 active 狀態
  document.querySelectorAll('.mobile-tab').forEach(tab => {
    tab.classList.remove('active');
  });
  document.querySelectorAll('.col').forEach(col => {
    col.classList.remove('active');
  });
  
  // 添加選中的 active 狀態
  const selectedTab = document.querySelector(`.mobile-tab[data-col="${colName}"]`);
  const selectedCol = document.querySelector(`.col[data-col="${colName}"]`);
  
  if (selectedTab) selectedTab.classList.add('active');
  if (selectedCol) selectedCol.classList.add('active');
  
  // 滾動到頂部
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// 頁面載入時初始化手機版 Tab（預設顯示「今日專注」）
function initMobileTabs() {
  if (window.innerWidth <= 768) {
    // 先隱藏所有欄位
    document.querySelectorAll('.col').forEach(col => {
      col.classList.remove('active');
    });
    
    // 顯示「今日專注」
    const focusCol = document.querySelector('.col[data-col="focus"]');
    if (focusCol) focusCol.classList.add('active');
  }
}

function buildCard(card, col) {
  const div = document.createElement('div');
  div.className = 'card'; div.id = 'card-' + card.id; div.draggable = true; div.dataset.cardId = card.id; div.dataset.col = col;
  if (card.bgcolor) div.style.background = card.bgcolor; if (card.textcolor) div.style.color = card.textcolor;

  const hasBody = (card.body && card.body.trim()) || (card.nextStep && card.nextStep.trim());
  let metaHTML = '';
  if (card.project || card.createdByUsername || card.isPrivate || (col === 'done' && card.completedAt) || card.priority || card.postponedCount) {
    metaHTML = '<div class="card-meta">';
    if (card.project) metaHTML += `<span class="project-tag ${card.project}">${PROJECT_LABELS[card.project] || card.project}</span>`;
    
    // 优先级标记（四象限）
    if (card.priority) {
      const priorityLabels = {
        'urgent_important': '🔥 重要緊急',
        'important_not_urgent': '⭐ 重要不緊急',
        'urgent_not_important': '⚡ 緊急不重要',
        'not_urgent_not_important': '💤 不重要不緊急'
      };
      const priorityColors = {
        'urgent_important': '#FF4444',
        'important_not_urgent': '#FF9800',
        'urgent_not_important': '#2196F3',
        'not_urgent_not_important': '#9E9E9E'
      };
      const label = priorityLabels[card.priority] || card.priority;
      const color = priorityColors[card.priority] || '#666';
      metaHTML += `<span class="priority-tag" style="background: ${color}20; color: ${color}; border: 1px solid ${color}40;">${label}</span>`;
    }
    
    // 延期次数
    if (card.postponedCount && card.postponedCount > 0) {
      metaHTML += `<span class="postponed-tag">🔄 已延期 ${card.postponedCount}次</span>`;
    }
    
    if (card.createdByUsername) metaHTML += `<span class="created-by">by ${card.createdByUsername}</span>`;
    // 只在私人卡片显示标记
    if (card.isPrivate) metaHTML += `<span class="privacy-tag private">🔒 私人</span>`;
    if (col === 'done' && card.completedAt) metaHTML += `<span class="completed-time">✓ ${formatDateTime(card.completedAt)}</span>`;
    metaHTML += '</div>';
  }

  let sourceHTML = card.sourceLink ? `<a href="${escHtml(card.sourceLink)}" target="_blank" class="source-link" onclick="event.stopPropagation()">🔗 來源連結</a>` : '';
  let sumHTML = card.summary ? `<div class="card-summary">${escHtml(card.summary)}</div>` : '';
  let nsHTML = card.nextStep ? `<div class="card-next-step"><strong>下一步：</strong>${escHtml(card.nextStep)}</div>` : '';
  
  // 待办清单显示
  let checklistHTML = '';
  const checklistItems = normalizeChecklistItems(card.checklist);
  if (checklistItems.length > 0) {
    const completed = checklistItems.filter(item => item.checked).length;
    const total = checklistItems.length;
    checklistHTML = '<div class="card-checklist">';
    checklistHTML += `<div class="card-checklist-header">✓ 待辦清單 <span class="card-checklist-progress">${completed}/${total}</span></div>`;
    checklistItems.forEach((item, idx) => {
      checklistHTML += `<div class="card-checklist-item${item.checked ? ' checked' : ''}">`;
      checklistHTML += `<input type="checkbox" class="card-checklist-toggle" data-card-id="${card.id}" data-item-index="${idx}" data-col="${col}" ${item.checked ? 'checked' : ''}>`;
      checklistHTML += `<label>${escHtml(item.text)}</label>`;
      checklistHTML += '</div>';
    });
    checklistHTML += '</div>';
  }
  
  let timerHTML = '';
  if (col === 'focus') {
    timerHTML = `<div class="focus-timer"><div class="timer-display" id="timer-display-${card.id}">00:00:00</div><div class="timer-controls"><button class="timer-btn" id="timer-btn-${card.id}" onclick="toggleTimer(${card.id});event.stopPropagation()">開始專注</button><button class="timer-btn secondary" onclick="resetTimer(${card.id});event.stopPropagation()">重置</button></div></div>`;
  }

  let actsHTML = '<div class="card-actions">';
  if (col !== 'done') {
    if (col === 'lib' && state.week.length < 3) actsHTML += `<button class="act-btn postpone" onclick="moveAPI(${card.id},'week');event.stopPropagation()">→ 本週目標</button>`;
    if (col !== 'focus' && state.focus.length < 1) actsHTML += `<button class="act-btn focus" onclick="moveAPI(${card.id},'focus');event.stopPropagation()">→ 今日專注</button>`;
    if (col === 'week' || col === 'focus') actsHTML += `<button class="act-btn back" onclick="moveAPI(${card.id},'lib');event.stopPropagation()">↩ 退回策略庫</button>`;
    if (col === 'week') actsHTML += `<button class="act-btn postpone" onclick="postponeCard(${card.id},'${col}');event.stopPropagation()">⏭ 延到下週</button>`;
    actsHTML += `<button class="act-btn done" onclick="moveAPI(${card.id},'done');event.stopPropagation()">✓ 完成</button> <button class="act-btn" onclick="editCard(${card.id},'${col}');event.stopPropagation()">編輯</button> <button class="act-btn del" onclick="deleteAPI(${card.id});event.stopPropagation()">刪除</button></div>`;
  } else {
    actsHTML += `<button class="act-btn del" onclick="deleteAPI(${card.id});event.stopPropagation()">移除</button></div>`;
  }

  // 支援 HTML 內容顯示（保留格式）
  let bodyHTML = '';
  if (card.body && card.body.trim()) {
    // 預覽區域也顯示 HTML，但限制高度
    bodyHTML = `<div class="card-preview">${card.body}</div><div class="card-body">${card.body}</div>`;
  }

  div.innerHTML = `<div class="card-top"><span class="drag-handle">⋮⋮</span><div class="card-title">${col === 'done' ? '✓ ' : ''}${escHtml(card.title)}</div>${hasBody ? '<div class="chevron">▾</div>' : ''}</div>${metaHTML}${sourceHTML}${sumHTML}${nsHTML}${checklistHTML}${timerHTML}${bodyHTML}${actsHTML}`;
  
  if (hasBody) div.onclick = () => div.classList.toggle('open');
  div.addEventListener('dragstart', handleDragStart); div.addEventListener('dragend', handleDragEnd);
  return div;
}

function escHtml(str) { return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function formatDateTime(ts) {
  const d = new Date(ts.replace ? ts.replace(' ', 'T') : ts); if(isNaN(d)) return ts;
  return `${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

// ==========================================
// 拖曳處理
// ==========================================
let draggedCard = null;
function handleDragStart(e) { draggedCard = { id: parseInt(this.dataset.cardId), fromCol: this.dataset.col }; this.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; }
function handleDragEnd(e) { this.classList.remove('dragging'); document.querySelectorAll('.col').forEach(c => c.classList.remove('drag-over')); }
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.cards-area').forEach(a => { a.addEventListener('dragover', handleDragOver); a.addEventListener('drop', handleDrop); a.addEventListener('dragleave', handleDragLeave); });
});
function handleDragOver(e) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; this.closest('.col').classList.add('drag-over'); }
function handleDragLeave(e) { if (e.target === this) this.closest('.col').classList.remove('drag-over'); }
function handleDrop(e) {
  e.preventDefault(); if (!draggedCard) return;
  const toCol = this.closest('.col').dataset.col;
  if (toCol === 'focus' && state.focus.length >= 1 && draggedCard.fromCol !== 'focus') { toast('今日專注只能 1 張！'); this.closest('.col').classList.remove('drag-over'); return; }
  if (toCol === 'week' && state.week.length >= 3 && draggedCard.fromCol !== 'week') { toast('本週目標已滿 3 張！'); this.closest('.col').classList.remove('drag-over'); return; }
  if (draggedCard.fromCol !== toCol) moveAPI(draggedCard.id, toCol);
  this.closest('.col').classList.remove('drag-over'); draggedCard = null;
}
async function postponeCard(id, currentCol) { 
  if (currentCol !== 'week') {
    toast('只有本週目標可以延期');
    return;
  }
  
  // 检查本周目标数量
  if (state.week.length <= 3) {
    if (!confirm('本週目標只有 ' + state.week.length + ' 個，確定要延期嗎？')) return;
  }
  
  try {
    // 移动到策略库并增加延期次数
    const res = await fetch('api/cards.php?action=postpone', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: id })
    });
    const data = await res.json();
    if (data.success) { 
      toast('⏭ 已延到下週（移回策略庫）'); 
      await loadCards(); 
    } else {
      toast('❌ ' + (data.error || '延期失敗'));
    }
  } catch (err) { 
    toast('連線錯誤'); 
  }
}
async function clearDone() {
  if (!confirm('確定永久清空已完成區塊嗎？')) return;
  toast('清空中...'); for (const c of state.done) await fetch(`api/cards.php?action=delete&id=${c.id}`);
  toast('已清除完成區'); await loadCards();
}

// ==========================================
// 表單與 UI
// ==========================================
function openModal(col) {
  if (col === 'focus' && state.focus.length >= 1) { toast('今日專注只能 1 張！'); return; }
  if (col === 'week' && state.week.length >= 3) { toast('本週目標已滿 3 張！'); return; }
  document.getElementById('input-col').value = col; document.getElementById('input-edit-id').value = '';
  document.getElementById('input-title').value = ''; document.getElementById('input-project').value = ''; document.getElementById('input-priority').value = ''; document.getElementById('input-source').value = ''; document.getElementById('input-summary').value = ''; document.getElementById('input-nextstep').value = ''; document.getElementById('input-body').value = '';
  document.getElementById('checklist-container').innerHTML = '';
  document.getElementById('privacy-shared').checked = true;
  updateWordPreview('');
  initSwatches('', '#1A1A18');
  syncPriorityButtons('input-priority-group', '');
  document.getElementById('input-textcolor').value = '#1A1A18';
  document.getElementById('modal-title').textContent = { lib: '新增策略筆記', week: '新增本週目標', focus: '設定今日專注' }[col];
  document.getElementById('overlay').classList.add('open'); setTimeout(() => document.getElementById('input-title').focus(), 60);
}

function editCard(id, col) {
  if (openSidebar(id, col)) {
    return;
  }
  const card = state[col].find(c => c.id === id); if (!card) return;
  document.getElementById('input-col').value = col; document.getElementById('input-edit-id').value = id;
  document.getElementById('input-title').value = card.title; document.getElementById('input-project').value = card.project || ''; document.getElementById('input-source').value = card.sourceLink || ''; document.getElementById('input-summary').value = card.summary || ''; document.getElementById('input-nextstep').value = card.nextStep || ''; document.getElementById('input-body').value = card.body || '';
  if (card.isPrivate) {
    document.getElementById('privacy-private').checked = true;
  } else {
    document.getElementById('privacy-shared').checked = true;
  }
  document.getElementById('input-priority').value = card.priority || '';
  syncPriorityButtons('input-priority-group', card.priority || '');
  renderChecklistEdit(card.checklist, 'checklist-container');
  const preview = document.getElementById('word-preview');
  if (card.body && card.body.trim()) {
    preview.textContent = card.body;
    preview.classList.remove('empty');
  } else {
    preview.textContent = '點擊此處使用 Word 編輯器撰寫...';
    preview.classList.add('empty');
  }
  initSwatches(card.bgcolor || '', card.textcolor || '#1A1A18'); document.getElementById('modal-title').textContent = '編輯卡片';
  document.getElementById('overlay').classList.add('open');
  setTimeout(() => document.getElementById('input-title').focus(), 60);
}

async function saveCard() {
  const t = document.getElementById('input-title').value.trim(); if (!t) { document.getElementById('input-title').focus(); return; }
  const isPrivate = document.getElementById('privacy-private').checked ? 1 : 0;
  const checklist = getChecklistData('checklist-container'); // 获取待办清单数据
  const priority = document.getElementById('input-priority')?.value || null; // 获取优先级
  const data = { 
    col: document.getElementById('input-col').value, 
    title: t, 
    project: document.getElementById('input-project').value, 
    priority: priority, // 添加优先级
    sourceLink: document.getElementById('input-source').value.trim(), 
    summary: document.getElementById('input-summary').value.trim(), 
    nextStep: document.getElementById('input-nextstep').value.trim(), 
    body: document.getElementById('input-body').value.trim(), 
    bgcolor: document.getElementById('input-bgcolor').value, 
    textcolor: document.getElementById('input-textcolor').value, 
    isPrivate: isPrivate, 
    checklist: checklist 
  };
  const eid = document.getElementById('input-edit-id').value; if (eid) data.id = eid;
  const btn = document.querySelector('.modal-btn.primary'); btn.disabled = true; btn.textContent = '儲存中...';
  await saveCardToAPI(data);
  btn.disabled = false; btn.textContent = '儲存';
}

// 自动保存相关
let autoSaveTimer = null;
let autoSaveEnabled = false;
let currentEditingCardId = null;

// 启用自动保存监听（Notion 方式）
function enableAutoSave() {
  if (autoSaveEnabled) return;
  autoSaveEnabled = true;
  
  // 保存当前编辑的卡片 ID
  currentEditingCardId = document.getElementById('input-edit-id').value;
  
  // 监听所有输入字段的失焦事件（立即保存）
  const inputs = ['input-title', 'input-project', 'input-source', 'input-summary', 'input-nextstep'];
  inputs.forEach(id => {
    const element = document.getElementById(id);
    if (element) {
      // 失焦时立即保存
      element.addEventListener('blur', () => {
        silentAutoSave();
      });
      
      // 输入时 3 秒防抖保存（备份机制）
      element.addEventListener('input', triggerAutoSave);
    }
  });
  
  // 特殊处理：select 选择后立即保存
  const projectSelect = document.getElementById('input-project');
  if (projectSelect) {
    projectSelect.addEventListener('change', () => {
      silentAutoSave();
    });
  }
  
  // 隐私选项改变时立即保存
  const privacyInputs = document.querySelectorAll('input[name="privacy"]');
  privacyInputs.forEach(input => {
    input.addEventListener('change', () => {
      silentAutoSave();
    });
  });
}

// 触发自动保存（3秒延迟 - 备份机制）
function triggerAutoSave() {
  if (autoSaveTimer) clearTimeout(autoSaveTimer);
  autoSaveTimer = setTimeout(() => {
    silentAutoSave();
  }, 3000);
}

// 静默自动保存（不显示提示）
async function silentAutoSave() {
  const editId = document.getElementById('input-edit-id').value;
  if (!editId) return; // 如果是新增卡片，不自动保存
  
  const title = document.getElementById('input-title').value.trim();
  if (!title) return; // 标题为空，不保存
  
  try {
    const isPrivate = document.getElementById('privacy-private').checked ? 1 : 0;
    const priority = document.getElementById('input-priority')?.value || null;

    const data = {
      id: editId,
      col: document.getElementById('input-col').value,
      title: title,
      project: document.getElementById('input-project').value,
      priority: priority,
      sourceLink: document.getElementById('input-source').value.trim(),
      summary: document.getElementById('input-summary').value.trim(),
      nextStep: document.getElementById('input-nextstep').value.trim(),
      body: document.getElementById('input-body').value.trim(),
      bgcolor: document.getElementById('input-bgcolor').value,
      textcolor: document.getElementById('input-textcolor').value,
      isPrivate: isPrivate,
      checklist: getChecklistData('checklist-container')
    };
    
    // 静默保存：不显示 toast 提示
    const res = await fetch('api/cards.php?action=save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    
    const result = await res.json();
    if (result.success) {
      // 静默更新数据，不显示提示
      await loadCards();
    }
  } catch (err) {
    // 静默失败，不显示错误
    console.log('自动保存失败:', err);
  }
}

// 关闭modal并保存（手机版返回按钮）
async function closeModalWithSave() {
  const editId = document.getElementById('input-edit-id').value;
  
  if (editId) {
    // 编辑模式：静默保存后关闭
    await silentAutoSave();
    closeModal();
  } else {
    // 新增模式：直接关闭
    closeModal();
  }
}

function closeModal() { document.getElementById('overlay').classList.remove('open'); autoSaveEnabled = false; if (autoSaveTimer) clearTimeout(autoSaveTimer); }
function handleOverlayClick(e) { if (e.target === document.getElementById('overlay')) closeModal(); }
document.addEventListener('keydown', e => { 
  if (e.key === 'Escape' && !document.getElementById('fullscreen-editor').classList.contains('open') && !document.getElementById('project-settings-overlay').classList.contains('open')) closeModal(); 
});
document.getElementById('input-title').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); document.getElementById('input-summary').focus(); } });
document.getElementById('searchInput').addEventListener('input', e => { searchQuery = e.target.value.trim(); render(); });

function setPrivacyFilter(filter) {
  privacyFilter = filter;
  const privacySelect = document.getElementById('privacyFilter');
  if (privacySelect && privacySelect.value !== filter) privacySelect.value = filter;
  render();
}

document.getElementById('privacyFilter').addEventListener('change', e => {
  setPrivacyFilter(e.target.value);
});

function toggleHelp() { document.getElementById('sidebar').classList.toggle('open'); }
function toggleExportMenu() { document.getElementById('export-dropdown').classList.toggle('open'); }
function toggleHelpMenu() { document.getElementById('help-dropdown').classList.toggle('open'); }

// 手機版「更多」菜單控制
function toggleMobileMoreMenu() {
  const dropdown = document.getElementById('mobile-more-dropdown');
  dropdown.classList.toggle('show');
}

function closeMobileMoreMenu() {
  const dropdown = document.getElementById('mobile-more-dropdown');
  dropdown.classList.remove('show');
}

// 點擊外部關閉「更多」菜單
document.addEventListener('click', function(e) {
  const moreMenu = document.querySelector('.mobile-more-menu');
  const dropdown = document.getElementById('mobile-more-dropdown');
  if (moreMenu && dropdown && !moreMenu.contains(e.target)) {
    dropdown.classList.remove('show');
  }
});
document.addEventListener('click', e => { if (!e.target.closest('.export-btn') && !e.target.closest('.export-dropdown')) document.getElementById('export-dropdown').classList.remove('open'); });
document.addEventListener('click', e => { if (!e.target.closest('.help-toggle') && !e.target.closest('.help-dropdown')) document.getElementById('help-dropdown').classList.remove('open'); });

// 色票
const BG_COLORS = [{val:'',label:'預設'},{val:'#FFFFFF',label:'白'},{val:'#FFF9C4',label:'黃'},{val:'#FAECE7',label:'橘'},{val:'#EEEDFE',label:'紫'},{val:'#E1F5EE',label:'綠'},{val:'#E6F1FB',label:'藍'},{val:'#FBEAF0',label:'粉'},{val:'#EFEDE8',label:'米'},{val:'#2C2C2A',label:'黑'}];
const TEXT_COLORS = [{val:'#E24B4A',label:'紅'},{val:'#185FA5',label:'藍'},{val:'#1A1A18',label:'黑'}];
function initSwatches(curBg, curText, prefix = '') {
  const bgElId = prefix ? `${prefix}-bg-swatches` : 'bg-swatches';
  const textElId = prefix ? `${prefix}-text-swatches` : 'text-swatches';
  const bgInputId = prefix ? `${prefix}-input-bgcolor` : 'input-bgcolor';
  const textInputId = prefix ? `${prefix}-input-textcolor` : 'input-textcolor';
  
  const bgEl = document.getElementById(bgElId);
  const textEl = document.getElementById(textElId);
  
  if (!bgEl || !textEl) return; // 如果元素不存在，直接返回
  
  bgEl.innerHTML = ''; textEl.innerHTML = '';
  BG_COLORS.forEach(({val,label}) => { const s = document.createElement('div'); s.className = 'swatch'+(val===curBg?' selected':''); s.title=label; s.style.background=val||'#F5F3EE'; if(!val) s.style.border='2px dashed #ccc'; s.onclick=()=>{ bgEl.querySelectorAll('.swatch').forEach(x=>x.classList.remove('selected')); s.classList.add('selected'); document.getElementById(bgInputId).value=val; }; bgEl.appendChild(s); });
  const selectedText = curText || '#1A1A18';
  TEXT_COLORS.forEach(({val,label}) => { const s = document.createElement('div'); s.className = 'swatch'+(val===selectedText?' selected':''); s.title=label; s.style.background=val; s.onclick=()=>{ textEl.querySelectorAll('.swatch').forEach(x=>x.classList.remove('selected')); s.classList.add('selected'); document.getElementById(textInputId).value=val; }; textEl.appendChild(s); });
  document.getElementById(textInputId).value = selectedText;
}

// ==========================================
// 專注計時與匯出
// ==========================================
function toggleTimer(id) { timerRunning ? pauseTimer(id) : startTimer(id); }

async function startTimer(id) { 
  timerRunning = true; 
  currentFocusCardId = id;
  document.getElementById(`timer-btn-${id}`).textContent = '暫停'; 
  
  // 添加閃爍效果到今日專注區
  const focusCol = document.querySelector('.col-focus');
  if (focusCol) focusCol.classList.add('timer-active');
  
  // 保存开始时间到数据库
  const card = state.focus.find(c => c.id === id);
  if (card && !currentFocusLogId) {
    try {
      const res = await fetch('api/focus-logs.php?action=start', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          card_id: id,
          task_title: card.title,
          task_project: card.project || ''
        })
      });
      const data = await res.json();
      if (data.success) {
        currentFocusLogId = data.log_id;
      }
    } catch (err) {
      console.error('保存专注记录失败:', err);
    }
  }
  
  focusTimer = setInterval(() => { timerSeconds++; updateTimerDisplay(id); }, 1000); 
}

async function pauseTimer(id) { 
  timerRunning = false; 
  document.getElementById(`timer-btn-${id}`).textContent = '繼續'; 
  clearInterval(focusTimer); 
  
  // 移除閃爍效果
  const focusCol = document.querySelector('.col-focus');
  if (focusCol) focusCol.classList.remove('timer-active');
  
  // 暂停时保存时长
  if (currentFocusLogId && timerSeconds > 0) {
    await saveTimerDuration();
  }
}

async function resetTimer(id) { 
  timerRunning = false; 
  timerSeconds = 0; 
  clearInterval(focusTimer); 
  updateTimerDisplay(id); 
  document.getElementById(`timer-btn-${id}`).textContent = '開始專注'; 
  
  // 移除閃爍效果
  const focusCol = document.querySelector('.col-focus');
  if (focusCol) focusCol.classList.remove('timer-active');
  
  // 重置时保存并清除记录ID
  if (currentFocusLogId) {
    await saveTimerDuration();
    currentFocusLogId = null;
    currentFocusCardId = null;
  }
}

async function saveTimerDuration() {
  if (!currentFocusLogId || timerSeconds === 0) return;
  
  try {
    await fetch('api/focus-logs.php?action=stop', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        log_id: currentFocusLogId,
        duration_seconds: timerSeconds
      })
    });
  } catch (err) {
    console.error('保存时长失败:', err);
  }
}

function updateTimerDisplay(id) { const el = document.getElementById(`timer-display-${id}`); if(el) el.textContent = String(Math.floor(timerSeconds/3600)).padStart(2,'0')+':'+String(Math.floor((timerSeconds%3600)/60)).padStart(2,'0')+':'+String(timerSeconds%60).padStart(2,'0'); }

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

function syncPriorityButtons(groupId, selectedValue) {
  const group = document.getElementById(groupId);
  if (!group) return;

  const values = [
    'urgent_important',
    'important_not_urgent',
    'urgent_not_important',
    'not_urgent_not_important'
  ];

  group.querySelectorAll('.priority-btn').forEach((btn, idx) => {
    btn.classList.toggle('active', values[idx] === selectedValue);
  });
}

function selectPriorityValue(inputId, groupId, value) {
  const input = document.getElementById(inputId);
  if (!input) return;

  const nextValue = input.value === value ? '' : value;
  input.value = nextValue;
  syncPriorityButtons(groupId, nextValue);
}

// ==========================================
// 待办清单相关函数
// ==========================================

// 添加待办项到编辑界面
function addChecklistItem(text = '', checked = false, containerId = 'checklist-container') {
  const container = document.getElementById(containerId);
  if (!container) return;
  const id = 'checklist-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
  
  const item = document.createElement('div');
  item.className = 'checklist-item';
  item.dataset.id = id;
  item.innerHTML = `
    <input type="checkbox" ${checked ? 'checked' : ''}>
    <input type="text" placeholder="輸入待辦事項..." value="${escHtml(text)}">
    <button type="button" class="checklist-item-delete" data-checklist-id="${id}">刪除</button>
  `;
  
  container.appendChild(item);

  const textInput = item.querySelector('input[type="text"]');
  if (textInput) {
    textInput.addEventListener('keydown', (evt) => {
      if (evt.key === 'Enter') {
        evt.preventDefault();
        addChecklistItem('', false, containerId);
      }
    });
  }
  
  // 自动聚焦到新添加的输入框
  if (!text) {
    textInput?.focus();
  }
}

// 删除待办项
function deleteChecklistItem(id) {
  const item = document.querySelector(`.checklist-item[data-id="${id}"]`);
  if (item) {
    item.remove();
  }
}

// 获取当前编辑界面的待办清单数据
function getChecklistData(containerId = 'checklist-container') {
  const container = document.getElementById(containerId);
  if (!container) return null;

  const items = [];
  container.querySelectorAll('.checklist-item').forEach(item => {
    const checkbox = item.querySelector('input[type="checkbox"]');
    const textInput = item.querySelector('input[type="text"]');
    const text = textInput.value.trim();
    
    if (text) { // 只保存有内容的项目
      items.push({
        text: text,
        checked: checkbox.checked
      });
    }
  });
  return items.length > 0 ? items : null;
}

function normalizeChecklistItems(checklist) {
  if (!checklist) return [];
  const list = Array.isArray(checklist) ? checklist : (() => {
    if (typeof checklist === 'string') {
      try { return JSON.parse(checklist); } catch (e) { return []; }
    }
    return [];
  })();
  return list.filter(item => item && typeof item.text === 'string' && item.text.trim() !== '').map(item => ({
    text: item.text.trim(),
    checked: !!item.checked
  }));
}

// 在编辑界面渲染待办清单
function renderChecklistEdit(checklist, containerId = 'checklist-container') {
  const container = document.getElementById(containerId);
  if (!container) return;
  container.innerHTML = '';
  
  const normalizedChecklist = normalizeChecklistItems(checklist);
  if (normalizedChecklist.length > 0) {
    normalizedChecklist.forEach(item => {
      addChecklistItem(item.text, item.checked, containerId);
    });
  }
}

// 在卡片上切换待办项的勾选状态
async function toggleChecklistItem(cardId, itemIndex, col) {
  const card = state[col].find(c => c.id === cardId);
  const checklist = normalizeChecklistItems(card?.checklist);
  if (!card || !checklist[itemIndex]) return;
  
  // 切换状态
  checklist[itemIndex].checked = !checklist[itemIndex].checked;
  card.checklist = checklist;
  
  // 保存到服务器
  await saveCardToAPI({
    id: cardId,
    col: col,
    title: card.title,
    project: card.project,
    priority: card.priority || null,
    sourceLink: card.sourceLink,
    summary: card.summary,
    nextStep: card.nextStep,
    body: card.body,
    bgcolor: card.bgcolor,
    textcolor: card.textcolor,
    isPrivate: card.isPrivate ? 1 : 0,
    checklist: checklist
  });
  
  // 重新渲染
  render();
}

function handleChecklistClick(event) {
  const target = event.target instanceof Element ? event.target : null;
  if (!target) return;

  const addBtn = target.closest('.add-checklist-item-btn');
  if (addBtn) {
    event.preventDefault();
    event.stopPropagation();
    addChecklistItem('', false, addBtn.dataset.checklistContainer || 'checklist-container');
    return;
  }

  const deleteBtn = target.closest('.checklist-item-delete');
  if (deleteBtn) {
    event.preventDefault();
    event.stopPropagation();
    deleteChecklistItem(deleteBtn.dataset.checklistId);
  }
}

function handleChecklistMouseDown(event) {
  const target = event.target instanceof Element ? event.target : null;
  if (!target) return;

  // 先於 blur 事件觸發，避免在新增/編輯側欄 checklist 時被自動儲存清空
  const addBtn = target.closest('.add-checklist-item-btn[data-checklist-container="sidebar-checklist-container"]');
  const deleteBtn = target.closest('.checklist-item-delete');
  const inSidebarChecklistInput = target.closest('#sidebar-checklist-container .checklist-item input[type="text"]');

  if (addBtn || deleteBtn || inSidebarChecklistInput) {
    suppressSidebarAutosaveUntil = Date.now() + 1200;
  }
}

function handleChecklistTouchStart(event) {
  const target = event.target instanceof Element ? event.target : null;
  if (!target) return;

  const checkbox = target.closest('.card-checklist-toggle');
  if (!checkbox) return;

  event.preventDefault();
  event.stopPropagation();
  checkbox.click();
}

function handleChecklistChange(event) {
  const target = event.target instanceof Element ? event.target : null;
  if (!target) return;

  const checkbox = target.closest('.card-checklist-toggle');
  if (!checkbox) return;

  event.stopPropagation();
  const cardId = Number(checkbox.dataset.cardId);
  const itemIndex = Number(checkbox.dataset.itemIndex);
  const col = checkbox.dataset.col;

  if (!Number.isInteger(cardId) || !Number.isInteger(itemIndex) || !col) return;
  toggleChecklistItem(cardId, itemIndex, col);
}

document.addEventListener('click', handleChecklistClick);
document.addEventListener('mousedown', handleChecklistMouseDown, true);
document.addEventListener('touchstart', handleChecklistTouchStart, { passive: false });
document.addEventListener('change', handleChecklistChange);

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

function updateNotificationSoundUI() {
  const btn = document.getElementById('notification-sound-toggle');
  if (!btn) return;
  btn.textContent = notificationSoundEnabled ? '🔔' : '🔕';
  btn.title = notificationSoundEnabled ? '點擊關閉提示音' : '點擊開啟提示音';
  btn.setAttribute('aria-label', notificationSoundEnabled ? '關閉提示音' : '開啟提示音');
}

// 切换提示音开关
function toggleNotificationSound() {
  notificationSoundEnabled = !notificationSoundEnabled;
  localStorage.setItem('notificationSoundEnabled', notificationSoundEnabled);
  updateNotificationSoundUI();
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
  updateNotificationSoundUI();
  
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

// ESC键关闭侧边编辑栏
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape' || e.keyCode === 27) {
    const sidebar = document.getElementById('editor-sidebar');
    if (sidebar && sidebar.classList.contains('open')) {
      closeSidebar();
      e.preventDefault();
    }
  }
});

</script>
</body>
</html>
