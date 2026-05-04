<?php
require_once __DIR__ . '/../config/helpers.php';
requireLogin();
$activeNav = 'tasks';
$user = currentUser();
$db   = getDB();

// Get user's team IDs
$teamSt = $db->prepare("SELECT team_id FROM team_members WHERE user_id=?");
$teamSt->execute([$user['id']]);
$myTeamIds = $teamSt->fetchAll(PDO::FETCH_COLUMN);

// Handle POST (create / update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $title    = trim($_POST['title']    ?? '');
        $desc     = trim($_POST['description'] ?? '');
        $status   = in_array($_POST['status']??'', ['todo','in_progress','review','done']) ? $_POST['status'] : 'todo';
        $priority = in_array($_POST['priority']??'', ['low','medium','high','urgent']) ? $_POST['priority'] : 'medium';
        $due      = $_POST['due_date'] ?? '';
        $teamId   = (int)($_POST['team_id'] ?? 0);
        $assignee = (int)($_POST['assignee_id'] ?? 0) ?: null;

        if (!$title || !$teamId) { flash('error','Title and team are required.'); header('Location: tasks.php'); exit; }
        if (!in_array($teamId, $myTeamIds)) { flash('error','Access denied.'); header('Location: tasks.php'); exit; }

        if ($action === 'create') {
            $db->prepare("INSERT INTO tasks (title,description,status,priority,due_date,team_id,creator_id,assignee_id) VALUES(?,?,?,?,?,?,?,?)")
               ->execute([$title,$desc,$status,$priority,$due?:null,$teamId,$user['id'],$assignee]);
            flash('success','Task created!');
        } else {
            $taskId = (int)($_POST['task_id'] ?? 0);
            $st = $db->prepare("SELECT creator_id,team_id FROM tasks WHERE id=?"); $st->execute([$taskId]);
            $task = $st->fetch();
            if (!$task || $task['creator_id'] != $user['id']) { flash('error','Permission denied.'); header('Location: tasks.php'); exit; }
            $db->prepare("UPDATE tasks SET title=?,description=?,status=?,priority=?,due_date=?,assignee_id=? WHERE id=?")
               ->execute([$title,$desc,$status,$priority,$due?:null,$assignee,$taskId]);
            flash('success','Task updated!');
        }
        header('Location: tasks.php'); exit;
    }
}

// Get task to edit
$editTask = null;
if (isset($_GET['edit'])) {
    $st = $db->prepare("SELECT * FROM tasks WHERE id=?"); $st->execute([(int)$_GET['edit']]);
    $t = $st->fetch();
    if ($t && in_array($t['team_id'], $myTeamIds) && $t['creator_id'] == $user['id']) $editTask = $t;
}

// Filters
$filterTeam   = (int)($_GET['team']     ?? 0);
$filterStatus = $_GET['status']   ?? 'all';
$filterPrio   = $_GET['priority'] ?? 'all';
$searchQ      = trim($_GET['q']    ?? '');
$viewMode     = $_GET['view'] ?? 'grid'; // grid | kanban

// Query
$params = [];
$where  = ["t.team_id IN (SELECT team_id FROM team_members WHERE user_id={$user['id']})"];
if ($filterTeam && in_array($filterTeam, $myTeamIds)) { $where[] = 't.team_id=?'; $params[] = $filterTeam; }
if (in_array($filterStatus, ['todo','in_progress','review','done'])) { $where[] = 't.status=?'; $params[] = $filterStatus; }
if (in_array($filterPrio, ['low','medium','high','urgent'])) { $where[] = 't.priority=?'; $params[] = $filterPrio; }
if ($searchQ) { $where[] = '(t.title LIKE ? OR t.description LIKE ?)'; $params[] = "%$searchQ%"; $params[] = "%$searchQ%"; }

$whereSQL = implode(' AND ', $where);
$st = $db->prepare("
  SELECT t.*, u.name AS creator_name, u.avatar AS creator_avatar,
         a.name AS assignee_name, te.name AS team_name, te.color AS team_color
  FROM tasks t
  JOIN users u   ON u.id = t.creator_id
  JOIN teams te  ON te.id = t.team_id
  LEFT JOIN users a ON a.id = t.assignee_id
  WHERE $whereSQL
  ORDER BY
    CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END,
    t.due_date ASC, t.created_at DESC
");
$st->execute($params);
$tasks = $st->fetchAll();

// Teams for filters/dropdowns
$allTeams = [];
if ($myTeamIds) {
    $ph = implode(',', array_fill(0, count($myTeamIds), '?'));
    $st2 = $db->prepare("SELECT id,name FROM teams WHERE id IN ($ph) ORDER BY name");
    $st2->execute($myTeamIds);
    $allTeams = $st2->fetchAll();
}

// Team members for assignee picker (if team selected)
$members = [];
if ($filterTeam) {
    $st3 = $db->prepare("SELECT u.id, u.name, u.avatar FROM users u JOIN team_members tm ON tm.user_id=u.id WHERE tm.team_id=?");
    $st3->execute([$filterTeam]);
    $members = $st3->fetchAll();
}

$statusLabels = ['todo'=>'To Do','in_progress'=>'In Progress','review'=>'In Review','done'=>'Done'];
$statusBadge  = ['todo'=>'badge-todo','in_progress'=>'badge-progress','review'=>'badge-review','done'=>'badge-done'];
$prioBadge    = ['low'=>'badge-low','medium'=>'badge-medium','high'=>'badge-high','urgent'=>'badge-urgent'];
$prioColors   = ['low'=>'#94a3b8','medium'=>'#3b82f6','high'=>'#f59e0b','urgent'=>'#ef4444'];

$flash_success = flash('success');
$flash_error   = flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tasks — TaskFlow</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="../assets/css/main.css" rel="stylesheet">
</head>
<body>

<?php include 'partials/nav.php'; ?>

<!-- Hidden CSRF for JS operations -->
<input type="hidden" name="csrf_token" value="<?= csrf() ?>" id="csrfToken">

<main class="page-wrapper">

  <?php if ($flash_success): ?>
    <div class="alert alert-success alert-auto fade-in"><i class="bi bi-check-circle-fill me-2"></i><?= e($flash_success) ?></div>
  <?php endif; ?>
  <?php if ($flash_error): ?>
    <div class="alert alert-danger alert-auto fade-in"><i class="bi bi-exclamation-circle-fill me-2"></i><?= e($flash_error) ?></div>
  <?php endif; ?>

  <!-- Page Header -->
  <header class="page-header">
    <div>
      <h1 class="page-title">Tasks</h1>
      <p class="page-subtitle"><?= count($tasks) ?> task<?= count($tasks)!=1?'s':'' ?> found</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <!-- View toggle -->
      <div class="btn-group btn-group-sm" role="group">
        <a href="?<?= http_build_query(array_merge($_GET,['view'=>'grid'])) ?>"
           class="btn btn-<?= $viewMode==='grid'?'primary':'outline-secondary' ?>">
          <i class="bi bi-grid-3x3-gap"></i>
        </a>
        <a href="?<?= http_build_query(array_merge($_GET,['view'=>'kanban'])) ?>"
           class="btn btn-<?= $viewMode==='kanban'?'primary':'outline-secondary' ?>">
          <i class="bi bi-kanban"></i>
        </a>
      </div>
      <?php if ($myTeamIds): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#taskModal">
          <i class="bi bi-plus-lg me-1"></i> New Task
        </button>
      <?php endif; ?>
    </div>
  </header>

  <!-- Filter Bar -->
  <div class="filter-bar" id="filters">
    <div class="search-wrap">
      <i class="bi bi-search"></i>
      <input type="search" id="taskSearch" class="form-control" placeholder="Search tasks…"
             value="<?= e($searchQ) ?>">
    </div>

    <div class="filter-chips">
      <span style="font-size:.8rem;font-weight:600;color:var(--text-muted);align-self:center;">Status:</span>
      <button class="chip <?= $filterStatus==='all'?'active':'' ?>" data-filter="all" data-filter-group="status">All</button>
      <button class="chip <?= $filterStatus==='todo'?'active':'' ?>" data-filter="todo" data-filter-group="status">To Do</button>
      <button class="chip <?= $filterStatus==='in_progress'?'active':'' ?>" data-filter="in_progress" data-filter-group="status">In Progress</button>
      <button class="chip <?= $filterStatus==='review'?'active':'' ?>" data-filter="review" data-filter-group="status">Review</button>
      <button class="chip <?= $filterStatus==='done'?'active':'' ?>" data-filter="done" data-filter-group="status">Done</button>
    </div>

    <div class="filter-chips">
      <span style="font-size:.8rem;font-weight:600;color:var(--text-muted);align-self:center;">Priority:</span>
      <button class="chip <?= $filterPrio==='all'?'active':'' ?>" data-filter="all" data-filter-group="priority">All</button>
      <button class="chip <?= $filterPrio==='urgent'?'active':'' ?>" data-filter="urgent" data-filter-group="priority">🔥 Urgent</button>
      <button class="chip <?= $filterPrio==='high'?'active':'' ?>" data-filter="high" data-filter-group="priority">High</button>
      <button class="chip <?= $filterPrio==='medium'?'active':'' ?>" data-filter="medium" data-filter-group="priority">Medium</button>
      <button class="chip <?= $filterPrio==='low'?'active':'' ?>" data-filter="low" data-filter-group="priority">Low</button>
    </div>

    <?php if ($allTeams): ?>
    <select class="form-select form-select-sm" style="max-width:180px;" onchange="location.href='tasks.php?team='+this.value">
      <option value="">All Teams</option>
      <?php foreach ($allTeams as $t): ?>
        <option value="<?= $t['id'] ?>" <?= $filterTeam==$t['id']?'selected':'' ?>><?= e($t['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php endif; ?>
  </div>

  <!-- No results -->
  <div id="noTasksMsg" hidden class="empty-state card-tf card-tf-body mb-3">
    <div class="empty-state-icon">🔍</div>
    <h3>No tasks match your filters</h3>
    <p>Try adjusting your search or filters.</p>
  </div>

  <!-- ── GRID VIEW ──────────────────────────────────────── -->
  <?php if ($viewMode === 'grid'): ?>
  <?php if (empty($tasks)): ?>
    <div class="empty-state card-tf card-tf-body">
      <div class="empty-state-icon">📋</div>
      <h3>No tasks yet</h3>
      <p>Create your first task for a team.</p>
      <?php if ($myTeamIds): ?>
        <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#taskModal">Create Task</button>
      <?php else: ?>
        <a href="teams.php" class="btn btn-primary mt-2">Join a Team First</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
  <section class="task-grid" id="taskGrid">
    <?php foreach ($tasks as $i => $task): ?>
    <article class="task-card priority-<?= e($task['priority']) ?> fade-in"
             data-title="<?= e($task['title']) ?>"
             data-desc="<?= e($task['description'] ?? '') ?>"
             data-status="<?= e($task['status']) ?>"
             data-priority="<?= e($task['priority']) ?>">

      <div class="d-flex align-items-start justify-content-between gap-2">
        <h3 class="task-title"><?= e($task['title']) ?></h3>
        <?php if ($task['creator_id'] == $user['id']): ?>
        <div class="d-flex gap-1 flex-shrink-0">
          <button class="icon-btn" style="width:28px;height:28px;font-size:.78rem;"
                  onclick="openEditModal(<?= htmlspecialchars(json_encode($task)) ?>)" title="Edit">
            <i class="bi bi-pencil"></i>
          </button>
          <button class="icon-btn" style="width:28px;height:28px;font-size:.78rem;color:var(--danger);"
                  onclick="deleteTask(<?= $task['id'] ?>, this)" title="Delete">
            <i class="bi bi-trash"></i>
          </button>
        </div>
        <?php endif; ?>
      </div>

      <?php if ($task['description']): ?>
        <p class="task-desc"><?= e($task['description']) ?></p>
      <?php endif; ?>

      <div class="task-meta">
        <span class="badge-tf <?= $statusBadge[$task['status']] ?>"><?= $statusLabels[$task['status']] ?></span>
        <span class="badge-tf <?= $prioBadge[$task['priority']] ?>"><?= ucfirst($task['priority']) ?></span>
        <?php if ($task['due_date']): ?>
          <span data-due="<?= e($task['due_date']) ?>" style="font-size:.75rem;display:flex;align-items:center;gap:.25rem;">
            <i class="bi bi-calendar3"></i><?= date('M j', strtotime($task['due_date'])) ?>
          </span>
        <?php endif; ?>
      </div>

      <div style="display:flex;align-items:center;justify-content:space-between;font-size:.78rem;color:var(--text-muted);">
        <span title="Created by"><?= e($task['creator_avatar']) ?> <?= e($task['creator_name']) ?></span>
        <span style="background:<?= e($task['team_color']) ?>22;color:<?= e($task['team_color']) ?>;border-radius:4px;padding:.1rem .4rem;font-size:.7rem;font-weight:700;">
          <?= e($task['team_name']) ?>
        </span>
      </div>

      <?php if ($task['assignee_name']): ?>
        <div style="font-size:.75rem;color:var(--text-muted);">
          <i class="bi bi-person-check me-1"></i>Assigned to <?= e($task['assignee_name']) ?>
        </div>
      <?php endif; ?>

      <!-- Quick status update -->
      <?php if ($task['creator_id'] == $user['id']): ?>
      <select class="form-select form-select-sm" style="font-size:.75rem;"
              onchange="updateTaskStatus(<?= $task['id'] ?>, this.value, document.getElementById('csrfToken').value)">
        <?php foreach ($statusLabels as $sv => $sl): ?>
          <option value="<?= $sv ?>" <?= $task['status']==$sv?'selected':'' ?>><?= $sl ?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
    </article>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <!-- ── KANBAN VIEW ────────────────────────────────────── -->
  <?php else: ?>
  <section class="kanban-board" id="kanbanBoard">
    <?php
    $cols = [
      'todo'        => ['label'=>'To Do',       'icon'=>'⬜', 'color'=>'#64748b'],
      'in_progress' => ['label'=>'In Progress',  'icon'=>'🔵', 'color'=>'#3b82f6'],
      'review'      => ['label'=>'In Review',    'icon'=>'🟡', 'color'=>'#f59e0b'],
      'done'        => ['label'=>'Done',         'icon'=>'🟢', 'color'=>'#10b981'],
    ];
    foreach ($cols as $colStatus => $col):
      $colTasks = array_filter($tasks, fn($t) => $t['status'] === $colStatus);
    ?>
    <div class="kanban-col">
      <div class="kanban-col-header" style="color:<?= $col['color'] ?>;">
        <?= $col['icon'] ?> <?= $col['label'] ?>
        <span class="kanban-count"><?= count($colTasks) ?></span>
      </div>
      <?php foreach ($colTasks as $task): ?>
      <div class="kanban-task"
           data-title="<?= e($task['title']) ?>"
           data-status="<?= e($task['status']) ?>"
           data-priority="<?= e($task['priority']) ?>">
        <div style="font-weight:600;margin-bottom:.4rem;font-size:.875rem;"><?= e($task['title']) ?></div>
        <div style="display:flex;flex-wrap:wrap;gap:.3rem;align-items:center;">
          <span class="badge-tf <?= $prioBadge[$task['priority']] ?>"><?= ucfirst($task['priority']) ?></span>
          <?php if ($task['due_date']): ?>
            <span data-due="<?= e($task['due_date']) ?>" style="font-size:.7rem;">📅 <?= date('M j', strtotime($task['due_date'])) ?></span>
          <?php endif; ?>
        </div>
        <div style="margin-top:.5rem;font-size:.75rem;color:var(--text-muted);display:flex;justify-content:space-between;align-items:center;">
          <span><?= e($task['creator_avatar']) ?> <?= e($task['creator_name']) ?></span>
          <?php if ($task['creator_id'] == $user['id']): ?>
          <div class="d-flex gap-1">
            <button style="background:none;border:none;padding:0;cursor:pointer;color:var(--text-muted);"
                    onclick="openEditModal(<?= htmlspecialchars(json_encode($task)) ?>)"><i class="bi bi-pencil"></i></button>
            <button style="background:none;border:none;padding:0;cursor:pointer;color:var(--danger);"
                    onclick="deleteTask(<?= $task['id'] ?>, this)"><i class="bi bi-trash"></i></button>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($colTasks)): ?>
        <div style="text-align:center;padding:1.5rem .5rem;font-size:.8rem;color:var(--text-subtle);">No tasks here</div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

</main>

<!-- ── Create/Edit Task Modal ──────────────────────────── -->
<div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title font-head fw-800" id="taskModalLabel">New Task</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="taskForm">
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
          <input type="hidden" name="action" value="create" id="formAction">
          <input type="hidden" name="task_id" value="" id="formTaskId">

          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-medium" for="taskTitle">Title *</label>
              <input type="text" name="title" id="taskTitle" class="form-control" placeholder="What needs to be done?" required>
            </div>
            <div class="col-12">
              <label class="form-label fw-medium" for="taskDesc">Description</label>
              <textarea name="description" id="taskDesc" class="form-control" rows="3" placeholder="Add more details…"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-medium" for="taskTeam">Team *</label>
              <select name="team_id" id="taskTeam" class="form-select" required>
                <option value="">Select a team…</option>
                <?php foreach ($allTeams as $t): ?>
                  <option value="<?= $t['id'] ?>" <?= $filterTeam==$t['id']?'selected':'' ?>><?= e($t['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-medium" for="taskDue">Due Date</label>
              <input type="date" name="due_date" id="taskDue" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-medium" for="taskStatus">Status</label>
              <select name="status" id="taskStatus" class="form-select">
                <?php foreach ($statusLabels as $sv => $sl): ?><option value="<?= $sv ?>"><?= $sl ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-medium" for="taskPriority">Priority</label>
              <select name="priority" id="taskPriority" class="form-select">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
                <option value="urgent">🔥 Urgent</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-medium" for="taskAssignee">Assign to</label>
              <select name="assignee_id" id="taskAssignee" class="form-select">
                <option value="">Unassigned</option>
                <?php foreach ($members as $m): ?>
                  <option value="<?= $m['id'] ?>"><?= e($m['avatar'].' '.$m['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="formSubmitBtn">Create Task</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php
// If edit requested, open pre-filled modal
if ($editTask): ?>
<script>
window.addEventListener('DOMContentLoaded', () => {
  openEditModal(<?= json_encode($editTask) ?>);
});
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
<script>
function openEditModal(task) {
  document.getElementById('taskModalLabel').textContent = 'Edit Task';
  document.getElementById('formAction').value   = 'update';
  document.getElementById('formTaskId').value   = task.id;
  document.getElementById('taskTitle').value    = task.title;
  document.getElementById('taskDesc').value     = task.description || '';
  document.getElementById('taskStatus').value  = task.status;
  document.getElementById('taskPriority').value = task.priority;
  document.getElementById('taskDue').value      = task.due_date || '';
  document.getElementById('taskTeam').value     = task.team_id;
  if (task.assignee_id) document.getElementById('taskAssignee').value = task.assignee_id;
  document.getElementById('formSubmitBtn').textContent = 'Save Changes';
  new bootstrap.Modal(document.getElementById('taskModal')).show();
}

// Reset modal on close
document.getElementById('taskModal').addEventListener('hidden.bs.modal', () => {
  document.getElementById('taskModalLabel').textContent = 'New Task';
  document.getElementById('formAction').value  = 'create';
  document.getElementById('formTaskId').value  = '';
  document.getElementById('taskForm').reset();
  document.getElementById('formSubmitBtn').textContent = 'Create Task';
});

// Open new task modal if ?new=1
<?php if (isset($_GET['new'])): ?>
new bootstrap.Modal(document.getElementById('taskModal')).show();
<?php endif; ?>
</script>
</body>
</html>
