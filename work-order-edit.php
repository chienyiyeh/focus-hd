<?php
/**
 * 工單新增 / 編輯頁面
 * work-order-edit.php
 *
 * 用法：
 *   新增（手動）：  work-order-edit.php
 *   從報價轉入：    work-order-edit.php?from_quote=123
 *   編輯已有工單：  work-order-edit.php?id=5
 */
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId   = $_SESSION['user_id'];
$username = $_SESSION['username'];

$workOrderId  = intval($_GET['id'] ?? 0);
$fromQuoteId  = intval($_GET['from_quote'] ?? 0);

// 如果是從報價單轉入，讀取報價單資料
$quote     = null;
$workOrder = null;

if ($fromQuoteId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM quotations WHERE id=?");
    $stmt->execute([$fromQuoteId]);
    $quote = $stmt->fetch();
    if (!$quote) { die('報價單不存在'); }

    // 防止重複轉換
    if ($quote['converted_to_work_order']) {
        header("Location: work-order-edit.php?id={$quote['converted_to_work_order']}&msg=already_converted");
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM quotation_items WHERE quotation_id=? ORDER BY item_order");
    $stmt->execute([$fromQuoteId]);
    $quote['items'] = $stmt->fetchAll();
}

if ($workOrderId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT wo.*, q.quotation_number
        FROM work_orders wo
        LEFT JOIN quotations q ON wo.quotation_id = q.id
        WHERE wo.id=?
    ");
    $stmt->execute([$workOrderId]);
    $workOrder = $stmt->fetch();
    if (!$workOrder) { die('工單不存在'); }

    // 載入流程紀錄
    $stmt = $db->prepare("SELECT * FROM job_logs WHERE doc_type='work_order' AND doc_id=? ORDER BY created_at DESC");
    $stmt->execute([$workOrderId]);
    $workOrder['logs'] = $stmt->fetchAll();

    // 若有來源報價品項
    if ($workOrder['quotation_id']) {
        $stmt = $db->prepare("SELECT * FROM quotation_items WHERE quotation_id=? ORDER BY item_order");
        $stmt->execute([$workOrder['quotation_id']]);
        $workOrder['quote_items'] = $stmt->fetchAll();
    }
}

$pageTitle = $workOrderId ? "工單 {$workOrder['work_order_number']}" : ($fromQuoteId ? '報價轉工單' : '新增工單');

$statusLabels = ['pending'=>'待製作','in_progress'=>'製作中','completed'=>'已完工','paused'=>'暫停'];
$statusColors = ['pending'=>'#9B8FD9','in_progress'=>'#E8763E','completed'=>'#8FBC8F','paused'=>'#A8A8A5'];
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($pageTitle); ?> - 蘋果印刷</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg: #F8F8F6;
  --surface: #FFFFFF;
  --surface2: #F3F3F1;
  --border: rgba(0,0,0,0.07);
  --border-strong: rgba(0,0,0,0.12);
  --text: #2A2A28;
  --text2: #737371;
  --text3: #A8A8A5;
  --crm: #2C5F2D;
  --crm-bg: #EBF4EC;
  --crm-light: #F4FAF4;
  --week: #9B8FD9;
  --focus: #E8763E;
  --done: #8FBC8F;
  --danger: #E24B4A;
  --r: 10px;
  --rl: 14px;
}

body { font-family: -apple-system, BlinkMacSystemFont, "Microsoft JhengHei", "Noto Sans TC", sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

/* NAV */
nav { background: var(--surface); border-bottom: 1px solid var(--border); padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 50; }
.nav-logo { font-size: 15px; font-weight: 700; color: var(--crm); }
.nav-tabs { display: flex; gap: 6px; }
.nav-tab { padding: 5px 14px; border-radius: 20px; font-size: 13px; border: 1px solid var(--border-strong); background: var(--surface); color: var(--text2); text-decoration: none; transition: all .12s; }
.nav-tab:hover { background: var(--surface2); }
.nav-right { display: flex; gap: 8px; align-items: center; }
.user-pill { font-size: 12px; color: var(--text2); background: var(--surface2); padding: 5px 12px; border-radius: var(--r); border: 1px solid var(--border); }

/* LAYOUT */
.page { max-width: 1100px; margin: 0 auto; padding: 24px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; gap: 12px; }
.page-title { font-size: 22px; font-weight: 600; color: var(--crm); display: flex; align-items: center; gap: 10px; }
.page-actions { display: flex; gap: 8px; }

.grid { display: grid; grid-template-columns: 1fr 360px; gap: 16px; align-items: start; }

/* CARDS */
.card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--rl); overflow: hidden; margin-bottom: 16px; }
.card-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 8px; }
.card-header h2 { font-size: 15px; font-weight: 600; color: var(--crm); flex: 1; }
.accent-bar { height: 3px; background: var(--crm); }
.card-body { padding: 20px; }

/* FORMS */
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px; }
.form-row.full { grid-template-columns: 1fr; }
.form-row.three { grid-template-columns: 1fr 1fr 1fr; }
.field { display: flex; flex-direction: column; }
.field label { font-size: 12px; font-weight: 600; color: var(--text2); margin-bottom: 6px; }
.field input, .field select, .field textarea {
  padding: 9px 12px; border: 1px solid var(--border-strong); border-radius: var(--r);
  font-family: inherit; font-size: 13px; color: var(--text); background: var(--surface);
  transition: border-color .12s;
}
.field input:focus, .field select:focus, .field textarea:focus { outline: none; border-color: var(--crm); }
.field textarea { resize: vertical; min-height: 80px; line-height: 1.5; }
.field-hint { font-size: 11px; color: var(--text3); margin-top: 4px; }

/* STATUS SELECTOR */
.status-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.status-opt {
  padding: 10px 12px; border: 1px solid var(--border-strong); border-radius: var(--r);
  font-size: 12px; font-weight: 500; cursor: pointer; text-align: center;
  transition: all .12s; background: var(--surface); font-family: inherit; color: var(--text2);
}
.status-opt.active-pending   { background: #F3F1FC; color: #524A8C; border-color: var(--week); }
.status-opt.active-in_progress { background: #FFF3ED; color: #B85828; border-color: var(--focus); }
.status-opt.active-completed { background: #F2F8F2; color: #4A6B4A; border-color: var(--done); }
.status-opt.active-paused    { background: var(--surface2); color: var(--text2); border-color: var(--border-strong); }
.status-opt:hover:not(.active-pending):not(.active-in_progress):not(.active-completed):not(.active-paused) { background: var(--surface2); }

/* QUOTE ITEMS TABLE */
.items-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.items-table th { text-align: left; padding: 8px 10px; color: var(--text3); font-size: 11px; font-weight: 600; border-bottom: 1px solid var(--border); }
.items-table td { padding: 10px; border-bottom: 1px solid var(--border); vertical-align: top; }
.items-table tr:last-child td { border-bottom: none; }
.spec-text { font-size: 11px; color: var(--text2); margin-top: 3px; }

/* LOG TIMELINE */
.log-list { list-style: none; }
.log-item { display: flex; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--border); }
.log-item:last-child { border-bottom: none; }
.log-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--crm); flex-shrink: 0; margin-top: 5px; }
.log-content { flex: 1; }
.log-note { font-size: 13px; color: var(--text); line-height: 1.4; }
.log-meta { font-size: 11px; color: var(--text3); margin-top: 3px; }

/* QUOTE ORIGIN BANNER */
.quote-banner {
  background: var(--crm-bg); border: 1px solid #A8CCA8; border-radius: var(--r);
  padding: 12px 16px; display: flex; align-items: center; gap: 12px; margin-bottom: 20px;
  font-size: 13px;
}
.quote-banner-icon { font-size: 20px; flex-shrink: 0; }
.quote-banner-text { flex: 1; color: var(--crm); }
.quote-banner-number { font-weight: 600; }

/* BUTTONS */
.btn { padding: 10px 20px; border: none; border-radius: var(--r); font-family: inherit; font-size: 13px; font-weight: 600; cursor: pointer; transition: all .12s; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
.btn-primary { background: var(--crm); color: #fff; }
.btn-primary:hover { background: #1F4420; }
.btn-secondary { background: var(--surface2); color: var(--text2); border: 1px solid var(--border-strong); }
.btn-secondary:hover { background: #E8E8E5; }
.btn-danger { background: #FFF3F2; color: var(--danger); border: 1px solid #FCA5A5; }
.btn-danger:hover { background: #FEE2E2; }
.btn-convert { background: var(--crm); color: #fff; font-size: 14px; padding: 12px 24px; }
.btn-convert:hover { background: #1F4420; }

/* STATUS BADGE */
.status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }

/* MSG BANNER */
.msg-banner { padding: 12px 16px; border-radius: var(--r); margin-bottom: 16px; font-size: 13px; }
.msg-success { background: #F2F8F2; border: 1px solid #A8CCA8; color: var(--crm); }
.msg-warning { background: #FFF8F0; border: 1px solid #FFCC88; color: #8A5A00; }

#toast { position: fixed; bottom: 28px; left: 50%; transform: translateX(-50%); background: #222; color: #fff; padding: 10px 24px; border-radius: 30px; font-size: 13px; font-weight: 500; opacity: 0; transition: opacity .2s, transform .2s; z-index: 999; pointer-events: none; white-space: nowrap; }
#toast.show { opacity: 1; transform: translateX(-50%) translateY(-8px); }
</style>
</head>
<body>

<nav>
  <div class="nav-logo">🍎 蘋果印刷管理系統</div>
  <div class="nav-tabs">
    <a href="index.php" class="nav-tab">📊 專注看板</a>
    <a href="crm.php" class="nav-tab">💼 CRM</a>
    <a href="quotation-edit.php" class="nav-tab">＋ 新增報價</a>
  </div>
  <div class="nav-right">
    <span class="user-pill">👤 <?php echo htmlspecialchars($username); ?></span>
    <a href="crm.php" class="btn btn-secondary" style="padding:5px 12px;font-size:12px;">← 返回</a>
  </div>
</nav>

<div class="page">

  <?php if (isset($_GET['msg'])): ?>
    <?php if ($_GET['msg'] === 'already_converted'): ?>
      <div class="msg-banner msg-warning">⚠️ 此報價單已轉換過工單，直接跳轉到現有工單。</div>
    <?php elseif ($_GET['msg'] === 'converted'): ?>
      <div class="msg-banner msg-success">✅ 報價單已成功轉換為工單！</div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="page-header">
    <div class="page-title">
      🏭 <?php echo htmlspecialchars($pageTitle); ?>
      <?php if ($workOrder): ?>
        <?php $st = $workOrder['production_status']; ?>
        <span class="status-badge" style="background:<?php echo $st==='completed'?'#F2F8F2':($st==='in_progress'?'#FFF3ED':($st==='paused'?'#F5F5F5':'#F3F1FC')); ?>; color:<?php echo $statusColors[$st]??'#666'; ?>">
          <?php echo $statusLabels[$st] ?? $st; ?>
        </span>
      <?php endif; ?>
    </div>
    <div class="page-actions">
      <?php if ($workOrder): ?>
        <button class="btn btn-danger" onclick="deleteWorkOrder(<?php echo $workOrderId; ?>)">🗑 刪除</button>
      <?php endif; ?>
      <button class="btn btn-primary" onclick="saveWorkOrder()" id="save-btn">
        <?php echo ($fromQuoteId && !$workOrderId) ? '💰 確認轉為工單' : '💾 儲存工單'; ?>
      </button>
    </div>
  </div>

  <?php if ($fromQuoteId && $quote): ?>
  <div class="quote-banner">
    <span class="quote-banner-icon">💰</span>
    <div class="quote-banner-text">
      來源報價單：<span class="quote-banner-number"><?php echo htmlspecialchars($quote['quotation_number']); ?></span>
      　客戶：<?php echo htmlspecialchars($quote['customer_name']); ?>
      　總金額：NT$ <?php echo number_format($quote['total_amount']); ?>
    </div>
    <a href="quotation-print.php?id=<?php echo $fromQuoteId; ?>" target="_blank" class="btn btn-secondary" style="font-size:12px;padding:5px 12px;">檢視報價單</a>
  </div>
  <?php endif; ?>

  <div class="grid">
    <!-- 左側：工單資料 -->
    <div>
      <!-- 客戶資訊 -->
      <div class="card">
        <div class="accent-bar"></div>
        <div class="card-header"><h2>👤 客戶資訊</h2></div>
        <div class="card-body">
          <div class="form-row">
            <div class="field">
              <label>客戶姓名 *</label>
              <input type="text" id="customer_name" value="<?php echo htmlspecialchars($workOrder['customer_name'] ?? $quote['customer_name'] ?? ''); ?>" placeholder="客戶名稱">
            </div>
            <div class="field">
              <label>公司名稱</label>
              <input type="text" id="customer_company" value="<?php echo htmlspecialchars($workOrder['customer_company'] ?? $quote['customer_company'] ?? ''); ?>" placeholder="公司（選填）">
            </div>
          </div>
          <div class="form-row full">
            <div class="field">
              <label>聯絡電話</label>
              <input type="text" id="customer_phone" value="<?php echo htmlspecialchars($workOrder['customer_phone'] ?? $quote['customer_phone'] ?? ''); ?>" placeholder="0912-345-678">
            </div>
          </div>
        </div>
      </div>

      <!-- 品項摘要（來自報價） -->
      <?php if (($workOrder && $workOrder['quote_items']) || ($quote && $quote['items'])): ?>
      <div class="card">
        <div class="accent-bar"></div>
        <div class="card-header"><h2>📋 報價品項明細</h2><span style="font-size:11px;color:var(--text3)">自動帶入，僅供參考</span></div>
        <div class="card-body" style="padding:0">
          <table class="items-table">
            <thead><tr>
              <th>品名</th><th>規格</th><th>數量</th><th>單價</th><th>小計</th>
            </tr></thead>
            <tbody>
              <?php $items = $workOrder['quote_items'] ?? $quote['items'] ?? []; ?>
              <?php foreach ($items as $item): ?>
              <tr>
                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                <td><div class="spec-text"><?php echo nl2br(htmlspecialchars($item['specification'] ?? '')); ?></div></td>
                <td><?php echo $item['quantity']; ?> <?php echo htmlspecialchars($item['unit'] ?? '張'); ?></td>
                <td>NT$ <?php echo number_format($item['unit_price']); ?></td>
                <td>NT$ <?php echo number_format($item['subtotal']); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <!-- 生產資訊 -->
      <div class="card">
        <div class="accent-bar"></div>
        <div class="card-header"><h2>🏭 生產資訊</h2></div>
        <div class="card-body">
          <div class="form-row full">
            <div class="field">
              <label>品項摘要</label>
              <input type="text" id="product_summary" value="<?php echo htmlspecialchars($workOrder['product_summary'] ?? ''); ?>" placeholder="例：圓形貼紙 500張、名片 200張">
              <span class="field-hint">若從報價轉入，系統會自動填入</span>
            </div>
          </div>
          <div class="form-row three">
            <div class="field">
              <label>總金額 (NT$)</label>
              <input type="number" id="total_amount" value="<?php echo $workOrder['total_amount'] ?? $quote['total_amount'] ?? 0; ?>" min="0">
            </div>
            <div class="field">
              <label>預計完工日期</label>
              <input type="date" id="due_date" value="<?php echo $workOrder['due_date'] ?? ''; ?>">
            </div>
            <div class="field">
              <label>負責人</label>
              <input type="text" id="assigned_to" value="<?php echo htmlspecialchars($workOrder['assigned_to'] ?? $username); ?>" placeholder="負責處理人員">
            </div>
          </div>
          <div class="form-row full">
            <div class="field">
              <label>生產備註</label>
              <textarea id="production_note" placeholder="紙張規格、印刷方式、加工項目、特殊要求…"><?php echo htmlspecialchars($workOrder['production_note'] ?? ''); ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- 流程紀錄（僅在編輯模式顯示） -->
      <?php if ($workOrder && !empty($workOrder['logs'])): ?>
      <div class="card">
        <div class="card-header"><h2>📝 流程紀錄</h2></div>
        <div class="card-body">
          <ul class="log-list">
            <?php foreach ($workOrder['logs'] as $log): ?>
            <li class="log-item">
              <div class="log-dot"></div>
              <div class="log-content">
                <div class="log-note"><?php echo htmlspecialchars($log['note'] ?? ''); ?></div>
                <div class="log-meta">
                  <?php echo htmlspecialchars($log['created_by'] ?? ''); ?>
                  　<?php echo date('Y/m/d H:i', strtotime($log['created_at'])); ?>
                </div>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- 右側：狀態 + 連結 -->
    <div>
      <!-- 工單狀態 -->
      <?php if ($workOrderId): ?>
      <div class="card">
        <div class="accent-bar" style="background:var(--week)"></div>
        <div class="card-header"><h2>🔄 工單狀態</h2></div>
        <div class="card-body">
          <div class="status-grid" id="status-grid">
            <?php $curStatus = $workOrder['production_status'] ?? 'pending'; ?>
            <?php foreach ($statusLabels as $key => $label): ?>
            <button class="status-opt <?php echo $curStatus===$key?"active-{$key}":''; ?>"
                    onclick="selectStatus('<?php echo $key; ?>')"
                    id="status-<?php echo $key; ?>">
              <?php echo ['pending'=>'⏳','in_progress'=>'⚙️','completed'=>'✅','paused'=>'⏸'][$key].' '.$label; ?>
            </button>
            <?php endforeach; ?>
          </div>
          <textarea id="status_note" placeholder="狀態備註（選填）" style="width:100%;margin-top:10px;padding:8px;border:1px solid var(--border-strong);border-radius:var(--r);font-size:12px;font-family:inherit;resize:vertical;min-height:50px"></textarea>
          <button class="btn btn-primary" style="width:100%;margin-top:8px" onclick="updateStatus()">更新狀態</button>
          <div style="font-size:11px;color:var(--text3);margin-top:8px">
            <?php if ($workOrder['started_at']): ?>開始：<?php echo date('Y/m/d H:i', strtotime($workOrder['started_at'])); ?><br><?php endif; ?>
            <?php if ($workOrder['completed_at']): ?>完工：<?php echo date('Y/m/d H:i', strtotime($workOrder['completed_at'])); ?><br><?php endif; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- 工單資訊卡 -->
      <?php if ($workOrder): ?>
      <div class="card">
        <div class="card-header"><h2>📄 工單資訊</h2></div>
        <div class="card-body">
          <table style="width:100%;font-size:13px;border-collapse:collapse">
            <tr><td style="padding:6px 0;color:var(--text2)">工單編號</td><td style="font-weight:600"><?php echo $workOrder['work_order_number']; ?></td></tr>
            <?php if ($workOrder['quotation_number']): ?>
            <tr><td style="padding:6px 0;color:var(--text2)">來源報價單</td><td><a href="quotation-print.php?id=<?php echo $workOrder['quotation_id']; ?>" target="_blank" style="color:var(--crm)"><?php echo $workOrder['quotation_number']; ?></a></td></tr>
            <?php endif; ?>
            <tr><td style="padding:6px 0;color:var(--text2)">建立時間</td><td><?php echo date('Y/m/d', strtotime($workOrder['created_at'])); ?></td></tr>
            <?php if ($workOrder['due_date']): ?>
            <tr>
              <td style="padding:6px 0;color:var(--text2)">交期</td>
              <td style="<?php echo strtotime($workOrder['due_date'])<time()&&$workOrder['production_status']!=='completed'?'color:var(--danger);font-weight:600':''; ?>">
                <?php echo date('Y/m/d', strtotime($workOrder['due_date'])); ?>
                <?php if (strtotime($workOrder['due_date'])<time()&&$workOrder['production_status']!=='completed'): ?> ⚠️ 已逾期<?php endif; ?>
              </td>
            </tr>
            <?php endif; ?>
            <tr><td style="padding:6px 0;color:var(--text2)">總金額</td><td style="font-weight:600;color:var(--crm)">NT$ <?php echo number_format($workOrder['total_amount']); ?></td></tr>
          </table>
        </div>
      </div>

      <!-- 下一步 -->
      <div class="card" style="background:var(--crm-light);border-color:#A8CCA8">
        <div class="card-header"><h2>⏭ 完工後下一步</h2></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:8px">
          <button class="btn btn-secondary" style="width:100%;justify-content:center;font-size:13px" onclick="toast('出貨系統開發中，即將推出！')">📦 建立出貨單</button>
          <button class="btn btn-secondary" style="width:100%;justify-content:center;font-size:13px" onclick="toast('發票系統開發中，即將推出！')">🧾 建立發票</button>
          <div style="font-size:11px;color:var(--text3);text-align:center">出貨與發票功能建構中</div>
        </div>
      </div>
      <?php endif; ?>

      <!-- 新增模式的提示 -->
      <?php if (!$workOrderId): ?>
      <div class="card" style="background:var(--crm-light);border-color:#A8CCA8">
        <div class="card-header"><h2>💡 關於工單</h2></div>
        <div class="card-body" style="font-size:13px;color:var(--text2);line-height:1.8">
          工單建立後可以：<br>
          ✅ 更新製作狀態<br>
          ✅ 記錄流程紀錄<br>
          ✅ 逾期自動提示<br>
          ✅ 未來串接出貨單
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div id="toast"></div>

<script>
const WORK_ORDER_ID = <?php echo $workOrderId ?: 'null'; ?>;
const FROM_QUOTE_ID = <?php echo $fromQuoteId ?: 'null'; ?>;
let currentStatus = '<?php echo $workOrder['production_status'] ?? 'pending'; ?>';

function toast(msg, duration=2500) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), duration);
}

function getFormData() {
  return {
    id: WORK_ORDER_ID,
    customer_name:    document.getElementById('customer_name').value.trim(),
    customer_company: document.getElementById('customer_company').value.trim(),
    customer_phone:   document.getElementById('customer_phone').value.trim(),
    product_summary:  document.getElementById('product_summary').value.trim(),
    total_amount:     parseFloat(document.getElementById('total_amount').value) || 0,
    due_date:         document.getElementById('due_date').value || null,
    production_note:  document.getElementById('production_note').value.trim(),
    assigned_to:      document.getElementById('assigned_to').value.trim(),
  };
}

async function saveWorkOrder() {
  const data = getFormData();
  if (!data.customer_name) { toast('請填寫客戶姓名！'); return; }

  const btn = document.getElementById('save-btn');
  btn.textContent = '儲存中…';
  btn.disabled = true;

  try {
    if (FROM_QUOTE_ID && !WORK_ORDER_ID) {
      // 報價轉工單
      const res = await fetch('api/work-orders.php?action=convert', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
          quotation_id:    FROM_QUOTE_ID,
          due_date:        data.due_date,
          production_note: data.production_note,
          assigned_to:     data.assigned_to,
        })
      });
      const json = await res.json();
      if (json.success) {
        toast('工單建立成功！跳轉中…');
        setTimeout(() => { window.location = `work-order-edit.php?id=${json.data.work_order_id}&msg=converted`; }, 1200);
      } else {
        toast('錯誤：' + json.error);
        btn.textContent = '💰 確認轉為工單'; btn.disabled = false;
      }
    } else {
      // 新增或更新
      const res = await fetch('api/work-orders.php?action=save', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(data)
      });
      const json = await res.json();
      if (json.success) {
        toast(WORK_ORDER_ID ? '已儲存 ✓' : '工單建立成功！跳轉中…');
        if (!WORK_ORDER_ID) {
          setTimeout(() => { window.location = `work-order-edit.php?id=${json.data.id}`; }, 1200);
        } else {
          btn.textContent = '💾 儲存工單'; btn.disabled = false;
        }
      } else {
        toast('錯誤：' + json.error);
        btn.textContent = '💾 儲存工單'; btn.disabled = false;
      }
    }
  } catch (e) {
    toast('網路錯誤，請重試');
    btn.textContent = '💾 儲存工單'; btn.disabled = false;
  }
}

function selectStatus(status) {
  currentStatus = status;
  document.querySelectorAll('.status-opt').forEach(b => {
    b.className = 'status-opt';
    const key = b.id.replace('status-','');
    if (key === status) b.classList.add('active-'+status);
  });
}

async function updateStatus() {
  if (!WORK_ORDER_ID) return;
  const note = document.getElementById('status_note').value.trim();

  try {
    const res = await fetch('api/work-orders.php?action=update_status', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({id: WORK_ORDER_ID, status: currentStatus, note})
    });
    const json = await res.json();
    if (json.success) {
      toast(json.message || '狀態已更新 ✓');
      setTimeout(() => location.reload(), 1000);
    } else {
      toast('錯誤：' + json.error);
    }
  } catch(e) {
    toast('網路錯誤');
  }
}

async function deleteWorkOrder(id) {
  if (!confirm('確定要刪除這張工單嗎？\n（若有來源報價單，狀態會一併還原）')) return;
  const res = await fetch(`api/work-orders.php?action=delete&id=${id}`);
  const json = await res.json();
  if (json.success) {
    toast('已刪除');
    setTimeout(() => { window.location = 'crm.php'; }, 1000);
  } else {
    toast('刪除失敗：' + json.error);
  }
}
</script>
</body>
</html>
