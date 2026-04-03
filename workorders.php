<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: auth.php');
    exit;
}
$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head><?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: auth.php');
    exit;
}
$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 工單管理 - FOCUS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; padding-bottom: 90px; }
        .container { max-width: 1400px; margin: 0 auto; }
        
        /* 頂部 */
        .header { background: white; padding: 16px 20px; border-radius: 12px; margin-bottom: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 20px; display: flex; align-items: center; gap: 8px; }
        .back-btn { padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .back-btn:hover { background: #5568d3; transform: translateY(-1px); }
        
        /* 底部導航 */
        .bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; background: white; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-around; padding: 8px 0; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); z-index: 1000; }
        .nav-item { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 8px; cursor: pointer; border: none; background: none; color: #64748b; font-size: 11px; transition: all 0.2s; position: relative; }
        .nav-item-icon { font-size: 22px; }
        .nav-item.active { color: #667eea; }
        .nav-badge { position: absolute; top: 4px; right: 20px; background: #EF4444; color: white; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 10px; }
        
        /* 工單卡片 */
        .wo-card { background: white; border-radius: 12px; padding: 16px; margin-bottom: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); cursor: pointer; transition: all 0.2s; }
        .wo-card:active { transform: scale(0.98); }
        .wo-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .wo-number { font-size: 16px; font-weight: 700; color: #667eea; }
        .wo-date { font-size: 12px; color: #94a3b8; }
        .wo-customer { font-size: 14px; color: #1e293b; margin-bottom: 4px; font-weight: 600; }
        .wo-product { font-size: 13px; color: #64748b; margin-bottom: 8px; }
        .wo-footer { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .wo-price { font-size: 16px; font-weight: 700; color: #10b981; }
        .wo-status { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-pending { background: #FEF3C7; color: #92400E; }
        .status-production { background: #DBEAFE; color: #1E40AF; }
        .status-completed { background: #D1FAE5; color: #065F46; }
        .status-shipped { background: #E0E7FF; color: #3730A3; }
        .status-invoiced { background: #F3E8FF; color: #6B21A8; }
        .priority-high { border-left: 4px solid #EF4444; }
        .priority-urgent { border-left: 4px solid #DC2626; background: linear-gradient(to right, #FEF2F2 0%, white 20%); }
        
        /* 空狀態 */
        .empty-state { background: white; border-radius: 12px; padding: 60px 20px; text-align: center; color: #64748b; }
        .empty-icon { font-size: 48px; margin-bottom: 16px; }
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; padding: 20px; overflow-y: auto; }
        .modal.active { display: block; }
        .modal-content { background: white; border-radius: 12px; max-width: 600px; margin: 20px auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .modal-header { padding: 20px; border-bottom: 2px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        .modal-title { font-size: 18px; font-weight: 700; }
        .modal-close { width: 32px; height: 32px; border-radius: 50%; background: #f1f5f9; border: none; font-size: 20px; cursor: pointer; }
        .modal-body { padding: 20px; }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; font-family: inherit; }
        .form-textarea { resize: vertical; min-height: 80px; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        .btn-success { background: #10b981; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        
        /* 統計卡片 */
        .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 16px; }
        .stat-card { background: white; padding: 16px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .stat-label { font-size: 12px; color: #64748b; margin-bottom: 4px; }
        .stat-value { font-size: 24px; font-weight: 700; color: #1e293b; }
        
        .loading { text-align: center; padding: 40px; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 工單管理</h1>
            <a href="index.php" class="back-btn">← 返回看板</a>
        </div>
        
        <!-- 全部工單 -->
        <div id="tab-all" class="tab-content active">
            <div id="all-list"></div>
        </div>
        
        <!-- 製作中 -->
        <div id="tab-production" class="tab-content">
            <div id="production-list"></div>
        </div>
        
        <!-- 待出貨 -->
        <div id="tab-ship" class="tab-content">
            <div id="ship-list"></div>
        </div>
        
        <!-- 統計 -->
        <div id="tab-stats" class="tab-content">
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-label">待處理</div><div class="stat-value" id="stat-pending">-</div></div>
                <div class="stat-card"><div class="stat-label">製作中</div><div class="stat-value" id="stat-production">-</div></div>
                <div class="stat-card"><div class="stat-label">已完成</div><div class="stat-value" id="stat-completed">-</div></div>
                <div class="stat-card"><div class="stat-label">已出貨</div><div class="stat-value" id="stat-shipped">-</div></div>
            </div>
        </div>
    </div>
    
    <!-- 底部導航 -->
    <div class="bottom-nav">
        <button class="nav-item active" onclick="switchTab('all')"><div class="nav-item-icon">📋</div><div>全部</div></button>
        <button class="nav-item" onclick="switchTab('production')"><div class="nav-item-icon">🔄</div><div>製作中</div></button>
        <button class="nav-item" onclick="switchTab('ship')"><div class="nav-item-icon">📦</div><div>待出貨</div></button>
        <button class="nav-item" onclick="switchTab('stats')"><div class="nav-item-icon">📊</div><div>統計</div></button>
    </div>
    
    <!-- 工單詳情 Modal -->
    <div class="modal" id="woModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">工單詳情</h2>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>
    
    <script>
        const API = 'workorders-api.php';
        let allWOs = [];
        let currentTab = 'all';
        
        const statusMap = {
            'pending': { text: '待處理', class: 'pending' },
            'production': { text: '製作中', class: 'production' },
            'completed': { text: '已完成', class: 'completed' },
            'shipped': { text: '已出貨', class: 'shipped' },
            'invoiced': { text: '已開發票', class: 'invoiced' }
        };
        
        const priorityMap = {
            'low': '低',
            'normal': '一般',
            'high': '高',
            'urgent': '緊急'
        };
        
        document.addEventListener('DOMContentLoaded', () => {
            loadWorkOrders();
            loadStats();
        });
        
        function switchTab(tab) {
            currentTab = tab;
            document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
            event.target.closest('.nav-item').classList.add('active');
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById('tab-' + tab).classList.add('active');
        }
        
        async function loadWorkOrders() {
            try {
                const res = await fetch(`${API}?action=list&limit=100`);
                const data = await res.json();
                
                if (data.success) {
                    allWOs = data.data;
                    renderAll();
                    renderProduction();
                    renderShip();
                }
            } catch (err) {
                console.error('載入失敗:', err);
            }
        }
        
        async function loadStats() {
            try {
                const res = await fetch(`${API}?action=stats`);
                const data = await res.json();
                
                if (data.success) {
                    document.getElementById('stat-pending').textContent = data.data.pending;
                    document.getElementById('stat-production').textContent = data.data.production;
                    document.getElementById('stat-completed').textContent = data.data.completed;
                    document.getElementById('stat-shipped').textContent = data.data.shipped;
                }
            } catch (err) {
                console.error('載入統計失敗:', err);
            }
        }
        
        function renderAll() {
            const container = document.getElementById('all-list');
            if (allWOs.length === 0) {
                container.innerHTML = '<div class="empty-state"><div class="empty-icon">📋</div><div>目前沒有工單</div></div>';
                return;
            }
            container.innerHTML = allWOs.map(wo => createWOCard(wo)).join('');
        }
        
        function renderProduction() {
            const wos = allWOs.filter(w => w.status === 'production');
            const container = document.getElementById('production-list');
            if (wos.length === 0) {
                container.innerHTML = '<div class="empty-state"><div class="empty-icon">🔄</div><div>沒有製作中的工單</div></div>';
                return;
            }
            container.innerHTML = wos.map(wo => createWOCard(wo)).join('');
        }
        
        function renderShip() {
            const wos = allWOs.filter(w => w.status === 'completed');
            const container = document.getElementById('ship-list');
            if (wos.length === 0) {
                container.innerHTML = '<div class="empty-state"><div class="empty-icon">📦</div><div>沒有待出貨的工單</div></div>';
                return;
            }
            container.innerHTML = wos.map(wo => createWOCard(wo)).join('');
        }
        
        function createWOCard(wo) {
            const status = statusMap[wo.status];
            const priorityClass = wo.priority === 'high' || wo.priority === 'urgent' ? 'priority-' + wo.priority : '';
            
            return `
                <div class="wo-card ${priorityClass}" onclick="showWO(${wo.id})">
                    <div class="wo-header">
                        <div class="wo-number">${wo.wo_number}</div>
                        <div class="wo-date">${new Date(wo.created_at).toLocaleDateString('zh-TW')}</div>
                    </div>
                    <div class="wo-customer">${wo.customer_name}${wo.company ? ' - ' + wo.company : ''}</div>
                    <div class="wo-product">${wo.product_name}</div>
                    <div class="wo-footer">
                        <div class="wo-price">NT$ ${parseFloat(wo.total_amount).toLocaleString()}</div>
                        <div class="wo-status status-${status.class}">${status.text}</div>
                    </div>
                </div>
            `;
        }
        
        async function showWO(id) {
            const modal = document.getElementById('woModal');
            const modalBody = document.getElementById('modalBody');
            const modalTitle = document.getElementById('modalTitle');
            
            modal.classList.add('active');
            modalBody.innerHTML = '<div class="loading">載入中...</div>';
            
            try {
                const res = await fetch(`${API}?action=detail&wo_id=${id}`);
                const data = await res.json();
                
                if (data.success) {
                    const wo = data.data;
                    modalTitle.textContent = wo.wo_number;
                    
                    const specs = wo.product_specs || [];
                    const files = wo.file_urls || [];
                    
                    modalBody.innerHTML = `
                        <div class="form-group">
                            <label class="form-label">狀態</label>
                            <select class="form-select" id="status-select">
                                <option value="pending" ${wo.status === 'pending' ? 'selected' : ''}>待處理</option>
                                <option value="production" ${wo.status === 'production' ? 'selected' : ''}>製作中</option>
                                <option value="completed" ${wo.status === 'completed' ? 'selected' : ''}>已完成</option>
                                <option value="shipped" ${wo.status === 'shipped' ? 'selected' : ''}>已出貨</option>
                                <option value="invoiced" ${wo.status === 'invoiced' ? 'selected' : ''}>已開發票</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">客戶資訊</label>
                            <div style="background:#f8fafc;padding:12px;border-radius:8px;">
                                <div><strong>姓名：</strong>${wo.customer_name}</div>
                                ${wo.company ? `<div><strong>公司：</strong>${wo.company}</div>` : ''}
                                ${wo.phone ? `<div><strong>電話：</strong>${wo.phone}</div>` : ''}
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">商品規格</label>
                            <div style="background:#f8fafc;padding:12px;border-radius:8px;">
                                <div style="font-weight:600;margin-bottom:8px;">${wo.product_name}</div>
                                ${specs.map(s => `<div style="font-size:13px;"><strong>${s.label}:</strong> ${s.value}</div>`).join('')}
                                ${files.map(f => `<div style="margin-top:8px;"><a href="${f.url}" target="_blank" style="color:#667eea;">📎 ${f.label}</a></div>`).join('')}
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">生產備註</label>
                            <textarea class="form-textarea" id="notes-input" placeholder="記錄生產注意事項...">${wo.production_notes || ''}</textarea>
                        </div>
                        
                        ${wo.status === 'completed' || wo.status === 'shipped' ? `
                        <div class="form-group">
                            <label class="form-label">出貨方式</label>
                            <select class="form-select" id="shipping-method">
                                <option value="">請選擇</option>
                                <option value="pickup" ${wo.shipping_method === 'pickup' ? 'selected' : ''}>自取</option>
                                <option value="delivery" ${wo.shipping_method === 'delivery' ? 'selected' : ''}>宅配</option>
                                <option value="self_delivery" ${wo.shipping_method === 'self_delivery' ? 'selected' : ''}>自送</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">物流單號</label>
                            <input type="text" class="form-input" id="tracking-input" value="${wo.tracking_number || ''}" placeholder="填寫宅配單號...">
                        </div>
                        ` : ''}
                        
                        <div style="display:flex;gap:8px;margin-top:20px;">
                            <button class="btn btn-primary" onclick="updateWO(${wo.id})" style="flex:1;">更新工單</button>
                        </div>
                    `;
                }
            } catch (err) {
                modalBody.innerHTML = '<div class="empty-state">載入失敗</div>';
            }
        }
        
        async function updateWO(id) {
            const status = document.getElementById('status-select').value;
            const notes = document.getElementById('notes-input')?.value;
            const shippingMethod = document.getElementById('shipping-method')?.value;
            const tracking = document.getElementById('tracking-input')?.value;
            
            const data = {
                wo_id: id,
                status: status,
                production_notes: notes
            };
            
            if (shippingMethod) data.shipping_method = shippingMethod;
            if (tracking) data.tracking_number = tracking;
            
            try {
                const res = await fetch(`${API}?action=update`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await res.json();
                
                if (result.success) {
                    alert('✓ 更新成功');
                    closeModal();
                    loadWorkOrders();
                    loadStats();
                } else {
                    alert('更新失敗: ' + result.error);
                }
            } catch (err) {
                alert('更新失敗');
            }
        }
        
        function closeModal() {
            document.getElementById('woModal').classList.remove('active');
        }
    </script>
</body>
</html>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 工單管理 - FOCUS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; padding-bottom: 90px; }
        .container { max-width: 1400px; margin: 0 auto; }
        
        /* 頂部 */
        .header { background: white; padding: 16px 20px; border-radius: 12px; margin-bottom: 16px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 20px; display: flex; align-items: center; gap: 8px; }
        .back-btn { padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .back-btn:hover { background: #5568d3; transform: translateY(-1px); }
        
        /* 底部導航 */
        .bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; background: white; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-around; padding: 8px 0; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); z-index: 1000; }
        .nav-item { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 8px; cursor: pointer; border: none; background: none; color: #64748b; font-size: 11px; transition: all 0.2s; position: relative; }
        .nav-item-icon { font-size: 22px; }
        .nav-item.active { color: #667eea; }
        .nav-badge { position: absolute; top: 4px; right: 20px; background: #EF4444; color: white; font-size: 10px; font-weight: 700; padding: 2px 6px; border-radius: 10px; }
        
        /* 工單卡片 */
        .wo-card { background: white; border-radius: 12px; padding: 16px; margin-bottom: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); cursor: pointer; transition: all 0.2s; }
        .wo-card:active { transform: scale(0.98); }
        .wo-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .wo-number { font-size: 16px; font-weight: 700; color: #667eea; }
        .wo-date { font-size: 12px; color: #94a3b8; }
        .wo-customer { font-size: 14px; color: #1e293b; margin-bottom: 4px; font-weight: 600; }
        .wo-product { font-size: 13px; color: #64748b; margin-bottom: 8px; }
        .wo-footer { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .wo-price { font-size: 16px; font-weight: 700; color: #10b981; }
        .wo-status { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-pending { background: #FEF3C7; color: #92400E; }
        .status-production { background: #DBEAFE; color: #1E40AF; }
        .status-completed { background: #D1FAE5; color: #065F46; }
        .status-shipped { background: #E0E7FF; color: #3730A3; }
        .status-invoiced { background: #F3E8FF; color: #6B21A8; }
        .priority-high { border-left: 4px solid #EF4444; }
        .priority-urgent { border-left: 4px solid #DC2626; background: linear-gradient(to right, #FEF2F2 0%, white 20%); }
        
        /* 空狀態 */
        .empty-state { background: white; border-radius: 12px; padding: 60px 20px; text-align: center; color: #64748b; }
        .empty-icon { font-size: 48px; margin-bottom: 16px; }
        
        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; padding: 20px; overflow-y: auto; }
        .modal.active { display: block; }
        .modal-content { background: white; border-radius: 12px; max-width: 600px; margin: 20px auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .modal-header { padding: 20px; border-bottom: 2px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center; }
        .modal-title { font-size: 18px; font-weight: 700; }
        .modal-close { width: 32px; height: 32px; border-radius: 50%; background: #f1f5f9; border: none; font-size: 20px; cursor: pointer; }
        .modal-body { padding: 20px; }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; font-family: inherit; }
        .form-textarea { resize: vertical; min-height: 80px; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        .btn-success { background: #10b981; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        
        /* 統計卡片 */
        .stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 16px; }
        .stat-card { background: white; padding: 16px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .stat-label { font-size: 12px; color: #64748b; margin-bottom: 4px; }
        .stat-value { font-size: 24px; font-weight: 700; color: #1e293b; }
        
        .loading { text-align: center; padding: 40px; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 工單管理</h1>
            <a href="index.php" class="back-btn">← 返回看板</a>
        </div>
        
        <!-- 全部工單 -->
        <div id="tab-all" class="tab-content active">
            <div id="all-list"></div>
        </div>
        
        <!-- 製作中 -->
        <div id="tab-production" class="tab-content">
            <div id="production-list"></div>
        </div>
        
        <!-- 待出貨 -->
        <div id="tab-ship" class="tab-content">
            <div id="ship-list"></div>
        </div>
        
        <!-- 統計 -->
        <div id="tab-stats" class="tab-content">
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-label">待處理</div><div class="stat-value" id="stat-pending">-</div></div>
                <div class="stat-card"><div class="stat-label">製作中</div><div class="stat-value" id="stat-production">-</div></div>
                <div class="stat-card"><div class="stat-label">已完成</div><div class="stat-value" id="stat-completed">-</div></div>
                <div class="stat-card"><div class="stat-label">已出貨</div><div class="stat-value" id="stat-shipped">-</div></div>
            </div>
        </div>
    </div>
    
    <!-- 底部導航 -->
    <div class="bottom-nav">
        <button class="nav-item active" onclick="switchTab('all')"><div class="nav-item-icon">📋</div><div>全部</div></button>
        <button class="nav-item" onclick="switchTab('production')"><div class="nav-item-icon">🔄</div><div>製作中</div></button>
        <button class="nav-item" onclick="switchTab('ship')"><div class="nav-item-icon">📦</div><div>待出貨</div></button>
        <button class="nav-item" onclick="switchTab('stats')"><div class="nav-item-icon">📊</div><div>統計</div></button>
    </div>
    
    <!-- 工單詳情 Modal -->
    <div class="modal" id="woModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">工單詳情</h2>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>
    
    <script>
        const API = 'workorders-api.php';
        let allWOs = [];
        let currentTab = 'all';
        
        const statusMap = {
            'pending': { text: '待處理', class: 'pending' },
            'production': { text: '製作中', class: 'production' },
            'completed': { text: '已完成', class: 'completed' },
            'shipped': { text: '已出貨', class: 'shipped' },
            'invoiced': { text: '已開發票', class: 'invoiced' }
        };
        
        const priorityMap = {
            'low': '低',
            'normal': '一般',
            'high': '高',
            'urgent': '緊急'
        };
        
        document.addEventListener('DOMContentLoaded', () => {
            loadWorkOrders();
            loadStats();
        });
        
        function switchTab(tab) {
            currentTab = tab;
            document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
            event.target.closest('.nav-item').classList.add('active');
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById('tab-' + tab).classList.add('active');
        }
        
        async function loadWorkOrders() {
            try {
                const res = await fetch(`${API}?action=list&limit=100`);
                const data = await res.json();
                
                if (data.success) {
                    allWOs = data.data;
                    renderAll();
                    renderProduction();
                    renderShip();
                }
            } catch (err) {
                console.error('載入失敗:', err);
            }
        }
        
        async function loadStats() {
            try {
                const res = await fetch(`${API}?action=stats`);
                const data = await res.json();
                
                if (data.success) {
                    document.getElementById('stat-pending').textContent = data.data.pending;
                    document.getElementById('stat-production').textContent = data.data.production;
                    document.getElementById('stat-completed').textContent = data.data.completed;
                    document.getElementById('stat-shipped').textContent = data.data.shipped;
                }
            } catch (err) {
                console.error('載入統計失敗:', err);
            }
        }
        
        function renderAll() {
            const container = document.getElementById('all-list');
            if (allWOs.length === 0) {
                container.innerHTML = '<div class="empty-state"><div class="empty-icon">📋</div><div>目前沒有工單</div></div>';
                return;
            }
            container.innerHTML = allWOs.map(wo => createWOCard(wo)).join('');
        }
        
        function renderProduction() {
            const wos = allWOs.filter(w => w.status === 'production');
            const container = document.getElementById('production-list');
            if (wos.length === 0) {
                container.innerHTML = '<div class="empty-state"><div class="empty-icon">🔄</div><div>沒有製作中的工單</div></div>';
                return;
            }
            container.innerHTML = wos.map(wo => createWOCard(wo)).join('');
        }
        
        function renderShip() {
            const wos = allWOs.filter(w => w.status === 'completed');
            const container = document.getElementById('ship-list');
            if (wos.length === 0) {
                container.innerHTML = '<div class="empty-state"><div class="empty-icon">📦</div><div>沒有待出貨的工單</div></div>';
                return;
            }
            container.innerHTML = wos.map(wo => createWOCard(wo)).join('');
        }
        
        function createWOCard(wo) {
            const status = statusMap[wo.status];
            const priorityClass = wo.priority === 'high' || wo.priority === 'urgent' ? 'priority-' + wo.priority : '';
            
            return `
                <div class="wo-card ${priorityClass}" onclick="showWO(${wo.id})">
                    <div class="wo-header">
                        <div class="wo-number">${wo.wo_number}</div>
                        <div class="wo-date">${new Date(wo.created_at).toLocaleDateString('zh-TW')}</div>
                    </div>
                    <div class="wo-customer">${wo.customer_name}${wo.company ? ' - ' + wo.company : ''}</div>
                    <div class="wo-product">${wo.product_name}</div>
                    <div class="wo-footer">
                        <div class="wo-price">NT$ ${parseFloat(wo.total_amount).toLocaleString()}</div>
                        <div class="wo-status status-${status.class}">${status.text}</div>
                    </div>
                </div>
            `;
        }
        
        async function showWO(id) {
            const modal = document.getElementById('woModal');
            const modalBody = document.getElementById('modalBody');
            const modalTitle = document.getElementById('modalTitle');
            
            modal.classList.add('active');
            modalBody.innerHTML = '<div class="loading">載入中...</div>';
            
            try {
                const res = await fetch(`${API}?action=detail&wo_id=${id}`);
                const data = await res.json();
                
                if (data.success) {
                    const wo = data.data;
                    modalTitle.textContent = wo.wo_number;
                    
                    const specs = wo.product_specs || [];
                    const files = wo.file_urls || [];
                    
                    modalBody.innerHTML = `
                        <div class="form-group">
                            <label class="form-label">狀態</label>
                            <select class="form-select" id="status-select">
                                <option value="pending" ${wo.status === 'pending' ? 'selected' : ''}>待處理</option>
                                <option value="production" ${wo.status === 'production' ? 'selected' : ''}>製作中</option>
                                <option value="completed" ${wo.status === 'completed' ? 'selected' : ''}>已完成</option>
                                <option value="shipped" ${wo.status === 'shipped' ? 'selected' : ''}>已出貨</option>
                                <option value="invoiced" ${wo.status === 'invoiced' ? 'selected' : ''}>已開發票</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">客戶資訊</label>
                            <div style="background:#f8fafc;padding:12px;border-radius:8px;">
                                <div><strong>姓名：</strong>${wo.customer_name}</div>
                                ${wo.company ? `<div><strong>公司：</strong>${wo.company}</div>` : ''}
                                ${wo.phone ? `<div><strong>電話：</strong>${wo.phone}</div>` : ''}
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">商品規格</label>
                            <div style="background:#f8fafc;padding:12px;border-radius:8px;">
                                <div style="font-weight:600;margin-bottom:8px;">${wo.product_name}</div>
                                ${specs.map(s => `<div style="font-size:13px;"><strong>${s.label}:</strong> ${s.value}</div>`).join('')}
                                ${files.map(f => `<div style="margin-top:8px;"><a href="${f.url}" target="_blank" style="color:#667eea;">📎 ${f.label}</a></div>`).join('')}
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">生產備註</label>
                            <textarea class="form-textarea" id="notes-input" placeholder="記錄生產注意事項...">${wo.production_notes || ''}</textarea>
                        </div>
                        
                        ${wo.status === 'completed' || wo.status === 'shipped' ? `
                        <div class="form-group">
                            <label class="form-label">出貨方式</label>
                            <select class="form-select" id="shipping-method">
                                <option value="">請選擇</option>
                                <option value="pickup" ${wo.shipping_method === 'pickup' ? 'selected' : ''}>自取</option>
                                <option value="delivery" ${wo.shipping_method === 'delivery' ? 'selected' : ''}>宅配</option>
                                <option value="self_delivery" ${wo.shipping_method === 'self_delivery' ? 'selected' : ''}>自送</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">物流單號</label>
                            <input type="text" class="form-input" id="tracking-input" value="${wo.tracking_number || ''}" placeholder="填寫宅配單號...">
                        </div>
                        ` : ''}
                        
                        <div style="display:flex;gap:8px;margin-top:20px;">
                            <button class="btn btn-primary" onclick="updateWO(${wo.id})" style="flex:1;">更新工單</button>
                        </div>
                    `;
                }
            } catch (err) {
                modalBody.innerHTML = '<div class="empty-state">載入失敗</div>';
            }
        }
        
        async function updateWO(id) {
            const status = document.getElementById('status-select').value;
            const notes = document.getElementById('notes-input')?.value;
            const shippingMethod = document.getElementById('shipping-method')?.value;
            const tracking = document.getElementById('tracking-input')?.value;
            
            const data = {
                wo_id: id,
                status: status,
                production_notes: notes
            };
            
            if (shippingMethod) data.shipping_method = shippingMethod;
            if (tracking) data.tracking_number = tracking;
            
            try {
                const res = await fetch(`${API}?action=update`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await res.json();
                
                if (result.success) {
                    alert('✓ 更新成功');
                    closeModal();
                    loadWorkOrders();
                    loadStats();
                } else {
                    alert('更新失敗: ' + result.error);
                }
            } catch (err) {
                alert('更新失敗');
            }
        }
        
        function closeModal() {
            document.getElementById('woModal').classList.remove('active');
        }
    </script>
</body>
</html>
