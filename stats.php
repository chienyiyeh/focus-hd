<?php
// 工作统计页面
require_once 'api/config.php';

// 检查登入
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>工作统计 - 我的专注看板</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  
  :root {
    --bg: #F5F3EE;
    --surface: #FFFFFF;
    --surface2: #EFEDE8;
    --border: rgba(0,0,0,0.08);
    --text: #1A1A18;
    --text-secondary: #6B6B66;
    --text-muted: #9E9E99;
    --accent: #1D9E75;
  }
  
  body { font-family: 'Noto Sans TC', sans-serif; background: var(--bg); color: var(--text); line-height: 1.6; }
  
  header { background: var(--surface); border-bottom: 1px solid var(--border); padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; }
  .logo { font-size: 18px; font-weight: 700; }
  .btn { padding: 8px 16px; background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; cursor: pointer; text-decoration: none; color: var(--text); display: inline-block; }
  .btn:hover { background: var(--surface); }
  
  .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
  
  .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-bottom: 32px; }
  .stat-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 20px; }
  .stat-label { font-size: 13px; color: var(--text-secondary); margin-bottom: 8px; }
  .stat-value { font-size: 32px; font-weight: 700; color: var(--accent); }
  .stat-sub { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
  
  .chart-container { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 24px; margin-bottom: 24px; }
  .chart-title { font-size: 16px; font-weight: 600; margin-bottom: 16px; }
  
  .log-list { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 24px; }
  .log-item { padding: 12px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
  .log-item:last-child { border-bottom: none; }
  .log-title { font-weight: 500; }
  .log-meta { font-size: 12px; color: var(--text-muted); }
  .log-duration { font-weight: 600; color: var(--accent); }
  
  .empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
  .empty-state-icon { font-size: 48px; margin-bottom: 16px; }
  .empty-state-text { font-size: 16px; margin-bottom: 8px; }
  .empty-state-hint { font-size: 14px; }
</style>
</head>
<body>

<header>
  <div class="logo">📊 工作统计</div>
  <a href="index.php" class="btn">← 返回看板</a>
</header>

<div class="container">
  
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">今日专注时长</div>
      <div class="stat-value" id="stat-today">0 分钟</div>
      <div class="stat-sub" id="stat-today-count">0 次专注</div>
    </div>
    
    <div class="stat-card">
      <div class="stat-label">本周专注时长</div>
      <div class="stat-value" id="stat-week">0 分钟</div>
      <div class="stat-sub" id="stat-week-count">0 次专注</div>
    </div>
    
    <div class="stat-card">
      <div class="stat-label">本月专注时长</div>
      <div class="stat-value" id="stat-month">0 分钟</div>
      <div class="stat-sub" id="stat-month-count">0 次专注</div>
    </div>
  </div>
  
  <div class="chart-container">
    <div class="chart-title">📈 本周趋势</div>
    <canvas id="weekChart" height="80"></canvas>
  </div>
  
  <div class="chart-container">
    <div class="chart-title">🎯 任务分布（本月）</div>
    <canvas id="projectChart" height="80"></canvas>
  </div>
  
  <div class="chart-container">
    <div class="chart-title">⏰ 时段分析（本月）</div>
    <canvas id="hourChart" height="80"></canvas>
  </div>
  
  <div class="log-list">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
      <div class="chart-title" style="margin: 0;">📋 最近记录</div>
      <button class="btn" onclick="exportData()">📥 导出本月数据</button>
    </div>
    <div id="recent-logs">
      <div class="empty-state">
        <div class="empty-state-icon">⏱️</div>
        <div class="empty-state-text">暂无专注记录</div>
        <div class="empty-state-hint">开始使用计时器记录您的工作时间吧！</div>
      </div>
    </div>
  </div>
  
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
async function loadStats() {
  try {
    const res = await fetch('api/focus-logs.php?action=stats');
    const data = await res.json();
    
    if (data.success) {
      const stats = data.stats;
      
      // 今日统计
      document.getElementById('stat-today').textContent = formatDuration(stats.today.total_seconds || 0);
      document.getElementById('stat-today-count').textContent = (stats.today.count || 0) + ' 次专注';
      
      // 本周统计
      document.getElementById('stat-week').textContent = formatDuration(stats.week.total_seconds || 0);
      document.getElementById('stat-week-count').textContent = (stats.week.count || 0) + ' 次专注';
      
      // 本月统计
      document.getElementById('stat-month').textContent = formatDuration(stats.month.total_seconds || 0);
      document.getElementById('stat-month-count').textContent = (stats.month.count || 0) + ' 次专注';
      
      // 绘制图表
      renderWeekChart(stats.week_trend || []);
      renderProjectChart(stats.project_distribution || []);
      renderHourChart(stats.hour_distribution || []);
    }
  } catch (err) {
    console.error('加载统计失败:', err);
  }
}

async function loadRecentLogs() {
  try {
    const res = await fetch('api/focus-logs.php?action=recent&limit=20');
    const data = await res.json();
    
    if (data.success) {
      const container = document.getElementById('recent-logs');
      if (data.logs.length === 0) {
        container.innerHTML = `
          <div class="empty-state">
            <div class="empty-state-icon">⏱️</div>
            <div class="empty-state-text">暂无专注记录</div>
            <div class="empty-state-hint">开始使用计时器记录您的工作时间吧！</div>
          </div>
        `;
        return;
      }
      
      container.innerHTML = data.logs.map(log => `
        <div class="log-item">
          <div>
            <div class="log-title">${escapeHtml(log.task_title)}</div>
            <div class="log-meta">
              ${log.date} ${log.start_time ? new Date(log.start_time).toLocaleTimeString('zh-TW', {hour: '2-digit', minute: '2-digit'}) : ''}
              ${log.task_project ? ' · ' + escapeHtml(log.task_project) : ''}
            </div>
          </div>
          <div class="log-duration">${formatDuration(log.duration_seconds || 0)}</div>
        </div>
      `).join('');
    }
  } catch (err) {
    console.error('加载记录失败:', err);
  }
}

function formatDuration(seconds) {
  if (!seconds) return '0 分钟';
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  if (hours > 0) {
    return minutes > 0 ? `${hours} 小时 ${minutes} 分钟` : `${hours} 小时`;
  }
  return `${minutes} 分钟`;
}

function escapeHtml(str) {
  const div = document.createElement('div');
  div.textContent = str;
  return div.innerHTML;
}

function renderWeekChart(data) {
  const ctx = document.getElementById('weekChart');
  if (data.length === 0) {
    ctx.parentElement.innerHTML = '<div class="empty-state"><div class="empty-state-text">本周暂无数据</div></div>';
    return;
  }
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: data.map(d => d.date),
      datasets: [{
        label: '专注时长（分钟）',
        data: data.map(d => Math.round((d.total_seconds || 0) / 60)),
        borderColor: '#1D9E75',
        backgroundColor: 'rgba(29, 158, 117, 0.1)',
        tension: 0.4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: { legend: { display: false } }
    }
  });
}

function renderProjectChart(data) {
  const ctx = document.getElementById('projectChart');
  if (data.length === 0) {
    ctx.parentElement.innerHTML = '<div class="empty-state"><div class="empty-state-text">本月暂无数据</div></div>';
    return;
  }
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: data.map(d => d.task_project || '未分类'),
      datasets: [{
        label: '专注时长（分钟）',
        data: data.map(d => Math.round((d.total_seconds || 0) / 60)),
        backgroundColor: '#1D9E75'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: { legend: { display: false } }
    }
  });
}

function renderHourChart(data) {
  const ctx = document.getElementById('hourChart');
  if (data.length === 0) {
    ctx.parentElement.innerHTML = '<div class="empty-state"><div class="empty-state-text">本月暂无数据</div></div>';
    return;
  }
  const hours = Array.from({length: 24}, (_, i) => i);
  const hourData = hours.map(h => {
    const found = data.find(d => d.hour === h);
    return found ? Math.round((found.total_seconds || 0) / 60) : 0;
  });
  
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: hours.map(h => h + ':00'),
      datasets: [{
        label: '专注时长（分钟）',
        data: hourData,
        backgroundColor: '#1D9E75'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      plugins: { legend: { display: false } }
    }
  });
}

function exportData() {
  const today = new Date();
  const startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
  const endDate = today.toISOString().split('T')[0];
  
  window.location.href = `api/focus-logs.php?action=export&start_date=${startDate}&end_date=${endDate}`;
}

// 页面加载时执行
document.addEventListener('DOMContentLoaded', () => {
  loadStats();
  loadRecentLogs();
});
</script>

</body>
</html>
