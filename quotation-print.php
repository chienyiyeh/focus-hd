<?php
/**
 * 報價單列印頁面
 * quotation-print.php
 */
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$quotationId = $_GET['id'] ?? null;
if (!$quotationId) {
    die('缺少報價單 ID');
}

// 取得報價單資料
$db = getDB();
$stmt = $db->prepare("SELECT * FROM quotations WHERE id = ?");
$stmt->execute([$quotationId]);
$quote = $stmt->fetch();

if (!$quote) {
    die('報價單不存在');
}

// 取得品項
$stmt = $db->prepare("SELECT * FROM quotation_items WHERE quotation_id = ? ORDER BY item_order");
$stmt->execute([$quotationId]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>報價單 - <?php echo $quote['quotation_number']; ?></title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
  font-family: "Microsoft JhengHei", sans-serif;
  background: #F5F5F5;
  padding: 2rem;
}

.page {
  max-width: 800px;
  margin: 0 auto;
  background: white;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  min-height: 1000px;
  position: relative;
}

.header {
  padding: 2rem 2.5rem;
  border-bottom: 2px solid #2C5F2D;
}

.header-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.company-info {
  flex: 1;
}

.company-name {
  font-size: 28px;
  font-weight: 500;
  color: #2C5F2D;
  margin-bottom: 4px;
}

.company-name-en {
  font-size: 13px;
  color: #666;
  margin-bottom: 12px;
}

.company-details {
  font-size: 13px;
  color: #666;
  line-height: 1.6;
}

.doc-title {
  text-align: right;
}

.doc-title-zh {
  font-size: 24px;
  font-weight: 500;
  color: #2C5F2D;
  margin-bottom: 8px;
}

.doc-title-en {
  font-size: 13px;
  color: #666;
}

.info-section {
  padding: 1.5rem 2.5rem;
  background: #F9F9F9;
  border-bottom: 1px solid #E0E0E0;
}

.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
}

.info-box {
  background: white;
  padding: 1rem;
  border-radius: 8px;
  border: 0.5px solid #E0E0E0;
}

.info-box-title {
  font-size: 13px;
  color: #666;
  margin-bottom: 12px;
}

.info-box-content {
  font-size: 13px;
  line-height: 1.6;
}

.info-box-content strong {
  font-size: 15px;
  font-weight: 500;
  display: block;
  margin-bottom: 6px;
}

.info-row {
  display: flex;
  justify-content: space-between;
  margin-bottom: 6px;
}

.info-label {
  color: #666;
}

.info-value {
  font-weight: 500;
}

.items-section {
  padding: 2rem 2.5rem;
}

.section-title {
  font-size: 15px;
  font-weight: 500;
  margin-bottom: 1rem;
  color: #333;
}

.items-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  margin-bottom: 2rem;
}

.items-table thead {
  background: #2C5F2D;
  color: white;
}

.items-table th {
  padding: 12px;
  text-align: left;
  font-weight: 500;
  border-right: 1px solid rgba(255,255,255,0.2);
}

.items-table th:last-child {
  border-right: none;
}

.items-table td {
  padding: 16px 12px;
  border-bottom: 1px solid #E0E0E0;
  vertical-align: top;
}

.items-table tbody tr:nth-child(even) {
  background: #F9F9F9;
}

.item-spec {
  color: #666;
  line-height: 1.5;
  white-space: pre-line;
}

.summary {
  display: flex;
  justify-content: flex-end;
  margin-top: 2rem;
}

.summary-box {
  width: 300px;
}

.summary-row {
  display: flex;
  justify-content: space-between;
  padding: 12px 16px;
  border-bottom: 1px solid #E0E0E0;
  font-size: 13px;
}

.summary-row.total {
  background: #2C5F2D;
  color: white;
  border-radius: 8px;
  margin-top: 8px;
  font-size: 15px;
  font-weight: 500;
  border: none;
}

.summary-row.total .summary-value {
  font-size: 20px;
}

.notes-section {
  padding: 0 2.5rem 2rem;
}

.notes-box {
  background: #FFF9E6;
  border: 1px solid #FFE4A3;
  border-radius: 8px;
  padding: 1.25rem;
}

.notes-title {
  font-size: 14px;
  font-weight: 500;
  color: #856404;
  margin-bottom: 12px;
}

.notes-content {
  font-size: 13px;
  color: #856404;
  line-height: 1.8;
}

.notes-content div {
  margin-bottom: 6px;
}

.footer {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  padding: 1.5rem 2.5rem;
  background: #F5F5F5;
  border-top: 1px solid #E0E0E0;
  font-size: 12px;
  color: #999;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.no-print {
  text-align: center;
  margin-top: 1.5rem;
}

.btn-print {
  padding: 12px 24px;
  background: #2C5F2D;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 15px;
  margin: 0 8px;
}

.btn-print:hover {
  background: #1F4420;
}

.btn-back {
  padding: 12px 24px;
  background: #F3F3F1;
  color: #2A2A28;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 15px;
  text-decoration: none;
  display: inline-block;
}

@media print {
  body {
    background: white;
    padding: 0;
  }
  
  .page {
    box-shadow: none;
    max-width: none;
  }
  
  .no-print {
    display: none;
  }
  
  .footer {
    position: relative;
  }
}
</style>
</head>
<body>

<div class="page">
  <!-- 頂部公司資訊 -->
  <div class="header">
    <div class="header-top">
      <div class="company-info">
        <div class="company-name">🍎 蘋果印刷設計工坊</div>
        <div class="company-name-en">Apple Printing & Design Studio</div>
        <div class="company-details">
          Tel: (02) 2345-6789 | Fax: (02) 2345-6790<br>
          Email: info@apple-printing.com<br>
          地址：台北市大安區○○路 123 號
        </div>
      </div>
      <div class="doc-title">
        <div class="doc-title-zh">報價單</div>
        <div class="doc-title-en">QUOTATION</div>
      </div>
    </div>
  </div>

  <!-- 報價單資訊 -->
  <div class="info-section">
    <div class="info-grid">
      <div class="info-box">
        <div class="info-box-title">客戶資訊</div>
        <div class="info-box-content">
          <strong><?php echo htmlspecialchars($quote['customer_name']); ?></strong>
          <?php if ($quote['customer_company']): ?>
          <?php echo htmlspecialchars($quote['customer_company']); ?><br>
          <?php endif; ?>
          <?php if ($quote['customer_phone']): ?>
          Tel: <?php echo htmlspecialchars($quote['customer_phone']); ?><br>
          <?php endif; ?>
          <?php if ($quote['customer_email']): ?>
          Email: <?php echo htmlspecialchars($quote['customer_email']); ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="info-box">
        <div class="info-box-title">報價資訊</div>
        <div class="info-box-content">
          <div class="info-row">
            <span class="info-label">報價單號：</span>
            <span class="info-value"><?php echo htmlspecialchars($quote['quotation_number']); ?></span>
          </div>
          <div class="info-row">
            <span class="info-label">報價日期：</span>
            <span class="info-value"><?php echo $quote['quote_date']; ?></span>
          </div>
          <div class="info-row">
            <span class="info-label">有效期限：</span>
            <span class="info-value" style="color: #E67E22; font-weight: 500;"><?php echo $quote['valid_days']; ?> 天</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- 產品明細 -->
  <div class="items-section">
    <div class="section-title">產品明細</div>
    
    <table class="items-table">
      <thead>
        <tr>
          <th style="width: 50px;">項次</th>
          <th style="width: 120px;">品名</th>
          <th style="width: 250px;">規格說明</th>
          <th style="width: 80px; text-align: center;">數量</th>
          <th style="width: 80px; text-align: right;">單價</th>
          <th style="width: 100px; text-align: right;">小計</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): ?>
        <tr>
          <td><?php echo $item['item_order']; ?></td>
          <td><?php echo htmlspecialchars($item['item_name']); ?></td>
          <td class="item-spec"><?php echo nl2br(htmlspecialchars($item['specification'])); ?></td>
          <td style="text-align: center;">
            <?php echo number_format($item['quantity']); ?> <?php echo htmlspecialchars($item['unit']); ?>
          </td>
          <td style="text-align: right;">NT$ <?php echo number_format($item['unit_price'], 2); ?></td>
          <td style="text-align: right; font-weight: 500;">NT$ <?php echo number_format($item['subtotal'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- 總計 -->
    <div class="summary">
      <div class="summary-box">
        <div class="summary-row">
          <span>小計：</span>
          <span>NT$ <?php echo number_format($quote['subtotal'], 2); ?></span>
        </div>
        <div class="summary-row">
          <span>稅額 (<?php echo $quote['tax_rate']; ?>%)：</span>
          <span>NT$ <?php echo number_format($quote['tax_amount'], 2); ?></span>
        </div>
        <div class="summary-row total">
          <span>總計金額：</span>
          <span class="summary-value">NT$ <?php echo number_format($quote['total_amount'], 2); ?></span>
        </div>
      </div>
    </div>
  </div>

  <!-- 備註說明 -->
  <div class="notes-section">
    <div class="notes-box">
      <div class="notes-title">📌 備註說明</div>
      <div class="notes-content">
        <div><strong>1. 交期：</strong>確認稿件後 3 個工作天（不含例假日）</div>
        <div><strong>2. 付款方式：</strong>交貨後付現、匯款或月結（需審核）</div>
        <div><strong>3. 打樣：</strong>如需打樣請提前告知，打樣費另計</div>
        <div><strong>4. 設計：</strong>客戶自備完稿檔案（AI、PDF 格式）</div>
        <div><strong>5. 其他：</strong>報價不含運費，外縣市運費另計</div>
        <?php if ($quote['notes']): ?>
        <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #FFE4A3;">
          <strong>補充說明：</strong><br>
          <?php echo nl2br(htmlspecialchars($quote['notes'])); ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- 頁尾 -->
  <div class="footer">
    <div>此報價單由系統自動生成 | 列印日期：<?php echo date('Y-m-d H:i'); ?></div>
    <div></div>
  </div>
</div>

<!-- 操作按鈕 -->
<div class="no-print">
  <a href="crm.php" class="btn-back">返回</a>
  <button onclick="window.print()" class="btn-print">🖨️ 列印報價單</button>
  <button onclick="sendEmail()" class="btn-print" style="background: #3B82F6;">📧 寄送 Email</button>
</div>

<script>
function sendEmail() {
  alert('Email 功能開發中...');
  // TODO: 實現 Email 發送功能
}
</script>

</body>
</html>
