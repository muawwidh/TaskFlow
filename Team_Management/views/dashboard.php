<?php
require_once __DIR__ . '/../config/helpers.php';
requireLogin();
$activeNav = 'dashboard';
$user = currentUser();
$db = getDB();

// Stats
$teams = $db->prepare("SELECT COUNT(*) FROM team_members WHERE user_id=?");
$teams->execute([$user['id']]);
$teamCount = $teams->fetchColumn();

$myTasks = $db->prepare("SELECT COUNT(*) FROM tasks t JOIN team_members tm ON tm.team_id=t.team_id WHERE tm.user_id=? AND t.creator_id=?");
$myTasks->execute([$user['id'], $user['id']]);
$myTaskCount = $myTasks->fetchColumn();

$doneTasks = $db->prepare("SELECT COUNT(*) FROM tasks t JOIN team_members tm ON tm.team_id=t.team_id WHERE tm.user_id=? AND t.status='done'");
$doneTasks->execute([$user['id']]);
$doneCount = $doneTasks->fetchColumn();

$urgentTasks = $db->prepare("SELECT COUNT(*) FROM tasks t JOIN team_members tm ON tm.team_id=t.team_id WHERE tm.user_id=? AND t.priority='urgent' AND t.status != 'done'");
$urgentTasks->execute([$user['id']]);
$urgentCount = $urgentTasks->fetchColumn();

// Recent tasks across user's teams
$recentTasks = $db->prepare("
  SELECT t.*, u.name AS creator_name, u.avatar AS creator_avatar, te.name AS team_name
  FROM tasks t
  JOIN users u ON u.id = t.creator_id
  JOIN teams te ON te.id = t.team_id
  JOIN team_members tm ON tm.team_id = t.team_id AND tm.user_id = ?
  ORDER BY t.updated_at DESC
  LIMIT 6
");
$recentTasks->execute([$user['id']]);
$recent = $recentTasks->fetchAll();

// User's teams
$myTeams = $db->prepare("
  SELECT te.*, COUNT(DISTINCT tm2.user_id) AS member_count,
         COUNT(DISTINCT ta.id) AS task_count
  FROM teams te
  JOIN team_members tm  ON tm.team_id  = te.id AND tm.user_id = ?
  LEFT JOIN team_members tm2 ON tm2.team_id = te.id
  LEFT JOIN tasks ta ON ta.team_id = te.id
  GROUP BY te.id
  ORDER BY te.created_at DESC
  LIMIT 4
");
$myTeams->execute([$user['id']]);
$teams = $myTeams->fetchAll();

$flash_success = flash('success');
$flash_error = flash('error');
?>
<!DOCTYPE html>
<html lang="en" <?= ($localStorage_dark = '') ?>>

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — TaskFlow</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link
    href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap"
    rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/css/main.css" rel="stylesheet">
</head>

<body>

  <?php include 'partials/nav.php'; ?>

  <main class="page-wrapper">

    <?php if ($flash_success): ?>
      <div class="alert alert-success alert-auto mb-3 fade-in"><i
          class="bi bi-check-circle-fill me-2"></i><?= e($flash_success) ?></div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
      <div class="alert alert-danger alert-auto mb-3 fade-in"><i
          class="bi bi-exclamation-circle-fill me-2"></i><?= e($flash_error) ?></div>
    <?php endif; ?>

    <!-- Hero Banner -->
    <header class="dash-hero fade-in">
      <div style="position:relative;z-index:1;">
        <h1 style="font-size:1.6rem;font-weight:800;margin-bottom:.25rem;">
          Good <?= date('H') < 12 ? 'morning' : (date('H') < 18 ? 'afternoon' : 'evening') ?>,
          <?= e(explode(' ', $user['name'])[0]) ?> <?= e($user['avatar']) ?>
        </h1>
        <p style="opacity:.85;margin:0;">You have
          <strong><?= $urgentCount ?> urgent</strong> task<?= $urgentCount != 1 ? 's' : '' ?> requiring attention.
        </p>
      </div>
      <div style="position:relative;z-index:1;margin-top:1.5rem;display:flex;gap:.75rem;flex-wrap:wrap;">
        <a href="tasks.php?new=1" class="btn btn-light fw-semibold btn-sm">
          <i class="bi bi-plus-lg me-1"></i> New Task
        </a>
        <a href="teams.php?new=1" class="btn btn-outline-light fw-semibold btn-sm">
          <i class="bi bi-people me-1"></i> Create Team
        </a>
      </div>
    </header>

    <!-- Stats -->
    <section id="stats" class="section-anchor">
      <div class="stats-grid">
        <article class="stat-card fade-in fade-in-1" style="--stat-color:#6366f1;">
          <div class="stat-icon">👥</div>
          <div class="stat-val"><?= $teamCount ?></div>
          <div class="stat-label">Teams Joined</div>
        </article>
        <article class="stat-card fade-in fade-in-2" style="--stat-color:#3b82f6;">
          <div class="stat-icon">📋</div>
          <div class="stat-val"><?= $myTaskCount ?></div>
          <div class="stat-label">My Tasks</div>
        </article>
        <article class="stat-card fade-in fade-in-3" style="--stat-color:#10b981;">
          <div class="stat-icon">✅</div>
          <div class="stat-val"><?= $doneCount ?></div>
          <div class="stat-label">Completed</div>
        </article>
        <article class="stat-card fade-in fade-in-4" style="--stat-color:#ef4444;">
          <div class="stat-icon">🔥</div>
          <div class="stat-val"><?= $urgentCount ?></div>
          <div class="stat-label">Urgent</div>
        </article>
      </div>
    </section>

    <!-- Main 2-col layout -->
    <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem;align-items:start;" class="dashboard-grid">

      <!-- Left: Recent Tasks -->
      <section id="recent-tasks" class="section-anchor">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
          <h2 style="font-size:1.1rem;font-weight:700;">Recent Activity</h2>
          <a href="tasks.php" class="btn btn-sm btn-outline-secondary">View All Tasks</a>
        </div>

        <?php if (empty($recent)): ?>
          <div class="empty-state card-tf card-tf-body">
            <div class="empty-state-icon">📭</div>
            <h3>No tasks yet</h3>
            <p>Join a team and create your first task.</p>
            <a href="teams.php" class="btn btn-primary mt-2">Find a Team</a>
          </div>
        <?php else: ?>
          <div class="task-grid">
            <?php
            $statusLabels = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'review' => 'In Review', 'done' => 'Done'];
            $statusBadge = ['todo' => 'badge-todo', 'in_progress' => 'badge-progress', 'review' => 'badge-review', 'done' => 'badge-done'];
            $prioBadge = ['low' => 'badge-low', 'medium' => 'badge-medium', 'high' => 'badge-high', 'urgent' => 'badge-urgent'];
            foreach ($recent as $i => $task):
              ?>
              <article class="task-card priority-<?= e($task['priority']) ?> fade-in fade-in-<?= min($i + 1, 4) ?>"
                data-expandable data-title="<?= e($task['title']) ?>" data-desc="<?= e($task['description'] ?? '') ?>"
                data-status="<?= e($task['status']) ?>" data-priority="<?= e($task['priority']) ?>">
                <div class="d-flex align-items-start justify-content-between gap-2">
                  <h3 class="task-title"><?= e($task['title']) ?></h3>
                  <div class="d-flex gap-1 flex-shrink-0">
                    <a href="tasks.php?edit=<?= $task['id'] ?>" class="icon-btn" title="Edit"
                      style="width:28px;height:28px;font-size:.8rem;text-decoration:none;">
                      <i class="bi bi-pencil"></i>
                    </a>
                  </div>
                </div>
                <?php if ($task['description']): ?>
                  <p class="task-desc"><?= e($task['description']) ?></p>
                <?php endif; ?>
                <div class="task-meta">
                  <span class="badge-tf <?= $statusBadge[$task['status']] ?>"><?= $statusLabels[$task['status']] ?></span>
                  <span class="badge-tf <?= $prioBadge[$task['priority']] ?>"><?= ucfirst($task['priority']) ?></span>
                  <?php if ($task['due_date']): ?>
                    <span data-due="<?= e($task['due_date']) ?>"
                      style="font-size:.75rem;display:flex;align-items:center;gap:.25rem;">
                      <i class="bi bi-calendar3"></i> <?= date('M j', strtotime($task['due_date'])) ?>
                    </span>
                  <?php endif; ?>
                </div>
                <div
                  style="display:flex;align-items:center;justify-content:space-between;font-size:.78rem;color:var(--text-muted);">
                  <span><?= e($task['creator_avatar']) ?>     <?= e($task['creator_name']) ?></span>
                  <span class="text-accent fw-medium"><?= e($task['team_name']) ?></span>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <!-- Right: Teams + Resources -->
      <aside>
        <!-- My Teams -->
        <section id="my-teams" class="section-anchor mb-3">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;">
            <h2 style="font-size:1.1rem;font-weight:700;">My Teams</h2>
            <a href="teams.php" class="btn btn-sm btn-outline-secondary">All Teams</a>
          </div>
          <?php if (empty($teams)): ?>
            <div class="card-tf card-tf-body text-center" style="font-size:.875rem;color:var(--text-muted);">
              <p>No teams yet.</p>
              <a href="teams.php?new=1" class="btn btn-sm btn-primary mt-1">Create Team</a>
            </div>
          <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:.75rem;">
              <?php foreach ($teams as $team): ?>
                <a href="teams.php?view=<?= $team['id'] ?>" style="text-decoration:none;">
                  <div class="card-tf card-tf-body" style="display:flex;align-items:center;gap:.85rem;">
                    <div
                      style="width:40px;height:40px;border-radius:10px;background:<?= e($team['color']) ?>22;border:2px solid <?= e($team['color']) ?>33;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;">
                      👥
                    </div>
                    <div style="flex:1;min-width:0;">
                      <div
                        style="font-weight:600;font-size:.9rem;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        <?= e($team['name']) ?></div>
                      <div style="font-size:.75rem;color:var(--text-muted);">
                        <?= $team['member_count'] ?> member<?= $team['member_count'] != 1 ? 's' : '' ?> ·
                        <?= $team['task_count'] ?> tasks
                      </div>
                    </div>
                    <i class="bi bi-chevron-right" style="color:var(--text-subtle);"></i>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

        <!-- Resources / Video Section -->
        <section id="resources" class="section-anchor card-tf">
          <div class="card-tf-header">
            <span style="font-weight:700;font-size:.9rem;">📚 Resources</span>
          </div>
          <div class="card-tf-body">
            <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:1rem;">
              New to TaskFlow? Watch these quick guides to get your team productive.
            </p>
            <!-- Embedded Video 1 -->
            <figure style="margin-bottom:1rem;">
              <video controls width="100%" style="border-radius:var(--radius-sm);display:block;" poster="">
                <source src="https://www.w3schools.com/html/mov_bbb.mp4" type="video/mp4">
                Your browser doesn't support video.
              </video>
              <figcaption style="font-size:.75rem;color:var(--text-muted);margin-top:.4rem;">
                Getting started with TaskFlow (2 min)
              </figcaption>
            </figure>
            <!-- Embedded Video 2 -->
            <figure>
              <video controls width="100%" style="border-radius:var(--radius-sm);display:block;">
                <source src="https://www.w3schools.com/html/movie.mp4" type="video/mp4">
                Your browser doesn't support video.
              </video>
              <figcaption style="font-size:.75rem;color:var(--text-muted);margin-top:.4rem;">
                Team collaboration best practices (3 min)
              </figcaption>
            </figure>
          </div>
        </section>
      </aside>
    </div>

    <!-- Visual Gallery Section -->
    <section id="highlights" class="section-anchor" style="margin-top:2.5rem;">
      <h2 style="font-size:1.1rem;font-weight:700;margin-bottom:1rem;">🎨 Feature Highlights</h2>
      <div class="gallery-grid">
        <?php
        $highlights = [
          ['🗂️', 'Kanban Boards', 'Visualize your workflow'],
          ['📊', 'Analytics', 'Track team progress'],
          ['🔔', 'Notifications', 'Never miss a deadline'],
          ['🔗', 'Invite Links', 'Onboard teammates fast'],
          ['🌙', 'Dark Mode', 'Easy on the eyes'],
          ['📱', 'Mobile Ready', 'Work from anywhere'],
        ];
        foreach ($highlights as [$icon, $title, $desc]):
          ?>
          <figure class="gallery-item">
            <span><?= $icon ?></span>
            <div class="gallery-overlay">
              <div>
                <div style="font-size:1rem;margin-bottom:.25rem;"><?= $icon ?></div>
                <strong><?= $title ?></strong>
                <div style="font-size:.75rem;opacity:.9;"><?= $desc ?></div>
              </div>
            </div>
            <figcaption class="visually-hidden"><?= $title ?></figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
    </section>

  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../assets/js/app.js"></script>
  <script>
    // Responsive dashboard grid
    (function () {
      const grid = document.querySelector('.dashboard-grid');
      function adjustGrid() {
        grid.style.gridTemplateColumns = window.innerWidth < 900 ? '1fr' : '1fr 320px';
      }
      adjustGrid();
      window.addEventListener('resize', adjustGrid);
    })();
  </script>
</body>

</html>