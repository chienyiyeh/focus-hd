# api/cards.php

```php
<?php
/**
 * 卡片 API - 戰略樹修正版
 */

require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    errorResponse('登入已過期，請重新登入');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$isAdmin = ($userId === 1);
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

switch ($action) {
    case 'list':
        handleList($userId);
        break;
    case 'save':
        handleSave($userId, $isAdmin);
        break;
    case 'delete':
        handleDelete($userId, $isAdmin);
        break;
    case 'move':
        handleMove($userId);
        break;
    default:
        errorResponse('無效的卡片動作');
}

function ensureColumns(PDO $db): void {
    $cols = array_column($db->query("SHOW COLUMNS FROM cards")->fetchAll(PDO::FETCH_ASSOC), 'Field');

    if (!in_array('priority', $cols, true)) {
        $db->exec("ALTER TABLE cards ADD COLUMN priority VARCHAR(32) DEFAULT NULL");
    }
    if (!in_array('checklist', $cols, true)) {
        $db->exec("ALTER TABLE cards ADD COLUMN checklist JSON DEFAULT NULL");
    }
    if (!in_array('is_private', $cols, true)) {
        $db->exec("ALTER TABLE cards ADD COLUMN is_private TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (!in_array('created_by', $cols, true)) {
        $db->exec("ALTER TABLE cards ADD COLUMN created_by INT DEFAULT NULL");
    }
    if (!in_array('postponed_count', $cols, true)) {
        $db->exec("ALTER TABLE cards ADD COLUMN postponed_count INT DEFAULT 0");
    }
    if (!in_array('level', $cols, true)) {
        $db->exec("ALTER TABLE cards ADD COLUMN level ENUM('year','month','week','project','general') DEFAULT 'general'");
    }
    if (!in_array('parent_id', $cols, true)) {
        $db->exec("ALTER TABLE cards ADD COLUMN parent_id INT DEFAULT NULL");
    }
    if (!in_array('goal_col', $cols, true)) {
        $db->exec("ALTER TABLE cards ADD COLUMN goal_col VARCHAR(20) DEFAULT NULL");
    }
}

function handleList(int $userId): void {
    try {
        $db = getDB();
        ensureColumns($db);

        $stmt = $db->prepare("
            SELECT
                c.id,
                c.col,
                c.title,
                c.project,
                c.priority,
                c.source_link,
                c.summary,
                c.next_step,
                c.body,
                c.checklist,
                c.bgcolor,
                c.textcolor,
                c.is_private,
                c.created_by,
                c.completed_at,
                c.created_at,
                c.updated_at,
                c.level,
                c.parent_id,
                c.goal_col,
                u.username AS created_by_username
            FROM cards c
            LEFT JOIN users u ON c.created_by = u.id
            WHERE c.user_id = ? OR c.is_private = 0
            ORDER BY
                CASE c.col
                    WHEN 'goal' THEN 0
                    WHEN 'lib' THEN 1
                    WHEN 'week' THEN 2
                    WHEN 'focus' THEN 3
                    WHEN 'done' THEN 4
                    ELSE 9
                END,
                c.updated_at DESC,
                c.created_at DESC,
                c.id DESC
        ");
        $stmt->execute([$userId]);
        $cards = $stmt->fetchAll();

        $result = [
            'goal' => [],
            'lib' => [],
            'week' => [],
            'focus' => [],
            'done' => [],
        ];

        foreach ($cards as $card) {
            $col = $card['col'];
            if (!isset($result[$col])) {
                continue;
            }

            $result[$col][] = [
                'id' => (int)$card['id'],
                'title' => $card['title'],
                'project' => $card['project'],
                'priority' => $card['priority'],
                'sourceLink' => $card['source_link'],
                'summary' => $card['summary'],
                'nextStep' => $card['next_step'],
                'body' => $card['body'],
                'checklist' => $card['checklist'] ? json_decode($card['checklist'], true) : [],
                'bgcolor' => $card['bgcolor'],
                'textcolor' => $card['textcolor'],
                'isPrivate' => (bool)$card['is_private'],
                'createdBy' => $card['created_by'],
                'createdByUsername' => $card['created_by_username'],
                'completedAt' => $card['completed_at'],
                'createdAt' => $card['created_at'],
                'updatedAt' => $card['updated_at'],
                'level' => $card['level'] ?: 'general',
                'parentId' => $card['parent_id'] !== null ? (int)$card['parent_id'] : null,
                'goalCol' => $card['goal_col'],
            ];
        }

        jsonResponse($result);
    } catch (Throwable $e) {
        errorResponse('取得卡片失敗: ' . (APP_DEBUG ? $e->getMessage() : '系統錯誤'));
    }
}

function cleanHTML(?string $html): ?string {
    if ($html === null || $html === '') {
        return null;
    }

    $allowed = '<p><br><strong><em><u><s><ol><ul><li><h1><h2><h3><span><a><img><font><div>';
    $cleaned = strip_tags($html, $allowed);
    $cleaned = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $cleaned);
    $cleaned = preg_replace('/on\w+="[^"]*"/i', '', $cleaned);
    return $cleaned;
}

function normalizeCol(?string $col): string {
    $col = cleanInput($col ?? 'lib');
    return in_array($col, ['goal', 'lib', 'week', 'focus', 'done'], true) ? $col : 'lib';
}

function normalizeLevel(?string $level, string $col, array $checklist): string {
    $level = cleanInput($level ?? '');
    $allowed = ['year', 'month', 'week', 'project', 'general'];

    if ($col === 'goal') {
        return in_array($level, ['year', 'month', 'week'], true) ? $level : 'general';
    }

    if (in_array($level, $allowed, true)) {
        return $level;
    }

    if (!empty($checklist)) {
        return 'project';
    }

    return 'general';
}

function handleSave(int $userId, bool $isAdmin): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('僅支援 POST 請求');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        errorResponse('資料格式錯誤');
    }

    $id = isset($input['id']) ? (int)$input['id'] : null;
    $col = normalizeCol($input['col'] ?? 'lib');
    $title = cleanInput($input['title'] ?? '');
    $project = cleanInput($input['project'] ?? null);
    $priority = cleanInput($input['priority'] ?? null);
    $sourceLink = cleanInput($input['sourceLink'] ?? null);
    $summary = cleanInput($input['summary'] ?? null);
    $nextStep = cleanInput($input['nextStep'] ?? null);
    $body = cleanHTML($input['body'] ?? null);
    $bgcolor = cleanInput($input['bgcolor'] ?? null);
    $textcolor = cleanInput($input['textcolor'] ?? null);
    $isPrivate = isset($input['isPrivate']) ? (int)$input['isPrivate'] : 0;
    $checklistArray = !empty($input['checklist']) && is_array($input['checklist']) ? $input['checklist'] : [];
    $checklist = !empty($checklistArray) ? json_encode($checklistArray, JSON_UNESCAPED_UNICODE) : null;
    $completedAt = $input['completedAt'] ?? null;
    $parentId = isset($input['parentId']) && $input['parentId'] !== '' ? (int)$input['parentId'] : null;
    $goalCol = cleanInput($input['goalCol'] ?? ($col === 'goal' ? 'goal' : null));
    $level = normalizeLevel($input['level'] ?? null, $col, $checklistArray);

    if ($title === '') {
        errorResponse('標題不能為空');
    }

    try {
        $db = getDB();
        ensureColumns($db);

        if ($id) {
            if ($isAdmin) {
                $stmt = $db->prepare("
                    UPDATE cards SET
                        col=?,
                        title=?,
                        project=?,
                        priority=?,
                        source_link=?,
                        summary=?,
                        next_step=?,
                        body=?,
                        checklist=?,
                        bgcolor=?,
                        textcolor=?,
                        is_private=?,
                        completed_at=?,
                        level=?,
                        parent_id=?,
                        goal_col=?
                    WHERE id=?
                ");
                $stmt->execute([
                    $col,
                    $title,
                    $project,
                    $priority,
                    $sourceLink,
                    $summary,
                    $nextStep,
                    $body,
                    $checklist,
                    $bgcolor,
                    $textcolor,
                    $isPrivate,
                    $completedAt,
                    $level,
                    $parentId,
                    $goalCol,
                    $id,
                ]);
            } else {
                $stmt = $db->prepare("
                    UPDATE cards SET
                        col=?,
                        title=?,
                        project=?,
                        priority=?,
                        source_link=?,
                        summary=?,
                        next_step=?,
                        body=?,
                        checklist=?,
                        bgcolor=?,
                        textcolor=?,
                        is_private=?,
                        completed_at=?,
                        level=?,
                        parent_id=?,
                        goal_col=?
                    WHERE id=? AND (user_id=? OR is_private=0)
                ");
                $stmt->execute([
                    $col,
                    $title,
                    $project,
                    $priority,
                    $sourceLink,
                    $summary,
                    $nextStep,
                    $body,
                    $checklist,
                    $bgcolor,
                    $textcolor,
                    $isPrivate,
                    $completedAt,
                    $level,
                    $parentId,
                    $goalCol,
                    $id,
                    $userId,
                ]);
            }

            $existsStmt = $db->prepare("SELECT id FROM cards WHERE id=?");
            $existsStmt->execute([$id]);
            $exists = $existsStmt->fetch();

            if ($exists) {
                successResponse(['id' => $id], '卡片更新成功');
            }

            errorResponse('卡片不存在或無權限修改');
        }

        $stmt = $db->prepare("
            INSERT INTO cards (
                user_id,
                col,
                title,
                project,
                priority,
                source_link,
                summary,
                next_step,
                body,
                checklist,
                bgcolor,
                textcolor,
                is_private,
                created_by,
                completed_at,
                level,
                parent_id,
                goal_col
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $userId,
            $col,
            $title,
            $project,
            $priority,
            $sourceLink,
            $summary,
            $nextStep,
            $body,
            $checklist,
            $bgcolor,
            $textcolor,
            $isPrivate,
            $userId,
            $completedAt,
            $level,
            $parentId,
            $goalCol,
        ]);

        successResponse(['id' => (int)$db->lastInsertId()], '卡片新增成功');
    } catch (Throwable $e) {
        errorResponse('儲存卡片失敗: ' . (APP_DEBUG ? $e->getMessage() : '系統錯誤'));
    }
}

function handleDelete(int $userId, bool $isAdmin): void {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if (!$id) {
        errorResponse('缺少卡片 ID');
    }

    try {
        $db = getDB();
        if ($isAdmin) {
            $stmt = $db->prepare("DELETE FROM cards WHERE id=?");
            $stmt->execute([$id]);
        } else {
            $stmt = $db->prepare("DELETE FROM cards WHERE id=? AND user_id=?");
            $stmt->execute([$id, $userId]);
        }

        if ($stmt->rowCount() > 0) {
            successResponse([], '卡片刪除成功');
        }

        errorResponse('卡片不存在或無權限刪除');
    } catch (Throwable $e) {
        errorResponse('刪除卡片失敗');
    }
}

function handleMove(int $userId): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        errorResponse('僅支援 POST 請求');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    $newCol = normalizeCol($input['col'] ?? '');

    if (!$id || !$newCol) {
        errorResponse('缺少必要參數');
    }
    if (!in_array($newCol, ['lib', 'week', 'focus', 'done'], true)) {
        errorResponse('無效的欄位');
    }

    try {
        $db = getDB();
        ensureColumns($db);

        $stmt = $db->prepare("SELECT user_id, col FROM cards WHERE id=?");
        $stmt->execute([$id]);
        $card = $stmt->fetch();
        if (!$card) {
            errorResponse('卡片不存在');
        }

        $isOwner = ((int)$card['user_id'] === $userId);

        if (!$isOwner && in_array($newCol, ['focus', 'week', 'done'], true)) {
            errorResponse('只能將別人的卡片退回策略庫');
        }

        $completedAt = ($newCol === 'done') ? date('Y-m-d H:i:s') : null;

        $update = $db->prepare("UPDATE cards SET col=?, completed_at=? WHERE id=?");
        $update->execute([$newCol, $completedAt, $id]);

        successResponse([], '卡片移動成功');
    } catch (Throwable $e) {
        errorResponse('移動卡片失敗');
    }
}
```

---

# index.php 需要直接覆蓋的 JavaScript 函式

把你現在 `index.php` 裡面同名函式，直接用下面這份完整版本覆蓋。

```javascript
let goalPanelCollapsed = false;
let goalFilterParentId = null;
window._spawnParentId = null;
window._spawnLevel = null;

function toggleGoalPanel() {
  goalPanelCollapsed = !goalPanelCollapsed;
  const panel = document.getElementById('goal-panel');
  if (panel) panel.classList.toggle('collapsed', goalPanelCollapsed);
  try {
    localStorage.setItem('goalPanelCollapsed', goalPanelCollapsed ? '1' : '0');
  } catch (e) {}
}

function initGoalPanelState() {
  try {
    const saved = localStorage.getItem('goalPanelCollapsed');
    if (saved === '1') {
      goalPanelCollapsed = true;
      const panel = document.getElementById('goal-panel');
      if (panel) panel.classList.add('collapsed');
    }
  } catch (e) {}
}

function getGoalById(id) {
  return (state.goal || []).find(c => Number(c.id) === Number(id)) || null;
}

function getGoalAncestors(parentId) {
  const result = [];
  let cursor = parentId;
  let safe = 0;
  while (cursor && safe < 20) {
    const found = getGoalById(cursor);
    if (!found) break;
    result.unshift(found);
    cursor = found.parentId;
    safe++;
  }
  return result;
}

function getGoalPathText(card) {
  if (!card || !card.parentId) return '';
  const ancestors = getGoalAncestors(card.parentId);
  return ancestors.map(x => x.title).join(' ＞ ');
}

function calcGoalProgress(goalCard) {
  const allCards = [...state.lib, ...state.week, ...state.focus, ...state.done];
  const directChildren = allCards.filter(c => Number(c.parentId) === Number(goalCard.id));
  const goalChildren = (state.goal || []).filter(c => Number(c.parentId) === Number(goalCard.id));

  if (directChildren.length === 0 && goalChildren.length === 0) {
    return { done: 0, total: 0 };
  }

  let done = 0;
  let total = 0;

  directChildren.forEach(c => {
    total += 1;
    if (c.col === 'done' || c.completedAt) done += 1;
  });

  goalChildren.forEach(child => {
    const childProgress = calcGoalProgress(child);
    done += childProgress.done;
    total += childProgress.total;
  });

  return { done, total };
}

function renderGoalTree() {
  const container = document.getElementById('goal-panel-body');
  const mobileContainer = document.getElementById('mobile-goal-body');
  const goalCards = Array.isArray(state.goal) ? [...state.goal] : [];
  const yearCards = goalCards
    .filter(c => c.level === 'year')
    .sort((a, b) => new Date(b.updatedAt || b.createdAt || 0) - new Date(a.updatedAt || a.createdAt || 0));

  const emptyHTML = `<div style="text-align:center;padding:24px 16px;color:rgba(255,255,255,0.3);font-size:12px;line-height:1.8;">還沒有年度目標<br>點下方按鈕建立第一個</div>`;

  if (container) {
    container.innerHTML = '';
    if (yearCards.length === 0) {
      container.innerHTML = emptyHTML;
    } else {
      yearCards.forEach(year => container.appendChild(buildYearCard(year, goalCards)));
    }
  }

  if (mobileContainer) {
    mobileContainer.innerHTML = '';
    if (yearCards.length === 0) {
      mobileContainer.innerHTML = '<div style="padding:16px;color:var(--text-muted);font-size:12px;text-align:center;">尚無年度目標</div>';
    } else {
      yearCards.forEach(year => mobileContainer.appendChild(buildMobileYearCard(year, goalCards)));
    }
  }
}

function buildYearCard(year, allGoalCards) {
  const monthCards = allGoalCards
    .filter(c => Number(c.parentId) === Number(year.id) && c.level === 'month')
    .sort((a, b) => new Date(b.updatedAt || b.createdAt || 0) - new Date(a.updatedAt || a.createdAt || 0));

  const progress = calcGoalProgress(year);
  const pct = progress.total > 0 ? Math.round(progress.done / progress.total * 100) : 0;
  const isOpen = true;

  const div = document.createElement('div');
  div.className = 'goal-year-card open';
  div.id = 'goal-year-' + year.id;

  div.innerHTML = `
    <div class="goal-year-header" onclick="this.closest('.goal-year-card').classList.toggle('open')">
      <span class="goal-year-chevron">▶</span>
      <span class="goal-year-icon">📌</span>
      <span class="goal-year-title">${escHtml(year.title)}</span>
      <span style="font-size:10px;color:rgba(255,255,255,0.5);margin-right:6px;">${pct}%</span>
      <button type="button" onclick="event.stopPropagation();filterByParent(${year.id})" style="background:rgba(255,215,0,0.12);border:1px solid rgba(255,215,0,0.25);color:#FFD700;border-radius:4px;padding:2px 6px;font-size:10px;cursor:pointer;">看子任務</button>
      <button type="button" onclick="editGoalCard(${year.id}, event)" style="background:rgba(255,255,255,0.08);border:none;color:rgba(255,255,255,0.7);border-radius:4px;padding:2px 6px;font-size:10px;cursor:pointer;">✏️</button>
      <button type="button" onclick="deleteGoalCard(${year.id}, event)" style="background:rgba(226,75,74,0.15);border:none;color:#E24B4A;border-radius:4px;padding:2px 6px;font-size:10px;cursor:pointer;">🗑</button>
    </div>
    <div class="goal-year-progress-bar">
      <div class="goal-year-progress-fill${pct === 100 && progress.total > 0 ? ' complete' : ''}" style="width:${pct}%;"></div>
    </div>
    <div class="goal-year-children" id="goal-year-children-${year.id}"></div>
  `;

  const childrenEl = div.querySelector('#goal-year-children-' + year.id);
  monthCards.forEach(month => childrenEl.appendChild(buildMonthCard(month, allGoalCards)));

  const addBtn = document.createElement('button');
  addBtn.className = 'goal-inline-add';
  addBtn.textContent = '＋ 新增月目標';
  addBtn.onclick = (e) => {
    e.stopPropagation();
    openGoalModal('month', year.id);
  };
  childrenEl.appendChild(addBtn);

  return div;
}

function buildMonthCard(month, allGoalCards) {
  const weekCards = allGoalCards
    .filter(c => Number(c.parentId) === Number(month.id) && c.level === 'week')
    .sort((a, b) => new Date(b.updatedAt || b.createdAt || 0) - new Date(a.updatedAt || a.createdAt || 0));

  const progress = calcGoalProgress(month);
  const pct = progress.total > 0 ? Math.round(progress.done / progress.total * 100) : 0;
  const isFiltered = goalFilterParentId === month.id;

  const div = document.createElement('div');
  div.className = 'goal-month-card' + (isFiltered ? ' goal-active-filter' : '');
  div.innerHTML = `
    <div class="goal-month-header" onclick="filterByParent(${month.id})" title="點一下顯示這個月目標底下的任務" style="cursor:pointer;">
      <span class="goal-month-title">📅 ${escHtml(month.title)}</span>
      <span class="goal-month-badge">${progress.done}/${progress.total}</span>
      <button type="button" onclick="editGoalCard(${month.id},event)" style="background:rgba(255,255,255,0.08);border:none;color:rgba(255,255,255,0.6);border-radius:4px;padding:2px 5px;font-size:10px;cursor:pointer;flex-shrink:0;margin-left:2px;">✏️</button>
      <button type="button" onclick="deleteGoalCard(${month.id},event)" style="background:rgba(226,75,74,0.15);border:none;color:#E24B4A;border-radius:4px;padding:2px 5px;font-size:10px;cursor:pointer;flex-shrink:0;">🗑</button>
    </div>
    <div class="goal-month-progress-bar">
      <div class="goal-month-progress-fill${pct === 100 && progress.total > 0 ? ' complete' : ''}" style="width:${pct}%"></div>
    </div>
    <div class="goal-month-children" id="month-children-${month.id}"></div>
  `;

  const childrenEl = div.querySelector('#month-children-' + month.id);
  weekCards.forEach(week => childrenEl.appendChild(buildWeekCard(week)));

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
      <button onclick="filterByParent(${week.id});switchMobileTab('lib')" style="padding:4px 10px;background:var(--surface2);border:1px solid var(--border);color:var(--text-muted);border-radius:6px;font-size:11px;cursor:pointer;font-family:inherit;">🔍 查看</button>
    </div>
  `;
  return div;
}

function openGoalModal(level, parentId, editId = null) {
  const levelNames = { year: '年度目標', month: '月度目標', week: '週目標' };
  const levelIcons = { year: '📌', month: '📅', week: '📋' };
  const overlay = document.getElementById('goal-modal-overlay');
  if (!overlay) return;

  _goalModalState = { level, parentId: parentId || null, editId: editId || null };

  const titleEl = document.getElementById('gm-title');
  const parentInfoEl = document.getElementById('gm-parent-info');
  const periodRow = document.getElementById('gm-period-row');
  const monthSel = document.getElementById('gm-month-input');
  const yearInput = document.getElementById('gm-year-input');
  const titleInput = document.getElementById('gm-title-input');
  const summaryInput = document.getElementById('gm-summary-input');

  titleEl.textContent = `${editId ? '編輯' : '新增'}${levelIcons[level] || ''} ${levelNames[level] || '目標'}`;

  if (parentId) {
    const parentCard = getGoalById(parentId);
    parentInfoEl.textContent = parentCard ? `隸屬：${getGoalAncestors(parentCard.id).map(x => x.title).join(' ＞ ') || parentCard.title}` : '隸屬：未找到父目標';
    parentInfoEl.style.display = 'block';
  } else {
    parentInfoEl.style.display = 'none';
    parentInfoEl.textContent = '';
  }

  if (level === 'year' || level === 'month') {
    periodRow.style.display = 'block';
    monthSel.style.display = level === 'month' ? 'block' : 'none';
  } else {
    periodRow.style.display = 'none';
    monthSel.style.display = 'none';
  }

  yearInput.value = new Date().getFullYear();
  monthSel.value = String(new Date().getMonth() + 1).padStart(2, '0');

  if (editId) {
    const card = getGoalById(editId);
    if (card) {
      titleInput.value = card.title || '';
      summaryInput.value = card.summary || '';
    } else {
      titleInput.value = '';
      summaryInput.value = '';
    }
  } else {
    titleInput.value = '';
    summaryInput.value = '';
  }

  overlay.style.display = 'flex';
  setTimeout(() => titleInput.focus(), 60);
}

function closeGoalModal() {
  const overlay = document.getElementById('goal-modal-overlay');
  if (overlay) overlay.style.display = 'none';
  _goalModalState = {};
}

async function confirmGoalModal() {
  const title = document.getElementById('gm-title-input').value.trim();
  const summary = document.getElementById('gm-summary-input').value.trim();
  if (!title) {
    document.getElementById('gm-title-input').focus();
    return;
  }

  const { level, parentId, editId } = _goalModalState;
  const data = {
    col: 'goal',
    title,
    summary: summary || null,
    level,
    parentId: parentId || null,
    goalCol: 'goal',
    isPrivate: 0
  };
  if (editId) data.id = editId;

  const btn = document.getElementById('gm-confirm');
  btn.disabled = true;
  btn.textContent = '儲存中...';

  try {
    const res = await fetch('api/cards.php?action=save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    });
    const d = await res.json();
    if (d.success) {
      closeGoalModal();
      await loadCards();
      if (parentId) filterByParent(parentId);
      toast(editId ? '✅ 目標已更新' : '✅ 目標已建立');
    } else {
      toast('❌ ' + (d.error || '儲存失敗'));
    }
  } catch (e) {
    toast('❌ 連線錯誤');
  } finally {
    btn.disabled = false;
    btn.textContent = '確認';
  }
}

function editGoalCard(id, event) {
  if (event) event.stopPropagation();
  const card = getGoalById(id);
  if (!card) {
    toast('找不到目標卡');
    return;
  }
  openGoalModal(card.level || 'general', card.parentId || null, card.id);
}

async function deleteGoalCard(id, event) {
  if (event) event.stopPropagation();
  const card = getGoalById(id);
  if (!card) return;
  if (!confirm(`確定要刪除「${card.title}」嗎？`)) return;

  try {
    const res = await fetch(`api/cards.php?action=delete&id=${id}`);
    const d = await res.json();
    if (d.success) {
      if (goalFilterParentId === id) goalFilterParentId = null;
      await loadCards();
      toast('🗑 目標已刪除');
    } else {
      toast('❌ ' + (d.error || '刪除失敗'));
    }
  } catch (e) {
    toast('❌ 刪除失敗');
  }
}

function filterByParent(parentId) {
  goalFilterParentId = Number(parentId);
  render();
  renderGoalTree();

  const goal = getGoalById(parentId);
  if (goal) {
    toast(`已顯示「${goal.title}」底下的任務`);
  }

  if (window.innerWidth <= 768) {
    switchMobileTab('lib');
  }
}

function clearGoalFilter() {
  goalFilterParentId = null;
  render();
  renderGoalTree();
}

function spawnProjectCard(weekId, weekTitle) {
  window._spawnParentId = Number(weekId);
  window._spawnLevel = 'project';

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
  document.getElementById('modal-title').textContent = `🚀 新增子任務 → ${weekTitle}`;
  document.getElementById('overlay').classList.add('open');
  setTimeout(() => document.getElementById('input-title').focus(), 60);
}

function buildGoalParentBadge(card) {
  if (!card || !card.parentId) return '';
  const ancestors = getGoalAncestors(card.parentId);
  if (!ancestors.length) return `<span class="parent-badge">🎯 目標任務</span>`;

  const levelNames = { year: '年度', month: '月度', week: '週度' };
  const path = ancestors.map(a => `${levelNames[a.level] || '目標'}：${a.title}`).join(' ／ ');
  const directParent = ancestors[ancestors.length - 1];
  return `<span class="parent-badge" style="display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:10px;font-weight:700;border:1px solid rgba(99,102,241,.18);background:rgba(99,102,241,.08);color:#4f46e5;" title="${escHtml(path)}">🧬 ${escHtml(path)}</span>`;
}

function buildCard(card, col, cardNo) {
  const div = document.createElement('div');
  const isProjectCard = (card.level === 'project') || (Array.isArray(card.checklist) && card.checklist.length > 0);
  const isFiltered = goalFilterParentId !== null && Number(card.parentId) !== Number(goalFilterParentId);
  div.className = 'card' + (isProjectCard ? ' is-project' : '') + (isFiltered ? ' goal-dimmed' : '');
  div.id = 'card-' + card.id;
  div.draggable = true;
  div.dataset.cardId = card.id;
  div.dataset.col = col;
  if (card.bgcolor) div.style.background = card.bgcolor;
  if (card.textcolor) div.style.color = card.textcolor;

  const parentBadgeHTML = buildGoalParentBadge(card);

  let checklistTitle = '待辦';
  if (card.level === 'project' && card.parentId) {
    const parentGoal = getGoalById(card.parentId);
    if (parentGoal?.level === 'year') checklistTitle = '年度專案';
    else if (parentGoal?.level === 'month') checklistTitle = '月度專案';
    else if (parentGoal?.level === 'week') checklistTitle = '週度專案';
  }

  const checklist = Array.isArray(card.checklist) ? card.checklist : [];
  const doneCount = checklist.filter(i => i.checked).length;

  div.innerHTML = `
    <div class="card-top" onclick="this.parentElement.classList.toggle('open')">
      <div style="display:flex;align-items:flex-start;gap:8px;flex:1;min-width:0;">
        <span class="drag-handle">⋮⋮</span>
        <div style="display:flex;flex-direction:column;gap:6px;min-width:0;flex:1;">
          ${parentBadgeHTML}
          <div class="card-title">${escHtml(card.title || '')}</div>
        </div>
      </div>
      <div class="card-actions-menu">
        <button type="button" class="card-actions-toggle" onclick="event.stopPropagation();this.nextElementSibling.classList.toggle('open')">⋯</button>
        <div class="card-actions-dropdown">
          <button type="button" class="card-action-item primary" onclick="event.stopPropagation();editCard(${card.id}, '${col}')">編輯內容</button>
          <button type="button" class="card-action-item danger" onclick="event.stopPropagation();deleteCard(${card.id}, '${col}')">刪除</button>
        </div>
      </div>
    </div>
    ${checklist.length ? `<div class="card-checklist" style="margin-bottom:8px;"><div class="card-checklist-header">${checklistTitle} <span class="card-checklist-progress">${doneCount}/${checklist.length}</span></div></div>` : ''}
    <div class="card-preview">${card.summary ? escHtml(card.summary) : ''}</div>
  `;

  return div;
}

async function loadCards() {
  try {
    const currentTab = document.querySelector('.mobile-tab.active')?.dataset?.col || null;
    const res = await fetch('api/cards.php?action=list');
    const data = await res.json();
    if (data.success === false) {
      toast('❌ ' + (data.error || '讀取失敗'));
      return;
    }

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
  } catch (err) {
    toast('連線異常，無法讀取卡片');
  }
}

function collectModalData() {
  return {
    title: document.getElementById('input-title').value.trim(),
    project: document.getElementById('input-project').value,
    priority: document.getElementById('input-priority').value,
    sourceLink: document.getElementById('input-source').value.trim(),
    summary: document.getElementById('input-summary').value.trim(),
    nextStep: document.getElementById('input-nextstep').value.trim(),
    body: document.getElementById('input-body').value,
    bgcolor: document.getElementById('input-bgcolor').value,
    textcolor: document.getElementById('input-textcolor').value,
    isPrivate: document.getElementById('privacy-private').checked ? 1 : 0,
    checklist: getChecklistData('checklist-container'),
    parentId: window._spawnParentId || null,
    level: window._spawnLevel || null
  };
}

function openModal(col) {
  const myFocusCount = state.focus.filter(c => c.createdByUsername === CURRENT_USERNAME || !c.createdByUsername).length;
  if (col === 'focus' && myFocusCount >= 1) {
    toast('你的今日專注已有 1 張！');
    return;
  }
  if (col === 'week' && state.week.length >= 3) {
    toast('本週目標已滿 3 張！');
    return;
  }

  window._spawnParentId = null;
  window._spawnLevel = null;

  document.getElementById('input-col').value = col;
  document.getElementById('input-edit-id').value = '';
  document.getElementById('input-title').value = '';
  document.getElementById('input-project').value = '';
  document.getElementById('input-source').value = '';
  document.getElementById('input-summary').value = '';
  document.getElementById('input-nextstep').value = '';
  document.getElementById('input-body').value = '';
  const bodyEditorClr = document.getElementById('input-body-editor');
  if (bodyEditorClr) bodyEditorClr.innerHTML = '';
  document.getElementById('checklist-container').innerHTML = '';
  toggleModalChecklist(false);
  document.getElementById('input-priority').value = '';
  document.querySelectorAll('.priority-btn').forEach(b => b.classList.remove('active'));
  initSwatches('', '');
  document.getElementById('modal-title').textContent = { lib: '新增策略筆記', week: '新增本週目標', focus: '設定今日專注' }[col];
  document.getElementById('overlay').classList.add('open');
  setTimeout(() => document.getElementById('input-title').focus(), 60);
}

function editCard(id, col) {
  const card = state[col].find(c => Number(c.id) === Number(id));
  if (!card) return;

  window._spawnParentId = card.parentId || null;
  window._spawnLevel = card.level || null;

  document.getElementById('input-col').value = col;
  document.getElementById('input-edit-id').value = id;
  document.getElementById('input-title').value = card.title || '';
  document.getElementById('input-project').value = card.project || '';
  setTimeout(renderProjectSelect, 10);
  document.getElementById('input-source').value = card.sourceLink || '';
  document.getElementById('input-summary').value = card.summary || '';
  document.getElementById('input-nextstep').value = card.nextStep || '';
  document.getElementById('input-body').value = card.body || '';
  const bodyEditor = document.getElementById('input-body-editor');
  if (bodyEditor) bodyEditor.innerHTML = card.body || '';

  if (card.isPrivate) document.getElementById('privacy-private').checked = true;
  else document.getElementById('privacy-shared').checked = true;

  renderChecklistEdit(card.checklist || []);
  toggleModalChecklist(Array.isArray(card.checklist) && card.checklist.length > 0);
  document.getElementById('input-priority').value = card.priority || '';
  document.querySelectorAll('.priority-btn').forEach(b => {
    b.classList.toggle('active', b.dataset.value === card.priority);
  });

  initSwatches(card.bgcolor || '', card.textcolor || '');
  document.getElementById('modal-title').textContent = '編輯卡片';
  document.getElementById('overlay').classList.add('open');
  setTimeout(() => document.getElementById('input-title').focus(), 60);
}

async function saveCard() {
  const col = document.getElementById('input-col').value;
  const editId = document.getElementById('input-edit-id').value;
  const payload = collectModalData();
  if (!payload.title) {
    document.getElementById('input-title').focus();
    return;
  }

  const data = {
    col,
    title: payload.title,
    project: payload.project,
    priority: payload.priority,
    sourceLink: payload.sourceLink,
    summary: payload.summary,
    nextStep: payload.nextStep,
    body: payload.body,
    bgcolor: payload.bgcolor,
    textcolor: payload.textcolor,
    isPrivate: payload.isPrivate,
    checklist: payload.checklist,
    parentId: payload.parentId,
    level: payload.level
  };

  if (editId) data.id = Number(editId);

  await saveCardToAPI(data);
}

async function saveCardToAPI(cardData) {
  try {
    const res = await fetch('api/cards.php?action=save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(cardData)
    });
    const data = await res.json();
    if (data.success) {
      toast(cardData.id ? '✅ 卡片更新成功' : '✅ 卡片新增成功');
      const currentTab = document.querySelector('.mobile-tab.active')?.dataset?.col || null;
      await loadCards();
      if (currentTab && window.innerWidth <= 768) setMobileTab(currentTab);
      closeModal();
      if (cardData.parentId) filterByParent(cardData.parentId);
      window._spawnParentId = null;
      window._spawnLevel = null;
    } else {
      toast('❌ ' + (data.error || '儲存失敗'));
    }
  } catch (err) {
    toast('❌ 連線錯誤，儲存失敗');
  }
}
```

---

# 另外補 1 段 CSS

把下面這段直接加到你現有的 `<style>` 最後面。

```css
.parent-badge {
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.goal-inline-add {
  width: 100%;
  margin-top: 8px;
  padding: 8px 10px;
  border: 1px dashed rgba(255,255,255,0.18);
  background: rgba(255,255,255,0.04);
  color: rgba(255,255,255,0.88);
  border-radius: 8px;
  cursor: pointer;
  font-size: 12px;
  font-family: inherit;
}
.goal-inline-add:hover {
  background: rgba(255,255,255,0.08);
}
.goal-active-filter {
  box-shadow: 0 0 0 2px rgba(99,102,241,0.35) inset;
}
```

---

# 這次修掉的重點

1. 左側戰略樹現在會用 `updated_at DESC` 排，剛建立或剛編輯的目標會優先在上面。
2. API 現在真的有存 `level / parent_id / goal_col`，不會再只存舊欄位。
3. 右側卡片改成 `level === 'project'` 也算專案，不再只靠 checklist 判斷，所以「一般筆記加代辦就被誤判成專案」這個問題被拆開了。
4. 父子辨識改成完整路徑 badge，例如：`年度：xxx ／ 月度：xxx ／ 週度：xxx`。
5. 年度 / 月度 / 週度目標都可直接點 `✏️` 開獨立編輯框。
6. 從週目標發出去的卡片，會保留 `parentId` 和 `level=project`，不會失聯。
7. 編輯卡片時也會把 `parentId / level` 帶回去，不會一編輯就掉父子關係。
