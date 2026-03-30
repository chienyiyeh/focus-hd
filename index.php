let goalPanelCollapsed = false;
  const addWeekBtn = document.createElement('button');
  addWeekBtn.className = 'goal-inline-add';
  addWeekBtn.textContent = '＋ 新增週目標';
  addWeekBtn.onclick = (e) => {
    e.stopPropagation();
    openGoalModal('week', month.id);
  };
  childrenEl.appendChild(addWeekBtn);

  return div;
}

function buildWeekCard(week) {
  const allCards = [...state.lib, ...state.week, ...state.focus, ...state.done];
  const children = allCards.filter(c => Number(c.parentId) === Number(week.id));
  const doneCount = children.filter(c => c.col === 'done').length;
  const total = children.length;
  const pct = total > 0 ? Math.round(doneCount / total * 100) : 0;
  const isComplete = total > 0 && doneCount === total;
  const isFiltered = goalFilterParentId === week.id;
  const weekPath = getGoalPathText(week);

  const div = document.createElement('div');
  div.className = 'goal-week-card' + (isFiltered ? ' goal-active-filter' : '');
  div.id = 'goal-week-' + week.id;
  div.dataset.weekId = week.id;

  div.innerHTML = `
    <div class="goal-week-header" onclick="filterByParent(${week.id})" title="${escHtml(weekPath || week.title)}" style="cursor:pointer;">
      <span class="goal-week-title">📋 ${escHtml(week.title)}</span>
      <span class="goal-week-progress">${doneCount}/${total}</span>
    </div>
    <div style="padding:0 10px 8px;color:rgba(255,255,255,0.55);font-size:10px;line-height:1.5;">${escHtml(weekPath || '未掛上上層目標')}</div>
    <div class="goal-week-bar">
      <div class="goal-week-bar-fill${isComplete ? ' complete' : ''}" style="width:${pct}%"></div>
    </div>
    <div class="goal-week-actions">
      <button class="goal-action-btn primary" onclick="spawnProjectCard(${week.id}, ${JSON.stringify(week.title)});event.stopPropagation()">＋ 子任務</button>
      <button class="goal-action-btn" onclick="filterByParent(${week.id});event.stopPropagation()">🔍 查看</button>
      <button class="goal-action-btn" onclick="editGoalCard(${week.id},event)">✏️ 編輯</button>
      <button class="goal-action-btn" onclick="deleteGoalCard(${week.id},event)" style="color:#E24B4A;">🗑</button>
    </div>
  `;

  return div;
}

function buildMobileYearCard(year, allGoalCards) {
  const monthCards = allGoalCards.filter(c => Number(c.parentId) === Number(year.id) && c.level === 'month');
  const wrap = document.createElement('div');
  wrap.style.cssText = 'margin-bottom:10px;background:#1f1f1d;border-radius:10px;padding:10px;border:1px solid rgba(255,215,0,0.2);';
  wrap.innerHTML = `<div style="display:flex;align-items:center;gap:8px;color:#FFD700;font-weight:700;margin-bottom:8px;">📌 ${escHtml(year.title)}</div>`;
  monthCards.forEach(month => {
    const monthEl = document.createElement('div');
    monthEl.style.cssText = 'margin-bottom:6px;padding:8px;background:rgba(255,255,255,0.04);border-radius:8px;color:#fff;';
    monthEl.innerHTML = `<div style="font-size:12px;font-weight:600;margin-bottom:4px;">📅 ${escHtml(month.title)}</div>`;
    const weekCards = allGoalCards.filter(c => Number(c.parentId) === Number(month.id) && c.level === 'week');
    weekCards.forEach(week => monthEl.appendChild(buildMobileWeekCard(week)));
    wrap.appendChild(monthEl);
  });
  return wrap;
}

function buildMobileWeekCard(week) {
  const allCards = [...state.lib, ...state.week, ...state.focus, ...state.done];
  const children = allCards.filter(c => Number(c.parentId) === Number(week.id));
  const doneCount = children.filter(c => c.col === 'done').length;
  const total = children.length;
  const pct = total > 0 ? Math.round(doneCount / total * 100) : 0;
  const isComplete = total > 0 && doneCount === total;

  const div = document.createElement('div');
  div.style.cssText = 'background:var(--surface);border-radius:6px;margin-bottom:4px;padding:8px 10px;border:1px solid var(--border);';
  div.innerHTML = `
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
      <span style="font-size:12px;color:var(--text);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">📋 ${escHtml(week.title)}</span>
      <span style="font-size:11px;color:var(--text-muted);">${doneCount}/${total}</span>
    </div>
    <div style="height:2px;background:var(--border);border-radius:1px;margin-bottom:8px;">
      <div style="height:100%;background:${isComplete ? '#22c55e' : '#f97316'};width:${pct}%;transition:width 0.6s;border-radius:1px;"></div>
    </div>
    <div style="display:flex;gap:6px;flex-wrap:wrap;">
      <button onclick="spawnProjectCard(${week.id}, ${JSON.stringify(week.title)})" style="padding:4px 10px;background:#534AB720;border:1px solid #534AB740;color:#534AB7;border-radius:6px;font-size:11px;cursor:pointer;font-family:inherit;">＋ 子任務</button>
}
