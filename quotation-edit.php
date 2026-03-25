<?php
/**
 * 報價單新增/編輯頁面
 * quotation-edit.php
 */
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
$quotationId = $_GET['id'] ?? null;
$pageTitle = $quotationId ? '編輯報價單' : '新增報價單';
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $pageTitle; ?> - 蘋果印刷</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
  font-family: -apple-system, BlinkMacSystemFont, "Microsoft JhengHei", sans-serif;
  background: #F8F8F6;
  color: #2A2A28;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 2rem;
}

.header {
  background: white;
  padding: 1.5rem 2rem;
  border-radius: 12px;
  margin-bottom: 2rem;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.header h1 {
  font-size: 24px;
  font-weight: 500;
  color: #2C5F2D;
}

.btn-back {
  padding: 8px 16px;
  background: #F3F3F1;
  border: none;
  border-radius: 8px;
  color: #2A2A28;
  cursor: pointer;
  text-decoration: none;
  display: inline-block;
  font-size: 14px;
}

.btn-back:hover {
  background: #E8E8E5;
}

.form-card {
  background: white;
  padding: 2rem;
  border-radius: 12px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
  margin-bottom: 2rem;
}

.form-section {
  margin-bottom: 2rem;
}

.form-section:last-child {
  margin-bottom: 0;
}

.section-title {
  font-size: 18px;
  font-weight: 500;
  color: #2C5F2D;
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 2px solid #2C5F2D;
}

.form-row {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1rem;
  margin-bottom: 1rem;
}

.form-row.full {
  grid-template-columns: 1fr;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group label {
  font-size: 14px;
  font-weight: 500;
  color: #555;
  margin-bottom: 0.5rem;
}

.form-group label .required {
  color: #E74C3C;
  margin-left: 2px;
}

.form-group input,
.form-group select,
.form-group textarea {
  padding: 10px 12px;
  border: 1px solid #DDD;
  border-radius: 6px;
  font-size: 14px;
  font-family: inherit;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #2C5F2D;
  box-shadow: 0 0 0 3px rgba(44, 95, 45, 0.1);
}

.form-group textarea {
  resize: vertical;
  min-height: 80px;
}

/* 品項表格 */
.items-table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 1rem;
}

.items-table thead {
  background: #2C5F2D;
  color: white;
}

.items-table th {
  padding: 12px;
  text-align: left;
  font-weight: 500;
  font-size: 13px;
}

.items-table td {
  padding: 12px;
  border-bottom: 1px solid #EEE;
}

.items-table tbody tr:hover {
  background: #F9F9F9;
}

.items-table input,
.items-table textarea {
  width: 100%;
  padding: 8px;
  border: 1px solid #DDD;
  border-radius: 4px;
  font-size: 13px;
  font-family: inherit;
}

.items-table textarea {
  min-height: 60px;
  resize: vertical;
}

.items-table .col-order { width: 50px; }
.items-table .col-name { width: 150px; }
.items-table .col-spec { width: 250px; }
.items-table .col-qty { width: 100px; }
.items-table .col-unit { width: 80px; }
.items-table .col-price { width: 100px; }
.items-table .col-subtotal { width: 120px; }
.items-table .col-actions { width: 60px; text-align: center; }

.btn-add-item {
  padding: 10px 20px;
  background: #2C5F2D;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 500;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.btn-add-item:hover {
  background: #1F4420;
}

.btn-remove-item {
  padding: 6px 12px;
  background: #FEE2E2;
  color: #991B1B;
  border: 1px solid #FCA5A5;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
}

.btn-remove-item:hover {
  background: #FEF2F2;
}

/* 總計區域 */
.summary-box {
  background: #F9F9F9;
  padding: 1.5rem;
  border-radius: 8px;
  margin-top: 1rem;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  font-size: 14px;
}

.summary-row.total {
  border-top: 2px solid #2C5F2D;
  margin-top: 8px;
  padding-top: 12px;
  font-size: 18px;
  font-weight: 500;
  color: #2C5F2D;
}

/* 操作按鈕 */
.form-actions {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
  padding-top: 1rem;
  border-top: 1px solid #EEE;
}

.btn-primary {
  padding: 12px 24px;
  background: #2C5F2D;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 15px;
  font-weight: 500;
}

.btn-primary:hover {
  background: #1F4420;
}

.btn-secondary {
  padding: 12px 24px;
  background: #F3F3F1;
  color: #2A2A28;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 15px;
}

.btn-secondary:hover {
  background: #E8E8E5;
}

.loading {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0,0,0,0.5);
  z-index: 9999;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 18px;
}

.loading.show {
  display: flex;
}

.customer-select-wrapper {
  display: flex;
  gap: 8px;
}

.customer-select-wrapper select {
  flex: 1;
}

.customer-select-wrapper button {
  padding: 10px 16px;
  background: #2C5F2D;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 13px;
  white-space: nowrap;
}
</style>
</head>
<body>

<div class="loading" id="loading">儲存中...</div>

<div class="container">
  <div class="header">
    <h1>🍎 <?php echo $pageTitle; ?></h1>
    <a href="crm.php" class="btn-back">← 返回列表</a>
  </div>

  <form id="quotationForm">
    <input type="hidden" id="quotationId" value="<?php echo $quotationId ?? ''; ?>">
    
    <!-- 客戶資訊 -->
    <div class="form-card">
      <div class="form-section">
        <div class="section-title">📋 客戶資訊</div>
        
        <div class="form-row">
          <div class="form-group">
            <label>選擇現有客戶</label>
            <div class="customer-select-wrapper">
              <select id="customerSelect">
                <option value="">-- 新客戶或手動輸入 --</option>
              </select>
              <button type="button" onclick="loadCustomerData()">載入資料</button>
            </div>
          </div>
          <div class="form-group">
            <label>客戶姓名 <span class="required">*</span></label>
            <input type="text" id="customerName" required>
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label>公司名稱</label>
            <input type="text" id="customerCompany">
          </div>
          <div class="form-group">
            <label>聯絡電話</label>
            <input type="tel" id="customerPhone">
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label>Email</label>
            <input type="email" id="customerEmail">
          </div>
          <div class="form-group">
            <label>報價日期</label>
            <input type="date" id="quoteDate" value="<?php echo date('Y-m-d'); ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- 產品明細 -->
    <div class="form-card">
      <div class="form-section">
        <div class="section-title">📦 產品明細</div>
        
        <table class="items-table">
          <thead>
            <tr>
              <th class="col-order">#</th>
              <th class="col-name">品項名稱 <span class="required">*</span></th>
              <th class="col-spec">規格說明</th>
              <th class="col-qty">數量 <span class="required">*</span></th>
              <th class="col-unit">單位</th>
              <th class="col-price">單價 <span class="required">*</span></th>
              <th class="col-subtotal">小計</th>
              <th class="col-actions">操作</th>
            </tr>
          </thead>
          <tbody id="itemsTableBody">
            <!-- 品項將由 JS 動態生成 -->
          </tbody>
        </table>
        
        <button type="button" class="btn-add-item" onclick="addItem()">
          ➕ 新增品項
        </button>
      </div>
      
      <!-- 總計 -->
      <div class="summary-box">
        <div class="summary-row">
          <span>小計：</span>
          <span id="summarySubtotal">NT$ 0</span>
        </div>
        <div class="summary-row">
          <span>稅額 (5%)：</span>
          <span id="summaryTax">NT$ 0</span>
        </div>
        <div class="summary-row total">
          <span>總計金額：</span>
          <span id="summaryTotal">NT$ 0</span>
        </div>
      </div>
    </div>

    <!-- 備註 -->
    <div class="form-card">
      <div class="form-section">
        <div class="section-title">📝 備註說明</div>
        <div class="form-row full">
          <div class="form-group">
            <textarea id="notes" placeholder="輸入報價備註..."></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- 操作按鈕 -->
    <div class="form-card">
      <div class="form-actions">
        <button type="button" class="btn-secondary" onclick="window.location.href='crm.php'">取消</button>
        <button type="submit" class="btn-primary">💾 儲存報價單</button>
      </div>
    </div>
  </form>
</div>

<script>
let itemCounter = 0;
let customers = [];

// 頁面載入時
document.addEventListener('DOMContentLoaded', async function() {
  // 載入客戶列表
  await loadCustomers();
  
  // 如果是編輯模式，載入報價單資料
  const quotationId = document.getElementById('quotationId').value;
  if (quotationId) {
    await loadQuotation(quotationId);
  } else {
    // 新增模式，添加一個空品項
    addItem();
  }
});

// 載入客戶列表
async function loadCustomers() {
  try {
    const response = await fetch('api/quotations.php?action=get_customers');
    const data = await response.json();
    
    if (data.success) {
      customers = data.data.customers;
      const select = document.getElementById('customerSelect');
      
      customers.forEach(customer => {
        const option = document.createElement('option');
        option.value = customer.id;
        option.textContent = `${customer.name}${customer.company ? ' - ' + customer.company : ''}`;
        select.appendChild(option);
      });
    }
  } catch (error) {
    console.error('載入客戶列表失敗:', error);
  }
}

// 載入客戶資料到表單
function loadCustomerData() {
  const customerId = document.getElementById('customerSelect').value;
  if (!customerId) return;
  
  const customer = customers.find(c => c.id == customerId);
  if (!customer) return;
  
  document.getElementById('customerName').value = customer.name || '';
  document.getElementById('customerCompany').value = customer.company || '';
  document.getElementById('customerPhone').value = customer.phone || '';
  document.getElementById('customerEmail').value = customer.email || '';
}

// 載入報價單
async function loadQuotation(id) {
  try {
    const response = await fetch(`api/quotations.php?action=get&id=${id}`);
    const data = await response.json();
    
    if (data.success) {
      const quote = data.data.quotation;
      
      // 填充基本資料
      document.getElementById('customerName').value = quote.customer_name || '';
      document.getElementById('customerCompany').value = quote.customer_company || '';
      document.getElementById('customerPhone').value = quote.customer_phone || '';
      document.getElementById('customerEmail').value = quote.customer_email || '';
      document.getElementById('quoteDate').value = quote.quote_date || '';
      document.getElementById('notes').value = quote.notes || '';
      
      // 填充品項
      if (quote.items && quote.items.length > 0) {
        quote.items.forEach(item => {
          addItem(item);
        });
      } else {
        addItem();
      }
    }
  } catch (error) {
    console.error('載入報價單失敗:', error);
    alert('載入報價單失敗');
  }
}

// 新增品項
function addItem(data = null) {
  itemCounter++;
  const tbody = document.getElementById('itemsTableBody');
  const row = tbody.insertRow();
  row.dataset.itemId = itemCounter;
  
  row.innerHTML = `
    <td class="col-order">${tbody.rows.length}</td>
    <td class="col-name">
      <input type="text" class="item-name" value="${data?.item_name || ''}" required 
             placeholder="例：名片印刷">
    </td>
    <td class="col-spec">
      <textarea class="item-spec" placeholder="例：9x5cm 雙面彩色\n300磅銅版紙\n單面上霧膜">${data?.specification || ''}</textarea>
    </td>
    <td class="col-qty">
      <input type="number" class="item-qty" value="${data?.quantity || 1}" min="1" required 
             onchange="calculateRow(this)">
    </td>
    <td class="col-unit">
      <input type="text" class="item-unit" value="${data?.unit || '張'}" 
             placeholder="張/本/份">
    </td>
    <td class="col-price">
      <input type="number" class="item-price" value="${data?.unit_price || 0}" min="0" step="0.01" required 
             onchange="calculateRow(this)">
    </td>
    <td class="col-subtotal">
      <span class="item-subtotal">NT$ ${data?.subtotal || 0}</span>
    </td>
    <td class="col-actions">
      <button type="button" class="btn-remove-item" onclick="removeItem(this)">刪除</button>
    </td>
  `;
  
  if (data) {
    calculateRow(row.querySelector('.item-qty'));
  }
  
  updateOrderNumbers();
}

// 移除品項
function removeItem(btn) {
  const row = btn.closest('tr');
  row.remove();
  updateOrderNumbers();
  calculateTotal();
}

// 更新項次編號
function updateOrderNumbers() {
  const rows = document.querySelectorAll('#itemsTableBody tr');
  rows.forEach((row, index) => {
    row.cells[0].textContent = index + 1;
  });
}

// 計算單行小計
function calculateRow(input) {
  const row = input.closest('tr');
  const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
  const price = parseFloat(row.querySelector('.item-price').value) || 0;
  const subtotal = qty * price;
  
  row.querySelector('.item-subtotal').textContent = `NT$ ${subtotal.toLocaleString('zh-TW', {minimumFractionDigits: 0, maximumFractionDigits: 2})}`;
  
  calculateTotal();
}

// 計算總計
function calculateTotal() {
  const rows = document.querySelectorAll('#itemsTableBody tr');
  let subtotal = 0;
  
  rows.forEach(row => {
    const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
    const price = parseFloat(row.querySelector('.item-price').value) || 0;
    subtotal += qty * price;
  });
  
  const taxRate = 5;
  const taxAmount = Math.round(subtotal * taxRate / 100);
  const total = subtotal + taxAmount;
  
  document.getElementById('summarySubtotal').textContent = `NT$ ${subtotal.toLocaleString('zh-TW')}`;
  document.getElementById('summaryTax').textContent = `NT$ ${taxAmount.toLocaleString('zh-TW')}`;
  document.getElementById('summaryTotal').textContent = `NT$ ${total.toLocaleString('zh-TW')}`;
}

// 提交表單
document.getElementById('quotationForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  
  // 收集品項資料
  const rows = document.querySelectorAll('#itemsTableBody tr');
  const items = [];
  
  for (let row of rows) {
    const item = {
      item_name: row.querySelector('.item-name').value.trim(),
      specification: row.querySelector('.item-spec').value.trim(),
      quantity: parseFloat(row.querySelector('.item-qty').value) || 0,
      unit: row.querySelector('.item-unit').value.trim() || '張',
      unit_price: parseFloat(row.querySelector('.item-price').value) || 0
    };
    
    if (!item.item_name) {
      alert('請填寫品項名稱');
      return;
    }
    
    if (item.quantity <= 0) {
      alert('數量必須大於 0');
      return;
    }
    
    if (item.unit_price < 0) {
      alert('單價不能為負數');
      return;
    }
    
    items.push(item);
  }
  
  if (items.length === 0) {
    alert('至少需要一個品項');
    return;
  }
  
  // 收集表單資料
  const formData = {
    id: document.getElementById('quotationId').value || null,
    customer_id: document.getElementById('customerSelect').value || null,
    customer_name: document.getElementById('customerName').value.trim(),
    customer_company: document.getElementById('customerCompany').value.trim(),
    customer_phone: document.getElementById('customerPhone').value.trim(),
    customer_email: document.getElementById('customerEmail').value.trim(),
    quote_date: document.getElementById('quoteDate').value,
    valid_days: 7,
    items: items,
    notes: document.getElementById('notes').value.trim()
  };
  
  if (!formData.customer_name) {
    alert('請填寫客戶姓名');
    return;
  }
  
  // 顯示載入中
  document.getElementById('loading').classList.add('show');
  
  try {
    const response = await fetch('api/quotations.php?action=save', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(formData)
    });
    
    const data = await response.json();
    
    if (data.success) {
      alert(data.message);
      window.location.href = 'crm.php';
    } else {
      alert('儲存失敗：' + data.message);
    }
  } catch (error) {
    console.error('儲存失敗:', error);
    alert('儲存失敗，請稍後再試');
  } finally {
    document.getElementById('loading').classList.remove('show');
  }
});
</script>

</body>
</html>
