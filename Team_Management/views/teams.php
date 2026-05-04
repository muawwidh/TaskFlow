<?php
require_once __DIR__ . '/../config/helpers.php';
requireLogin();
$activeNav = 'teams';
$user = currentUser();
$db   = getDB();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name  = trim($_POST['name'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $color = in_array($_POST['color'] ?? '', ['#6366f1','#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6']) ? $_POST['color'] : '#6366f1';
        if (strlen($name) >= 2) {
            $code = generateInviteCode();
            $ins  = $db->prepare("INSERT INTO teams (name,description,invite_code,owner_id,color) VALUES(?,?,?,?,?)");
            $ins->execute([$name,$desc,$code,$user['id'],$color]);
            $teamId = $db->lastInsertId();
            $db->prepare("INSERT INTO team_members (team_id,user_id,role) VALUES(?,?,'owner')")->execute([$teamId,$user['id']]);
            flash('success', "Team \"$name\" created! Code: $code");
        } else {
            flash('error', 'Team name must be at least 2 characters.');
        }
        header('Location: teams.php'); exit;
    }

    if ($action === 'join') {
        $code = strtoupper(trim($_POST['invite_code'] ?? ''));
        $st   = $db->prepare("SELECT * FROM teams WHERE invite_code=?");
        $st->execute([$code]);
        $team = $st->fetch();
        if (!$team) {
            flash('error', 'Invalid invite code.');
        } else {
            $check = $db->prepare("SELECT 1 FROM team_members WHERE team_id=? AND user_id=?");
            $check->execute([$team['id'],$user['id']]);
            if ($check->fetch()) {
                flash('error', 'You are already a member of that team.');
            } else {
                $db->prepare("INSERT INTO team_members (team_id,user_id) VALUES(?,?)")->execute([$team['id'],$user['id']]);
                flash('success', "Joined team \"{$team['name']}\"!");
            }
        }
        header('Location: teams.php'); exit;
    }

    if ($action === 'leave') {
        $teamId = (int)($_POST['team_id'] ?? 0);
        $st = $db->prepare("SELECT * FROM teams WHERE id=?"); $st->execute([$teamId]);
        $team = $st->fetch();
        if ($team && $team['owner_id'] == $user['id']) {
            flash('error', 'Team owners cannot leave. Transfer ownership or delete the team.');
        } else {
            $db->prepare("DELETE FROM team_members WHERE team_id=? AND user_id=?")->execute([$teamId,$user['id']]);
            flash('success', 'You left the team.');
        }
        header('Location: teams.php'); exit;
    }

    if ($action === 'delete') {
        $teamId = (int)($_POST['team_id'] ?? 0);
        $st = $db->prepare("SELECT owner_id FROM teams WHERE id=?"); $st->execute([$teamId]);
        $team = $st->fetch();
        if ($team && $team['owner_id'] == $user['id']) {
            $db->prepare("DELETE FROM teams WHERE id=?")->execute([$teamId]);
            flash('success', 'Team deleted.');
        } else {
            flash('error', 'Permission denied.');
        }
        header('Location: teams.php'); exit;
    }
}

// Fetch user's teams with member snippets
$myTeams = $db->prepare("
  SELECT te.*, u.name AS owner_name,
         COUNT(DISTINCT tm.user_id) AS member_count,
         COUNT(DISTINCT ta.id) AS task_count,
         tm2.role AS my_role
  FROM teams te
  JOIN team_members tm  ON tm.team_id  = te.id
  JOIN team_members tm2 ON tm2.team_id = te.id AND tm2.user_id = ?
  JOIN users u ON u.id = te.owner_id
  LEFT JOIN tasks ta ON ta.team_id = te.id
  WHERE tm2.user_id = ?
  GROUP BY te.id
  ORDER BY te.created_at DESC
");
$myTeams->execute([$user['id'],$user['id']]);
$teams = $myTeams->fetchAll();

// For each team, get first 4 member avatars
$memberAvatars = [];
foreach ($teams as $t) {
    $st = $db->prepare("SELECT u.avatar FROM team_members tm JOIN users u ON u.id=tm.user_id WHERE tm.team_id=? LIMIT 4");
    $st->execute([$t['id']]);
    $memberAvatars[$t['id']] = $st->fetchAll(PDO::FETCH_COLUMN);
}

$flash_success = flash('success');
$flash_error   = flash('error');
$showNew  = ($_GET['new'] ?? '') === '1';
$showJoin = ($_GET['join'] ?? '') === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teams — TaskFlow</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="../assets/css/main.css" rel="stylesheet">
</head>
<body>

<?php include 'partials/nav.php'; ?>

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
      <h1 class="page-title">Teams</h1>
      <p class="page-subtitle">Manage your student project teams</p>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-outline-secondary" id="btnJoin">
        <i class="bi bi-key me-1"></i> Join via Code
      </button>
      <button class="btn btn-primary" id="btnCreate">
        <i class="bi bi-plus-lg me-1"></i> Create Team
      </button>
    </div>
  </header>

  <!-- Join Team Panel (hidden by default) -->
  <section id="joinPanel" class="card-tf card-tf-body mb-4 <?= $showJoin?'':'d-none' ?>">
    <h2 style="font-size:1rem;font-weight:700;margin-bottom:1rem;">🔑 Join a Team</h2>
    <form method="POST" class="d-flex gap-2 align-items-end flex-wrap">
      <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="join">
      <div class="flex-grow-1">
        <label class="form-label fw-medium" for="inviteCode">Invite Code</label>
        <input type="text" name="invite_code" id="inviteCode" class="form-control"
               placeholder="e.g. ABCD1234" maxlength="8" style="text-transform:uppercase;letter-spacing:2px;font-family:monospace;" required>
      </div>
      <button type="submit" class="btn btn-primary">Join Team</button>
    </form>
  </section>

  <!-- Create Team Panel (hidden by default) -->
  <section id="createPanel" class="card-tf card-tf-body mb-4 <?= $showNew?'':'d-none' ?>">
    <h2 style="font-size:1rem;font-weight:700;margin-bottom:1rem;">🚀 Create New Team</h2>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="create">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-medium" for="teamName">Team Name *</label>
          <input type="text" name="name" id="teamName" class="form-control" placeholder="e.g. CS 301 Group A" required maxlength="100">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-medium">Team Color</label>
          <div class="d-flex gap-2 flex-wrap">
            <?php foreach(['#6366f1','#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6'] as $c): ?>
              <label style="cursor:pointer;">
                <input type="radio" name="color" value="<?= $c ?>" class="visually-hidden" <?= $c==='#6366f1'?'checked':'' ?>>
                <span style="display:block;width:32px;height:32px;border-radius:50%;background:<?= $c ?>;border:3px solid transparent;transition:border-color .2s;"
                      class="color-swatch" data-color="<?= $c ?>"></span>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label fw-medium" for="teamDesc">Description</label>
          <textarea name="description" id="teamDesc" class="form-control" rows="2"
                    placeholder="Brief description of your team's project..."></textarea>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary">Create Team</button>
          <button type="button" class="btn btn-outline-secondary ms-2" id="cancelCreate">Cancel</button>
        </div>
      </div>
    </form>
  </section>

  <!-- Teams Grid -->
  <section id="teams-list" class="section-anchor">
    <?php if (empty($teams)): ?>
      <div class="empty-state card-tf card-tf-body">
        <div class="empty-state-icon">👥</div>
        <h3>No teams yet</h3>
        <p>Create a team or join one with an invite code to get started.</p>
        <div class="d-flex gap-2 justify-content-center mt-2">
          <button class="btn btn-primary" id="btnCreateEmpty">Create Team</button>
          <button class="btn btn-outline-secondary" id="btnJoinEmpty">Join with Code</button>
        </div>
      </div>
    <?php else: ?>
      <div class="team-grid">
        <?php foreach($teams as $i => $team): ?>
        <article class="team-card fade-in fade-in-<?= min($i+1,4) ?>">
          <!-- Banner -->
          <div class="team-card-banner" style="background:<?= e($team['color']) ?>22;">
            <span style="font-size:2.5rem;">👥</span>
            <?php if ($team['my_role'] === 'owner'): ?>
              <span style="position:absolute;top:.5rem;right:.5rem;" class="badge-tf badge-done">Owner</span>
            <?php endif; ?>
          </div>
          <!-- Body -->
          <div class="team-card-body">
            <div class="team-card-name"><?= e($team['name']) ?></div>
            <?php if ($team['description']): ?>
              <p style="font-size:.82rem;color:var(--text-muted);margin:.25rem 0 .75rem;"><?= e($team['description']) ?></p>
            <?php endif; ?>

            <!-- Stats row -->
            <div style="display:flex;gap:1.25rem;font-size:.8rem;color:var(--text-muted);margin:.5rem 0 .75rem;">
              <span><i class="bi bi-people me-1"></i><?= $team['member_count'] ?> members</span>
              <span><i class="bi bi-kanban me-1"></i><?= $team['task_count'] ?> tasks</span>
            </div>

            <!-- Member avatars -->
            <div class="avatar-stack mb-3">
              <?php foreach ($memberAvatars[$team['id']] as $av): ?>
                <div class="avatar-stack-item" title="Member"><?= e($av) ?></div>
              <?php endforeach; ?>
            </div>

            <!-- Invite code -->
            <div class="team-invite copy-invite" data-code="<?= e($team['invite_code']) ?>">
              <i class="bi bi-clipboard"></i>
              <?= e($team['invite_code']) ?>
            </div>

            <!-- Actions -->
            <div class="d-flex gap-2 mt-3 flex-wrap">
              <a href="tasks.php?team=<?= $team['id'] ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-kanban me-1"></i> Tasks
              </a>
              <?php if ($team['my_role'] === 'owner'): ?>
                <button type="button" class="btn btn-sm btn-outline-danger ms-auto"
                        onclick="confirmDelete(<?= $team['id'] ?>, '<?= e(addslashes($team['name'])) ?>')">
                  <i class="bi bi-trash"></i>
                </button>
              <?php else: ?>
                <form method="POST" class="ms-auto">
                  <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
                  <input type="hidden" name="action" value="leave">
                  <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-secondary"
                          onclick="return confirm('Leave this team?')">Leave</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- Hidden delete form -->
  <form method="POST" id="deleteTeamForm" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="team_id" id="deleteTeamId">
  </form>

</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
<script>
// Panel toggles — DOM interactions #1
const joinPanel   = document.getElementById('joinPanel');
const createPanel = document.getElementById('createPanel');

function showPanel(which) {
  joinPanel.classList.add('d-none');
  createPanel.classList.add('d-none');
  if (which === 'join')   joinPanel.classList.remove('d-none');
  if (which === 'create') createPanel.classList.remove('d-none');
}

document.getElementById('btnJoin')?.addEventListener('click', () => showPanel('join'));
document.getElementById('btnCreate')?.addEventListener('click', () => showPanel('create'));
document.getElementById('btnJoinEmpty')?.addEventListener('click', () => showPanel('join'));
document.getElementById('btnCreateEmpty')?.addEventListener('click', () => showPanel('create'));
document.getElementById('cancelCreate')?.addEventListener('click', () => createPanel.classList.add('d-none'));

// Color swatch selection highlight
document.querySelectorAll('input[name="color"]').forEach(r => {
  r.addEventListener('change', () => {
    document.querySelectorAll('.color-swatch').forEach(s => s.style.borderColor='transparent');
    r.nextElementSibling.style.borderColor='var(--text)';
  });
});
document.querySelector('input[name="color"]:checked')?.nextElementSibling?.style &&
  (document.querySelector('input[name="color"]:checked').nextElementSibling.style.borderColor='var(--text)');

// Invite code uppercase
document.getElementById('inviteCode')?.addEventListener('input', function(){ this.value = this.value.toUpperCase(); });

function confirmDelete(teamId, teamName) {
  if (!confirm(`Delete team "${teamName}"? All tasks will be permanently deleted.`)) return;
  document.getElementById('deleteTeamId').value = teamId;
  document.getElementById('deleteTeamForm').submit();
}
</script>
</body>
</html>
