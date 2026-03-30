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
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    echo '<meta http-equiv="refresh" content="0;url=login.php">';
    echo '<script>window.location.replace("login.php");</script>';
    echo '</head><body></body></html>';
    exit();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$username = $_SESSION['username'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>FOCUS 專注看板</title>

<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#667eea">
<meta name="description" content="蘋果印刷設計工坊 - 任務管理與專注看板系統">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="專注看板">
<link rel="apple-touch-icon" href="/icon-192.png">
<link rel="icon" type="image/png" sizes="192x192" href="/icon-192.png">

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
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

  body { font-family: 'Noto Sans TC', sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; padding: 0; padding-bottom: 70px; }

  header { background: var(--surface); border-bottom: 1px solid var(--border); padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; flex-wrap: wrap; gap: 12px; }
  .logo { font-size: 15px; font-weight: 700; letter-spacing: -0.3px; color: var(--text); }
  .logo span { color: var(--accent-focus); }
  
  .header-center { display: flex; gap: 12px; align-items: center; flex: 1; justify-content: center; min-width: 0; }
  .search-box { position: relative; max-width: 300px; width: 100%; flex-shrink: 1; }
  .search-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: var(--surface); border: 1px solid var(--border-strong); border-radius: var(--radius); box-shadow: 0 4px 16px rgba(0,0,0,0.12); z-index: 99; max-height: 320px; overflow-y: auto; display: none; margin-top: 2px; }
  .search-dropdown.show { display: block; }
  .search-result-item { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; }
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
  
  .notification-btn { position: relative; background: none; border: 1px solid var(--border-strong); border-radius: var(--radius); padding: 6px 12px; font-size: 16px; cursor: pointer; transition: background 0.12s; }
  .notification-btn:hover { background: var(--surface2); }
  .notification-badge { position: absolute; top: -4px; right: -4px; background: #EF4444; color: white; font-size: 10px; font-weight: 600; padding: 2px 5px; border-radius: 10px; min-width: 18px; text-align: center; }
  
  .help-toggle, .export-btn, .logout-btn, .settings-btn { background: none; border: 1px solid var(--border-strong); border-radius: var(--radius); padding: 6px 12px; font-size: 12px; font-family: inherit; color: var(--text-secondary); cursor: pointer; transition: background 0.12s; white-space: nowrap; }
  .help-toggle:hover, .export-btn:hover, .settings-btn:hover { background: var(--surface2); }
  .help-toggle.active { background: var(--accent-week-bg); color: var(--accent-week-text); border-color: var(--accent-week); }
  
  .export-btn { background: var(--accent-lib-bg); color: var(--accent-lib-text); border-color: var(--accent-lib); font-weight: 500; }
  .settings-btn { background: var(--accent-week-bg); color: var(--accent-week-text); border-color: var(--accent-week); }
  .logout-btn { background: #FFF3F2; color: #991B1B; border-color: #FCA5A5; }
  
  .privacy-filters { display: flex; gap: 6px; margin-left: 12px; }
  .privacy-filter-btn { background: var(--surface2); border: 1px solid var(--border); border-radius: var(--radius); padding: 4px 10px; font-size: 12px; font-family: inherit; color: var(--text-secondary); cursor: pointer; transition: all 0.15s; white-space: nowrap; }
  .privacy-filter-btn.active { background: var(--accent-lib-bg); color: var(--accent-lib-text); border-color: var(--accent-lib); font-weight: 500; }

  .main-wrap { display: flex; align-items: start; max-width: 1400px; margin: 0 auto; padding: 24px; gap: 16px; }

  .sidebar { width: 220px; flex-shrink: 0; background: var(--surface); border-radius: var(--radius-lg); border: 1px solid var(--border); overflow: hidden; display: none; position: sticky; top: 72px; }
  .sidebar.open { display: block; }
  .sidebar-accent { height: 3px; background: var(--accent-week); }
  .sidebar-header { padding: 14px 16px 10px; border-bottom: 1px solid var(--border); }
  .sidebar-title { font-size: 13px; font-weight: 700; color: var(--text); }
  .sidebar-body { padding: 12px 14px 16px; }
  .help-section { margin-bottom: 16px; }
  .help-label { font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 6px; }
  .help-step { display: flex; gap: 8px; align-items: flex-start; margin-bottom: 7px; }
  .step-num { width: 18px; height: 18px; border-radius: 50%; background: var(--accent-week-bg); color: var(--accent-week-text); font-size: 10px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .step-text, .help-tip { font-size: 11px; color: var(--text-secondary); line-height: 1.55; }
  .help-tip { background: var(--surface2); border-radius: var(--radius); padding: 8px 10px; margin-top: 4px; }

  .board-wrap { flex: 1; min-width: 0; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; align-items: start; }
  
  .col { background: var(--surface); border-radius: var(--radius-lg); border: 1px solid var(--border); overflow: hidden; }
  .col.drag-over { border-color: var(--accent-week); background: var(--accent-week-bg); }
  .col-header { padding: 14px 16px 12px; border-bottom: 1px solid var(--border); }
  .col-title-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px; }
  .col-title { font-size: 14px; font-weight: 700; }
  
  .badge { font-size: 10px; font-weight: 600; padding: 2px 8px; border-radius: 20px; }
  .badge-lib { background: var(--accent-lib-bg); color: var(--accent-lib-text); }
  .badge-week { background: var(--accent-week-bg); color: var(--accent-week-text); }
  .badge-focus { background: var(--accent-focus-bg); color: var(--accent-focus-text); }
  .badge-done { background: var(--accent-done-bg); color: var(--accent-done-text); }
  
  .col-sub { font-size: 11px; color: var(--text-muted); }
  .col-accent { height: 3px; }
  .col-lib .col-accent { background: var(--accent-lib); }
  .col-week .col-accent { background: var(--accent-week); }
  .col-focus .col-accent { background: var(--accent-focus); }
  .col-done .col-accent { background: var(--accent-done); }
  
  .cards-area { padding: 10px; min-height: 120px; }
  .empty { text-align: center; padding: 24px 16px; font-size: 12px; color: var(--text-muted); line-height: 1.6; }

  /* 卡片基本樣式 */
  .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 12px; margin-bottom: 8px; margin-top: 22px; cursor: move; transition: all 0.15s; position: relative; overflow: visible; }
  .card:hover { border-color: var(--border-strong); box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
  .card.dragging { opacity: 0.5; cursor: grabbing; }
  .card.open { padding-bottom: 6px; }
  .card.goal-dimmed { opacity: 0.35; transition: opacity 0.2s; }
  .card.goal-dimmed:hover { opacity: 0.7; }
  .card.open .card-body { display: block; }
  .card.open .card-preview { display: none; }
  .card.open .chevron { transform: rotate(180deg); }

  /* 專案卡片外觀（包含上方留白等） */
  .card.is-project { background: #FFFDF8 !important; border: 1px solid #D4B896; }
  .card.is-project .card-top { background: #E8C98A; margin: -12px -12px 10px -12px; padding: 8px 12px; border-radius: calc(var(--radius) - 1px) calc(var(--radius) - 1px) 0 0; position: relative; }
  .card.is-project .card-top .card-title { color: #4A3520 !important; }
  .card.is-project .card-top .drag-handle { color: rgba(74,53,32,0.4) !important; }
  .card.is-project .card-top .card-actions-menu button { color: #4A3520 !important; border-color: rgba(74,53,32,0.3) !important; background: rgba(74,53,32,0.08) !important; }

  .card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; background: #C8D4DC; margin: -12px -12px 10px -12px; padding: 8px 12px; border-radius: calc(var(--radius) - 1px) calc(var(--radius) - 1px) 0 0; position: relative; }
  .card-top .card-title { color: #1A2C3A !important; font-size: 14px; font-weight: 500; line-height: 1.45; flex: 1; }
  .card-top .drag-handle { cursor: grab; color: rgba(26,44,58,0.4) !important; font-size: 16px; margin-right: 4px; }
  
  .card-meta { display: flex; gap: 6px; margin-bottom: 8px; flex-wrap: wrap; align-items: center; }
  .project-tag { padding: 3px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; display: inline-block; }
  .privacy-tag.private { font-size: 10px; color: #C62828; background: rgba(198, 40, 40, 0.1); padding: 2px 8px; border-radius: 10px; margin-left: 6px; font-weight: 500; }
  .source-link { font-size: 11px; color: var(--accent-lib); text-decoration: none; display: inline-flex; align-items: center; gap: 3px; margin-bottom: 6px; }
  .card-summary { font-size: 12px; color: var(--text-secondary); line-height: 1.5; margin-bottom: 8px; font-style: italic; }
  .card-next-step { font-size: 12px; color: var(--accent-focus-text); background: var(--accent-focus-bg); padding: 8px; border-radius: 6px; margin-bottom: 8px; line-height: 1.5; }
  
  /* 待辦清單樣式 */
  .checklist-item { display: flex; align-items: flex-start; gap: 8px; padding: 8px; background: var(--surface2); border-radius: 6px; margin-bottom: 6px; }
  .checklist-item input[type="checkbox"] { margin-top: 3px; cursor: pointer; width: 16px; height: 16px; }
  .checklist-item textarea { flex: 1; border: none; background: transparent; font-size: 13px; padding: 0; font-family: inherit; resize: none; overflow: hidden; min-height: 20px; line-height: 1.5; word-break: break-all; outline: none; }
  .checklist-item-delete { padding: 2px 8px; background: #FEE2E2; color: #991B1B; border: 1px solid #FCA5A5; border-radius: 4px; font-size: 11px; cursor: pointer; }
  .add-checklist-item-btn { width: 100%; padding: 8px; border: 1px dashed var(--border-strong); background: none; border-radius: var(--radius); font-size: 12px; font-family: inherit; color: var(--text-muted); cursor: pointer; }
  
  .card-preview { font-size: 12px; line-height: 1.5; margin-bottom: 8px; max-height: 48px; overflow: hidden; position: relative; }
  .focus-timer { background: var(--accent-focus-bg); border: 1px solid var(--accent-focus); border-radius: var(--radius); padding: 12px; margin-bottom: 12px; text-align: center; }
  .timer-display { font-size: 28px; font-weight: 700; color: var(--accent-focus); margin-bottom: 10px; font-variant-numeric: tabular-nums; }
  .timer-controls { display: flex; gap: 8px; justify-content: center; }
  .timer-btn { padding: 8px 16px; border: none; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; background: var(--accent-focus); color: white; }
  .timer-btn.secondary { background: var(--surface); color: var(--text-secondary); border: 1px solid var(--border); }

  .add-card-btn { width: 100%; padding: 12px; border: 1px dashed var(--border-strong); background: none; border-radius: var(--radius); font-size: 13px; font-weight: 500; font-family: inherit; color: var(--text-muted); cursor: pointer; margin-top: 4px; }
  .clear-done-btn { width: 100%; padding: 10px; border: 1px solid var(--border); background: var(--surface); border-radius: var(--radius); font-size: 12px; font-family: inherit; color: var(--text-muted); cursor: pointer; margin-top: 8px; }

  /* Modal */
  .overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 100; padding: 16px; overflow-y: auto; }
  .overlay.open { display: flex; }
  .modal { background: var(--surface); border-radius: var(--radius-lg); width: 100%; max-width: calc(100vw - 32px); max-height: calc(100vh - 32px); display: flex; flex-direction: column; margin: auto; overflow: hidden; }
  .modal-header { padding: 16px 20px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
  .modal-title { font-size: 16px; font-weight: 700; }
  .modal-body { padding: 20px; flex: 1; overflow-y: auto; }
  .field { margin-bottom: 16px; position: relative; }
  .field-label { display: block; font-size: 13px; font-weight: 600; color: var(--text); margin-bottom: 8px; }
  .field-input, .field-textarea { width: 100%; padding: 12px 14px; border: 1px solid var(--border); border-radius: var(--radius); font-family: inherit; font-size: 14px; color: var(--text); background: var(--surface); }
  .field-input:focus, .field-textarea:focus { outline: none; border-color: var(--accent-week); }
  .field-textarea { resize: vertical; min-height: 80px; line-height: 1.5; }
  
  .color-section { margin-bottom: 16px; }
  .color-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 10px; display: block; }
  .swatches { display: flex; gap: 12px; flex-wrap: wrap; }
  .swatch { width: 48px; height: 48px; border-radius: 10px; cursor: pointer; border: 2px solid transparent; }
  .swatch.selected { border-color: var(--accent-week); box-shadow: 0 0 0 2px var(--surface), 0 0 0 4px var(--accent-week); }

  .modal-footer { padding: 16px 20px; border-top: 1px solid var(--border); display: flex; gap: 12px; justify-content: flex-end; flex-shrink: 0; position: sticky; bottom: 0; background: var(--surface); z-index: 10; }
  .modal-btn { padding: 12px 24px; border: none; border-radius: var(--radius); font-family: inherit; font-size: 14px; font-weight: 600; cursor: pointer; }
  .modal-btn.primary { background: var(--accent-week); color: white; }
  .modal-btn.secondary { background: var(--surface2); color: var(--text-secondary); }

  /* 四象限優先級 */
  .priority-grid { display: flex; flex-wrap: nowrap; gap: 6px; margin-top: 4px; }
  .priority-btn { flex: 1; padding: 7px 4px; border: 1.5px solid var(--border-strong); background: var(--surface); border-radius: 8px; font-size: 11px; font-weight: 500; font-family: inherit; cursor: pointer; transition: all 0.12s; text-align: center; color: var(--text-secondary); white-space: nowrap; }
  .priority-btn.active[data-value="urgent_important"] { background: #FFF0F0; border-color: #FF4444; color: #CC0000; }
  .priority-btn.active[data-value="important_not_urgent"] { background: #FFF8EC; border-color: #FF9800; color: #CC7700; }
  .priority-btn.active[data-value="urgent_not_important"] { background: #EEF5FF; border-color: #2196F3; color: #0D6EBF; }
  .priority-btn.active[data-value="not_urgent_not_important"] { background: #F5F5F5; border-color: #9E9E9E; color: #555555; }

  /* 康乃爾筆記格式 */
  .cornell-layout { display: block; border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; margin-bottom: 8px; }
  .cornell-top { display: flex; flex-direction: column; border-bottom: 1px solid var(--border); }
  .cornell-a { width: 100%; padding: 8px; background: var(--surface2); overflow: hidden; border-bottom: 1px solid var(--border); }
  .cornell-b { flex: 1; padding: 10px; font-size: 12px; line-height: 1.8; overflow-wrap: break-word; cursor: text; position: relative;
    background-image: repeating-linear-gradient(to bottom, transparent, transparent calc(1.8em - 1px), rgba(0,0,0,0.15) calc(1.8em - 1px), rgba(0,0,0,0.15) 1.8em);
    background-size: 100% 1.8em; }
  .cornell-layout.edit-mode .cornell-top { flex-direction: row; }
  .cornell-layout.edit-mode .cornell-a { width: 45%; border-right: 1px solid var(--border); border-bottom: none; }
  .cornell-label { font-size: 10px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
  .cornell-c { padding: 8px 12px; background: #FFFEF0; border-top: 2px solid var(--accent-week); font-size: 12px; color: var(--text-secondary); font-style: italic; line-height: 1.5; }
  .cornell-c-label { font-size: 10px; font-weight: 700; color: var(--accent-week-text); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
  .note-editable { width: 100%; min-height: 120px; outline: none; }

  /* 卡片動作選單 */
  .card-actions-menu { position: relative; display: inline-block; margin-left: auto; flex-shrink: 0; }
  .card-actions-toggle { background: var(--surface2); border: 1px solid var(--border-strong); border-radius: 20px; padding: 5px 14px; font-size: 16px; color: var(--text); cursor: pointer; line-height: 1; margin-left: 8px; font-weight: 700; }
  .card-actions-dropdown { display: none; position: absolute; right: 0; top: 100%; margin-top: 4px; background: var(--surface); border: 1px solid var(--border-strong); border-radius: var(--radius); box-shadow: 0 4px 16px rgba(0,0,0,0.12); min-width: 140px; z-index: 50; overflow: hidden; }
  .card-actions-dropdown.open { display: block; }
  .card-action-item { display: block; width: 100%; padding: 10px 14px; font-size: 13px; font-family: inherit; font-weight: 500; text-align: left; border: none; background: none; cursor: pointer; color: var(--text-secondary); }
  .card-action-item:hover { background: var(--surface2); color: var(--text); }
  .card-action-item.danger { color: #E24B4A; }
  .card-action-item.primary { color: var(--accent-week-text); }
  .card-action-item.done-btn { color: var(--accent-done-text); }

  /* 戰略樹（左側收折面板） */
  .goal-panel { width: 280px; flex-shrink: 0; background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); display: flex; flex-direction: column; align-self: flex-start; position: sticky; top: 16px; max-height: calc(100vh - 100px); overflow: hidden; transition: width 0.25s ease; }
  .goal-panel.collapsed { width: 44px; }
  .goal-panel-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-bottom: 1px solid var(--border); background: linear-gradient(135deg, #1A1A18 0%, #2d2d2a 100%); border-radius: calc(var(--radius) - 1px) calc(var(--radius) - 1px) 0 0; flex-shrink: 0; gap: 8px; }
  .goal-panel-title { font-size: 13px; font-weight: 700; color: #FFD700; white-space: nowrap; overflow: hidden; letter-spacing: 0.5px; }
  .goal-panel.collapsed .goal-panel-title { display: none; }
  .goal-panel-toggle { background: none; border: none; cursor: pointer; font-size: 16px; color: rgba(255,255,255,0.7); padding: 2px 4px; border-radius: 4px; flex-shrink: 0; transition: transform 0.25s; line-height: 1; }
  .goal-panel.collapsed .goal-panel-toggle { transform: rotate(180deg); }
  .goal-panel-body { overflow-y: auto; flex: 1; padding: 8px; }
  .goal-panel.collapsed .goal-panel-body { display: none; }
  .goal-panel.collapsed .goal-add-btn { display: none; }
  .goal-add-btn { margin: 8px; padding: 8px; width: calc(100% - 16px); border: 1px dashed var(--border-strong); background: none; border-radius: var(--radius); font-size: 12px; color: var(--text-muted); cursor: pointer; font-family: inherit; transition: all 0.15s; flex-shrink: 0; }
  .goal-add-btn:hover { background: var(--surface2); color: var(--text); border-color: #FFD700; }

  /* 年度/月度/週度卡片 */
  .goal-year-card { background: linear-gradient(135deg, #1A1A18 0%, #2d2d2a 100%); border-radius: var(--radius); margin-bottom: 8px; overflow: hidden; border: 1px solid rgba(255,215,0,0.3); }
  .goal-year-header { display: flex; align-items: center; gap: 8px; padding: 10px 12px; cursor: pointer; user-select: none; }
  .goal-year-header:hover { background: rgba(255,255,255,0.05); }
  .goal-year-chevron { font-size: 10px; color: rgba(255,255,255,0.5); transition: transform 0.2s; flex-shrink: 0; padding: 4px; }
  .goal-year-card.open .goal-year-chevron { transform: rotate(90deg); }
  .goal-year-title { font-size: 13px; font-weight: 700; color: #FFD700; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: pointer; }
  .goal-year-title:hover { text-decoration: underline; }
  .goal-year-progress-bar { height: 3px; background: rgba(255,255,255,0.1); margin: 0 12px 10px; border-radius: 2px; overflow: hidden; }
  .goal-year-progress-fill { height: 100%; background: #FFD700; border-radius: 2px; transition: width 0.6s cubic-bezier(0.34,1.56,0.64,1); }
  .goal-year-progress-fill.complete { background: #22c55e; }
  .goal-year-children { padding: 0 8px 8px; display: none; }
  .goal-year-card.open .goal-year-children { display: block; }

  .goal-month-card { background: rgba(255,255,255,0.05); border-radius: calc(var(--radius) - 2px); margin-bottom: 6px; border: 1px solid rgba(255,255,255,0.08); overflow: hidden; }
  .goal-month-header { display: flex; align-items: center; gap: 8px; padding: 8px 10px; cursor: pointer; user-select: none; }
  .goal-month-header:hover { background: rgba(255,255,255,0.05); }
  .goal-month-chevron { font-size: 9px; color: rgba(255,255,255,0.4); transition: transform 0.2s; flex-shrink: 0; padding: 4px; }
  .goal-month-card.open .goal-month-chevron { transform: rotate(90deg); }
  .goal-month-title { font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.85); flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: pointer; }
  .goal-month-title:hover { text-decoration: underline; }
  .goal-month-badge { font-size: 10px; padding: 1px 6px; border-radius: 8px; background: rgba(99,102,241,0.3); color: #a5b4fc; flex-shrink: 0; }
  .goal-month-progress-bar { height: 3px; background: rgba(255,255,255,0.08); margin: 0 10px 8px; border-radius: 2px; overflow: hidden; }
  .goal-month-progress-fill { height: 100%; background: #6366f1; border-radius: 2px; transition: width 0.6s cubic-bezier(0.34,1.56,0.64,1); }
  .goal-month-progress-fill.complete { background: #22c55e; }
  .goal-month-children { padding: 0 8px 6px; display: none; }
  .goal-month-card.open .goal-month-children { display: block; }

  .goal-week-card { background: rgba(255,255,255,0.03); border-radius: calc(var(--radius) - 3px); margin-bottom: 4px; border: 1px solid rgba(255,255,255,0.06); padding: 6px 10px; }
  .goal-week-card.goal-active-filter { border-color: #6366f1; background: rgba(99,102,241,0.08); }
  .goal-week-header { display: flex; align-items: center; gap: 6px; cursor: pointer; user-select: none; }
  .goal-week-title { font-size: 11px; color: rgba(255,255,255,0.7); flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: pointer; }
  .goal-week-title:hover { text-decoration: underline; color: #fff; }
  .goal-week-progress { font-size: 10px; color: rgba(255,255,255,0.4); flex-shrink: 0; }
  .goal-week-bar { height: 2px; background: rgba(255,255,255,0.06); margin-top: 5px; border-radius: 1px; overflow: hidden; }
  .goal-week-bar-fill { height: 100%; background: #f97316; border-radius: 1px; transition: width 0.6s cubic-bezier(0.34,1.56,0.64,1); }
  .goal-week-bar-fill.complete { background: #22c55e; }
  .goal-week-actions { display: flex; gap: 4px; margin-top: 6px; }
  .goal-action-btn { font-size: 10px; padding: 2px 8px; border-radius: 4px; border: 1px solid rgba(255,255,255,0.15); background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.6); cursor: pointer; transition: all 0.15s; }
  .goal-action-btn:hover { background: rgba(255,255,255,0.12); color: #fff; border-color: rgba(255,255,255,0.3); }
  .goal-action-btn.primary { background: rgba(99,102,241,0.3); border-color: rgba(99,102,241,0.5); color: #a5b4fc; }
  .goal-action-btn.primary:hover { background: rgba(99,102,241,0.5); color: #fff; }
  .goal-inline-add { width: 100%; padding: 5px 8px; border: 1px dashed rgba(255,255,255,0.15); background: none; border-radius: 4px; font-size: 11px; color: rgba(255,255,255,0.35); cursor: pointer; text-align: left; transition: all 0.15s; margin-top: 4px; }
  .goal-inline-add:hover { border-color: rgba(255,255,255,0.3); color: rgba(255,255,255,0.7); background: rgba(255,255,255,0.04); }

  #toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%); background: #222; color: #FFF; padding: 14px 24px; border-radius: 30px; font-size: 14px; font-weight: 500; opacity: 0; transition: opacity 0.2s, transform 0.2s; pointer-events: none; z-index: 200; box-shadow: 0 8px 24px rgba(0,0,0,0.2); }
  #toast.show { opacity: 1; transform: translateX(-50%) translateY(-10px); }

  @media (max-width: 1024px) { .board-wrap { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
  @media (max-width: 768px) { 
    .header-user, .filter-tags, .privacy-filters, .header-actions .help-toggle, .header-actions .export-btn, .header-actions .logout-btn, .header-actions .settings-btn { display: none !important; }
    .sidebar { width: 100%; position: relative; top: 0; border-radius: 0; border-left: none; border-right: none; margin-bottom: 0; }
    .main-wrap { flex-direction: column; padding: 0 !important; gap: 0; }
    .board-wrap { display: block !important; }
    .board-wrap .col { display: none !important; width: 100vw !important; max-width: 100vw !important; border-radius: 0 !important; border-left: none !important; border-right: none !important; box-shadow: none !important; }
    .board-wrap .col.active { display: block !important; }
    .mobile-tab-bar { display: grid !important; position: fixed; bottom: 0; left: 0; right: 0; grid-template-columns: repeat(auto-fit, minmax(60px, 1fr)); height: 64px; background: var(--surface); border-top: 1px solid var(--border); z-index: 100; }
    .mobile-tab { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; border: none; background: none; cursor: pointer; color: var(--text-muted); font-family: inherit; }
    .mobile-tab.active { color: var(--accent-focus); background: var(--accent-focus-bg); }
    .goal-panel { display: none; }
  }
</style>
</head>
<body>

<header>
  <div class="logo">🎯 <span>FOCUS</span></div>
  <div class="header-center">
    <div class="search-box">
      <span class="search-icon">🔍</span>
      <input type="text" class="search-input" id="search-input" placeholder="搜尋卡片..." readonly>
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
    <div class="header-user">👤 <?php echo htmlspecialchars($username); ?></div>
    <button class="settings-btn" onclick="openProjectSettings()">⚙️ 設定</button>
    <button class="notification-btn" onclick="logout()" style="background:#FFF3F2;color:#991B1B;border-color:#FCA5A5;">登出</button>
  </div>
</header>

<div class="main-wrap">
  <div class="goal-panel" id="goal-panel">
    <div class="goal-panel-header">
      <span class="goal-panel-title">🏆 戰略目標</span>
      <button class="goal-panel-toggle" onclick="toggleGoalPanel()" title="收折/展開">◀</button>
    </div>
    <div class="goal-panel-body" id="goal-panel-body"></div>
    <button class="goal-add-btn" onclick="openGoalModal('year', null)">＋ 新增年度目標</button>
  </div>

  <div class="board-wrap">
    <div class="col col-lib active" data-col="lib">
      <div class="col-accent"></div>
      <div class="col-header">
        <div class="col-title-row">
          <div class="col-title">📚 策略筆記區</div>
          <div class="badge badge-lib" id="badge-lib">0 則</div>
        </div>
        <div class="col-sub">長期儲存 • 無限制</div>
      </div>
      <div style="padding: 0 16px 8px;"><button class="add-card-btn" onclick="openModal('lib')">+ 新增筆記</button></div>
      <div class="cards-area" id="cards-lib"></div>
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
      <div style="padding: 0 16px 8px;"><button class="add-card-btn" id="add-week" onclick="openModal('week')">+ 新增目標</button></div>
      <div class="cards-area" id="cards-week"></div>
    </div>

    <div class="col col-focus" data-col="focus">
      <div class="col-accent"></div>
      <div class="col-header">
        <div class="col-title-row">
          <div class="col-title">🎯 今日專注</div>
          <div class="badge badge-focus" id="badge-badge-focus">0 / 1</div>
        </div>
        <div class="col-sub">現在就做這件事</div>
      </div>
      <div class="cards-area" id="cards-focus"></div>
    </div>

    <div class="col col-done" data-col="done">
      <div class="col-accent"></div>
      <div class="col-header">
        <div class="col-title-row">
          <div class="col-title">✅ 已完成</div>
          <div class="badge badge-done" id="badge-done">0 件</div>
        </div>
      </div>
      <div class="cards-area" id="cards-done"></div>
    </div>
  </div>
</div>

<div class="overlay" id="overlay" onclick="handleOverlayClick(event)">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-title">新增卡片</div>
    </div>
    <div class="modal-body">
      <div class="field">
        <label class="field-label">標題 *</label>
        <input type="text" class="field-input" id="input-title" placeholder="輸入任務標題...">
      </div>
      <div class="field">
        <label class="field-label">摘要 <span class="optional">(選填)</span></label>
        <input type="text" class="field-input" id="input-summary" placeholder="一句話摘要...">
      </div>
      <div class="field">
        <label class="field-label">待辦清單</label>
        <div id="checklist-container"></div>
        <button type="button" class="add-checklist-item-btn" onclick="addChecklistItem()">+ 新增待辦項目</button>
      </div>
      <div class="field">
        <label class="field-label">詳細筆記</label>
        <div id="input-body-editor" class="note-editable" contenteditable="true" style="padding:10px; border:1px solid var(--border); border-radius:var(--radius); min-height:150px;"></div>
      </div>
      <div class="field">
        <label class="field-label">專案類型 <span class="optional">(選填)</span></label>
        <div id="project-btn-group" style="display:flex; flex-wrap:wrap; gap:6px;"></div>
        <input type="hidden" id="input-project">
      </div>
      <div class="field">
        <label class="field-label">優先級（四象限）</label>
        <div class="priority-grid" id="priority-grid">
          <button type="button" class="priority-btn" data-value="urgent_important" onclick="selectPriority('urgent_important')">🔥 重要緊急</button>
          <button type="button" class="priority-btn" data-value="important_not_urgent" onclick="selectPriority('important_not_urgent')">⭐ 重要不緊急</button>
          <button type="button" class="priority-btn" data-value="urgent_not_important" onclick="selectPriority('urgent_not_important')">⚡ 緊急不重要</button>
          <button type="button" class="priority-btn" data-value="not_urgent_not_important" onclick="selectPriority('not_urgent_not_important')">💤 不重要不緊急</button>
        </div>
        <input type="hidden" id="input-priority">
      </div>
      <input type="hidden" id="input-col">
      <input type="hidden" id="input-edit-id">
      <input type="hidden" id="input-bgcolor" value="">
      <input type="hidden" id="input-textcolor" value="">
    </div>
    <div class="modal-footer">
      <button class="modal-btn secondary" onclick="closeModal()">取消</button>
      <button class="modal-btn primary" onclick="saveCard()">儲存</button>
    </div>
  </div>
</div>

<div id="goal-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:10000;align-items:center;justify-content:center;">
  <div style="background:var(--surface);border-radius:16px;padding:0;width:90%;max-width:480px;box-shadow:0 16px 48px rgba(0,0,0,0.3);overflow:hidden;">
    <div id="gm-header" style="padding:18px 20px 16px;border-bottom:1px solid var(--border);">
      <div id="gm-title" style="font-size:16px;font-weight:700;color:var(--text);"></div>
      <div id="gm-parent-info" style="font-size:12px;color:var(--text-muted);margin-top:4px;display:none;"></div>
    </div>
    <div style="padding:20px;">
      <div style="margin-bottom:14px;">
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">標題 *</label>
        <input id="gm-title-input" type="text" placeholder="輸入目標標題..." style="width:100%;padding:10px 12px;border:1px solid var(--border-strong);border-radius:var(--radius);font-family:inherit;" onkeydown="if(event.key==='Enter')document.getElementById('gm-confirm').click()">
      </div>
      <div style="margin-bottom:14px;">
        <label style="display:block;font-size:12px;font-weight:600;margin-bottom:6px;">摘要 <span style="font-weight:400;color:var(--text-muted);">(選填)</span></label>
        <input id="gm-summary-input" type="text" placeholder="一句話說明這個目標..." style="width:100%;padding:10px 12px;border:1px solid var(--border-strong);border-radius:var(--radius);font-family:inherit;">
      </div>
    </div>
    <div style="padding:14px 20px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end;background:var(--surface2);">
      <button onclick="closeGoalModal()" style="padding:8px 18px;border:1px solid var(--border-strong);border-radius:var(--radius);background:none;cursor:pointer;">取消</button>
      <button id="gm-confirm" onclick="confirmGoalModal()" style="padding:8px 20px;border:none;border-radius:var(--radius);background:#534AB7;color:#fff;cursor:pointer;">確認</button>
    </div>
  </div>
</div>

<div id="toast"></div>

<script>
let state = { lib: [], week: [], focus: [], done: [], goal: [] };
const CURRENT_USERNAME = <?php echo json_encode($_SESSION['username'] ?? ''); ?>;
let privacyFilter = 'all';
let searchQuery = '';
let ALL_PROJECTS = {};
let PROJECT_LABELS = {};
let currentFilter = null;
let goalFilterParentId = null;

document.addEventListener('DOMContentLoaded', async () => {
  await loadCards();
  renderProjectSelect();
});

function escHtml(str) { return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function toast(msg) {
  const el = document.getElementById('toast'); 
  el.textContent = msg; 
  el.classList.add('show');
  clearTimeout(el._t); 
  el._t = setTimeout(() => { el.classList.remove('show'); }, 2500);
}

// ==========================================
// API 存取
// ==========================================
async function loadCards() {
  try {
    const res = await fetch('api/cards.php?action=list');
    const data = await res.json();
    if (data.success === false) { toast('❌ 讀取失敗'); return; }
    state = { lib: data.lib || [], week: data.week || [], focus: data.focus || [], done: data.done || [], goal: data.goal || [] };
    render();
    renderGoalTree();
  } catch (err) { toast('連線異常，無法讀取卡片'); }
}

async function saveCardToAPI(cardData) {
  try {
    const res = await fetch('api/cards.php?action=save', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(cardData)
    });
    const data = await res.json();
    if (data.success) {
      toast('✅ 儲存成功');
      await loadCards();
    } else toast('❌ ' + (data.error || '儲存失敗'));
  } catch (err) { toast('❌ 連線錯誤，儲存失敗'); }
}

async function moveAPI(id, toCol) {
  try {
    const res = await fetch('api/cards.php?action=move', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id, col: toCol })
    });
    const data = await res.json();
    if (data.success) { await loadCards(); } else { toast('❌ 移動失敗'); }
  } catch (err) { toast('連線錯誤'); }
}

async function deleteAPI(id) {
  if (!confirm('確定要刪除這張卡片嗎？此動作無法復原。')) return;
  try {
    const res = await fetch(`api/cards.php?action=delete&id=${id}`);
    const data = await res.json();
    if (data.success) { toast('🗑️ 卡片已刪除'); await loadCards(); } 
  } catch (err) { toast('連線錯誤'); }
}

// ==========================================
// 渲染邏輯
// ==========================================
function shouldShowCard(card) {
  if (privacyFilter === 'shared' && card.isPrivate) return false;
  if (privacyFilter === 'private' && !card.isPrivate) return false;
  if (currentFilter && card.project !== currentFilter) return false;
  return true;
}

function render() {
  ['lib', 'week', 'focus', 'done'].forEach(col => {
    const area = document.getElementById('cards-' + col); area.innerHTML = '';
    let visibleCards = state[col].filter(shouldShowCard);

    if (col === 'lib') {
      // 策略區優先顯示最新建立的卡片（ID 遞減），同時支援優先級排序
      visibleCards.sort((a, b) => {
        const pa = a.priority === 'urgent_important' ? 0 : 1;
        const pb = b.priority === 'urgent_important' ? 0 : 1;
        if (pa !== pb) return pa - pb;
        return b.id - a.id; // 新卡片放上面
      });
    }

    if (visibleCards.length === 0) {
      const empty = document.createElement('div'); empty.className = 'empty'; empty.textContent = '沒有卡片';
      area.appendChild(empty);
    } else {
      visibleCards.forEach((card, idx) => area.appendChild(buildCard(card, col, idx + 1)));
    }
  });

  document.getElementById('badge-lib').textContent = state.lib.length + ' 則';
  document.getElementById('badge-week').textContent = state.week.length + ' / 3';
}

function buildCard(card, col, cardNo) {
  const div = document.createElement('div');
  const isProjectCard = card.checklist && Array.isArray(card.checklist) && card.checklist.length > 0;
  const isFiltered = goalFilterParentId !== null && card.parentId !== goalFilterParentId;
  div.className = 'card' + (isProjectCard ? ' is-project' : '') + (isFiltered ? ' goal-dimmed' : '');
  div.id = 'card-' + card.id;

  let parentBadgeHTML = '';
  let cardTypeTag = '筆記';
  let cardTypeBg = '#4A7A9B';
  let cardTypeColor = '#FFF';

  if (card.parentId) {
    const parent = state.goal.find(g => g.id === card.parentId);
    if (parent) {
      let icon = '🎯'; let color = '#6366f1';
      if (parent.level === 'year') { icon='📌'; color='#B8860B'; cardTypeTag='📌 年度專案'; cardTypeBg='#B8860B'; cardTypeColor='#FFF'; }
      if (parent.level === 'month') { icon='📅'; color='#4169E1'; cardTypeTag='📅 月度專案'; cardTypeBg='#4169E1'; cardTypeColor='#FFF'; }
      if (parent.level === 'week') { icon='📋'; color='#D2691E'; cardTypeTag='📋 週專案'; cardTypeBg='#D2691E'; cardTypeColor='#FFF'; }
      parentBadgeHTML = `<div style="background: ${color}15; border-left: 3px solid ${color}; padding: 6px 10px; margin-bottom: 8px; font-size: 11px; font-weight: bold; color: ${color}; border-radius: 0 4px 4px 0;">${icon} 隸屬目標：${escHtml(parent.title)}</div>`;
    }
  } else if (isProjectCard) {
    cardTypeTag = '專案筆記'; cardTypeBg = '#C8922A'; cardTypeColor = '#FFF8EE';
  }

  // 渲染動態 Folder Tag (左上角小標籤)
  const folderTag = `<div style="position:absolute; top:-20px; left:12px; background:${cardTypeBg}; color:${cardTypeColor}; font-size:10px; font-weight:700; padding:2px 10px; border-radius:6px 6px 0 0; letter-spacing:0.5px;">${cardTypeTag}</div>`;

  let actsHTML = `<div class="card-actions-menu">
    <button class="card-actions-toggle" onclick="toggleCardMenu('menu-${card.id}');event.stopPropagation()">⋯</button>
    <div class="card-actions-dropdown" id="menu-${card.id}">
      <button class="card-action-item" onclick="editCard(${card.id},'${col}');closeCardMenu('menu-${card.id}');event.stopPropagation()">✏️ 編輯卡片</button>
      <button class="card-action-item danger" onclick="deleteAPI(${card.id});closeCardMenu('menu-${card.id}');event.stopPropagation()">🗑 刪除</button>
    </div>
  </div>`;

  div.innerHTML = `
    <div class="card-top">
      ${folderTag}
      <div class="card-title">${escHtml(card.title)}</div>
      ${actsHTML}
    </div>
    ${parentBadgeHTML}
    <div class="card-summary" style="margin-top:8px;">${escHtml(card.summary || '')}</div>
    <div class="card-preview">${card.body || ''}</div>
  `;
  return div;
}

function toggleCardMenu(menuId) {
  const menu = document.getElementById(menuId);
  if (!menu) return;
  const isOpen = menu.classList.contains('open');
  document.querySelectorAll('.card-actions-dropdown.open').forEach(m => m.classList.remove('open'));
  if (!isOpen) menu.classList.add('open');
}
function closeCardMenu(menuId) {
  const menu = document.getElementById(menuId);
  if (menu) menu.classList.remove('open');
}

// ==========================================
// 戰略樹與目標 Modal
// ==========================================
let goalPanelCollapsed = false;
let _goalModalState = {};

function toggleGoalPanel() {
  goalPanelCollapsed = !goalPanelCollapsed;
  const panel = document.getElementById('goal-panel');
  if (panel) panel.classList.toggle('collapsed', goalPanelCollapsed);
}

function renderGoalTree() {
  const container = document.getElementById('goal-panel-body');
  if (!container) return;
  const goalCards = state.goal || [];
  const yearCards = goalCards.filter(c => c.level === 'year');

  container.innerHTML = '';
  if (yearCards.length === 0) {
    container.innerHTML = `<div style="text-align:center;padding:24px 16px;color:rgba(255,255,255,0.3);font-size:12px;">還沒有目標<br>點下方按鈕建立第一個</div>`;
  } else {
    yearCards.forEach(year => container.appendChild(buildYearCard(year, goalCards)));
  }
}

function buildYearCard(year, allGoalCards) {
  const monthCards = allGoalCards.filter(c => c.parentId === year.id && c.level === 'month');
  const div = document.createElement('div');
  div.className = 'goal-year-card';
  div.innerHTML = `
    <div class="goal-year-header">
      <span class="goal-year-chevron" onclick="this.closest('.goal-year-card').classList.toggle('open'); event.stopPropagation();">▶</span>
      <span class="goal-year-icon" onclick="this.closest('.goal-year-card').classList.toggle('open'); event.stopPropagation();">📌</span>
      <span class="goal-year-title" onclick="editGoalCard(${year.id}, event)" title="點擊編輯">${escHtml(year.title)}</span>
    </div>
    <div class="goal-year-children" id="year-children-${year.id}"></div>
  `;
  const childrenEl = div.querySelector(`#year-children-${year.id}`);
  monthCards.forEach(month => childrenEl.appendChild(buildMonthCard(month, allGoalCards)));
  const addBtn = document.createElement('button');
  addBtn.className = 'goal-inline-add';
  addBtn.textContent = '＋ 新增月度目標';
  addBtn.onclick = (e) => { e.stopPropagation(); openGoalModal('month', year.id); };
  childrenEl.appendChild(addBtn);
  return div;
}

function buildMonthCard(month, allGoalCards) {
  const weekCards = allGoalCards.filter(c => c.parentId === month.id && c.level === 'week');
  const div = document.createElement('div');
  div.className = 'goal-month-card';
  div.innerHTML = `
    <div class="goal-month-header">
      <span class="goal-month-chevron" onclick="this.closest('.goal-month-card').classList.toggle('open'); event.stopPropagation();">▶</span>
      <span class="goal-month-title" onclick="editGoalCard(${month.id}, event)" title="點擊編輯">${escHtml(month.title)}</span>
    </div>
    <div class="goal-month-children" id="month-children-${month.id}"></div>
  `;
  const childrenEl = div.querySelector(`#month-children-${month.id}`);
  weekCards.forEach(week => childrenEl.appendChild(buildWeekCard(week)));
  const addBtn = document.createElement('button');
  addBtn.className = 'goal-inline-add';
  addBtn.textContent = '＋ 新增週目標';
  addBtn.onclick = (e) => { e.stopPropagation(); openGoalModal('week', month.id); };
  childrenEl.appendChild(addBtn);
  return div;
}

function buildWeekCard(week) {
  const isFiltered = goalFilterParentId === week.id;
  const div = document.createElement('div');
  div.className = 'goal-week-card' + (isFiltered ? ' goal-active-filter' : '');
  div.innerHTML = `
    <div class="goal-week-header" onclick="filterByParent(${week.id})" title="點擊篩選此週子任務">
      <span class="goal-week-title" onclick="editGoalCard(${week.id}, event); event.stopPropagation();" title="點擊編輯">📋 ${escHtml(week.title)}</span>
    </div>
    <div class="goal-week-actions">
      <button class="goal-action-btn primary" onclick="spawnProjectCard(${week.id}, '${escHtml(week.title)}');event.stopPropagation()">＋ 子任務</button>
      <button class="goal-action-btn" onclick="deleteAPI(${week.id});event.stopPropagation()" style="color:#E24B4A;">🗑</button>
    </div>
  `;
  return div;
}

function openGoalModal(level, parentId, editId = null) {
  const levelNames = { year: '年度目標', month: '月度目標', week: '週目標' };
  _goalModalState = { level, parentId, editId };

  document.getElementById('gm-title').textContent = (editId ? '編輯 ' : '新增 ') + (levelNames[level] || '目標');
  
  if (editId) {
    const card = state.goal.find(c => c.id === editId);
    if (card) {
      document.getElementById('gm-title-input').value = card.title || '';
      document.getElementById('gm-summary-input').value = card.summary || '';
    }
  } else {
    document.getElementById('gm-title-input').value = '';
    document.getElementById('gm-summary-input').value = '';
  }

  document.getElementById('goal-modal-overlay').style.display = 'flex';
  setTimeout(() => document.getElementById('gm-title-input').focus(), 60);
}

function closeGoalModal() {
  document.getElementById('goal-modal-overlay').style.display = 'none';
  _goalModalState = {};
}

async function confirmGoalModal() {
  const title = document.getElementById('gm-title-input').value.trim();
  if (!title) { document.getElementById('gm-title-input').focus(); return; }

  const { level, parentId, editId } = _goalModalState;
  const summary = document.getElementById('gm-summary-input').value.trim();

  const data = { col: 'goal', title, level, parentId: parentId || null, summary: summary || null, isPrivate: 0 };
  if (editId) data.id = editId;

  await saveCardToAPI(data);
  closeGoalModal();
}

function editGoalCard(id, e) {
  if (e) e.stopPropagation();
  const card = state.goal.find(c => c.id === id);
  if (!card) return;
  openGoalModal(card.level, card.parentId, id);
}

function filterByParent(parentId) {
  goalFilterParentId = goalFilterParentId === parentId ? null : parentId;
  render();
  renderGoalTree();
  toast(goalFilterParentId ? '🔍 篩選中：只顯示此目標的子任務' : '已取消篩選');
}

function spawnProjectCard(weekId, weekTitle) {
  openModal('lib');
  document.getElementById('modal-title').textContent = `🚀 新增子任務 → ${weekTitle}`;
  window._spawnParentId = weekId;
  window._spawnLevel = 'project';
}

// ==========================================
// 卡片 Modal
// ==========================================
function openModal(col) {
  document.getElementById('input-col').value = col;
  document.getElementById('input-edit-id').value = '';
  document.getElementById('input-title').value = '';
  document.getElementById('input-summary').value = '';
  document.getElementById('input-body-editor').innerHTML = '';
  document.getElementById('checklist-container').innerHTML = '';
  document.getElementById('modal-title').textContent = '新增卡片';
  document.getElementById('overlay').classList.add('open');
}

function editCard(id, col) {
  const card = state[col].find(c => c.id === id); if (!card) return;
  document.getElementById('input-col').value = col;
  document.getElementById('input-edit-id').value = id;
  document.getElementById('input-title').value = card.title;
  document.getElementById('input-summary').value = card.summary || '';
  document.getElementById('input-body-editor').innerHTML = card.body || '';
  
  const container = document.getElementById('checklist-container');
  container.innerHTML = '';
  if (card.checklist) {
    card.checklist.forEach(item => addChecklistItem(item.text, item.checked));
  }
  
  document.getElementById('modal-title').textContent = '編輯卡片';
  document.getElementById('overlay').classList.add('open');
}

async function saveCard() {
  const title = document.getElementById('input-title').value.trim();
  if (!title) return;

  const checklist = [];
  document.querySelectorAll('#checklist-container .checklist-item').forEach(item => {
    const text = item.querySelector('textarea').value.trim();
    if (text) checklist.push({ text: text, checked: item.querySelector('input').checked });
  });

  const eid = document.getElementById('input-edit-id').value;
  const data = {
    col: document.getElementById('input-col').value,
    title: title,
    summary: document.getElementById('input-summary').value.trim(),
    body: document.getElementById('input-body-editor').innerHTML.trim(),
    priority: document.getElementById('input-priority').value || null,
    checklist: checklist.length > 0 ? checklist : null
  };
  
  if (eid) data.id = eid;
  if (window._spawnParentId) {
    data.parentId = window._spawnParentId;
    data.level = window._spawnLevel || 'project';
    window._spawnParentId = null;
    window._spawnLevel = null;
  }

  await saveCardToAPI(data);
  closeModal();
}

function closeModal() {
  document.getElementById('overlay').classList.remove('open');
}
function handleOverlayClick(e) { if (e.target === document.getElementById('overlay')) closeModal(); }

function addChecklistItem(text = '', checked = false) {
  const container = document.getElementById('checklist-container');
  const item = document.createElement('div');
  item.className = 'checklist-item';
  item.innerHTML = `
    <input type="checkbox" ${checked ? 'checked' : ''}>
    <textarea rows="1">${escHtml(text)}</textarea>
    <button class="checklist-item-delete" onclick="this.parentElement.remove()">🗑</button>
  `;
  container.appendChild(item);
}

function selectPriority(value) {
  document.getElementById('input-priority').value = value;
  document.querySelectorAll('.priority-btn').forEach(b => b.classList.toggle('active', b.dataset.value === value));
}
function setPrivacyFilter(filter) {
  privacyFilter = filter;
  document.querySelectorAll('.privacy-filter-btn').forEach(btn => btn.classList.toggle('active', btn.dataset.filter === filter));
  render();
}
function renderProjectSelect() {} 
function openProjectSettings() {}
async function logout() {
  if (confirm('確定要登出嗎？')) window.location.href = 'index.php?action=logout';
}
</script>
</body>
</html>
