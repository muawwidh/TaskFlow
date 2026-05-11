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

$resourcesSt = $db->prepare("
  SELECT *
  FROM external_resources
  WHERE user_id = ?
  ORDER BY created_at DESC
  LIMIT 3
");
$resourcesSt->execute([(int)$user['id']]);
$savedResources = $resourcesSt->fetchAll();

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

        <!-- Quick Guide Video -->
        <section id="quick-guide" class="section-anchor card-tf mb-3">
          <div class="card-tf-header">
            <span style="font-weight:700;font-size:.9rem;">🎬 Quick Guide</span>
          </div>
          <div class="card-tf-body">
            <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:1rem;">
              A short project-management overview for organizing team work.
            </p>
            <figure style="margin:0;">
              <div style="aspect-ratio:16/9;width:100%;overflow:hidden;border-radius:var(--radius-sm);background:var(--surface-2);">
                <video controls preload="metadata" width="100%" height="100%" style="display:block;width:100%;height:100%;object-fit:cover;">
                  <source src="../assets/videos/taskflow-project-management.mp4" type="video/mp4">
                  Your browser does not support embedded video.
                </video>
              </div>
              <figcaption class="text-muted small mt-2">Project management workflow overview</figcaption>
            </figure>
          </div>
        </section>

        <!-- Resources / API Section -->
        <section id="resources" class="section-anchor card-tf">
          <div class="card-tf-header">
            <span style="font-weight:700;font-size:.9rem;">📚 Research Resources</span>
          </div>
          <div class="card-tf-body">
            <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:1rem;">
              Search external project-management references and save useful ones to your workspace.
            </p>
            <div class="api-card-head mb-2">
              <span class="badge-tf badge-low">Static guide</span>
              <a href="https://httpbin.org/html" target="_blank" rel="noopener" class="small">Source</a>
            </div>
            <iframe id="dashboardStaticHtmlFrame" class="api-static-frame api-static-frame-sm" title="Static project resource" sandbox=""></iframe>

            <form id="dashboardWikiSearchForm" class="d-flex gap-2 mt-3">
              <input type="search" id="dashboardWikiSearchInput" class="form-control form-control-sm" placeholder="Search: Kanban" required>
              <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-search"></i></button>
            </form>
            <div id="dashboardWikiResults" class="api-result-list api-result-list-compact mt-2">
              <div class="text-muted small">Search results appear here.</div>
            </div>

            <form id="dashboardSaveResourceForm" class="d-flex gap-2 mt-3">
              <input type="hidden" id="dashboardApiCsrfToken" value="<?= csrf() ?>">
              <input type="search" id="dashboardSaveResourceInput" class="form-control form-control-sm" placeholder="Save topic to database" required>
              <button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-cloud-plus"></i></button>
            </form>
            <div id="dashboardSaveResourceStatus" class="small text-muted mt-2"></div>

            <div id="dashboardSavedResources" class="api-result-list api-result-list-compact mt-3">
              <?php if (empty($savedResources)): ?>
                <div class="text-muted small">No saved resources yet.</div>
              <?php else: ?>
                <?php foreach ($savedResources as $resource): ?>
                  <article class="api-result-item">
                    <h3><?= e($resource['title']) ?></h3>
                    <?php if ($resource['summary_text']): ?>
                      <p><?= e($resource['summary_text']) ?></p>
                    <?php endif; ?>
                    <a href="<?= e($resource['source_url']) ?>" target="_blank" rel="noopener">Open source</a>
                  </article>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
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

    document.addEventListener('DOMContentLoaded', () => {
      const staticFrame = document.getElementById('dashboardStaticHtmlFrame');
      fetch('https://httpbin.org/html')
        .then(res => res.text())
        .then(html => { staticFrame.srcdoc = html; })
        .catch(() => { staticFrame.srcdoc = '<p>Static guide could not be loaded.</p>'; });

      const wikiForm = document.getElementById('dashboardWikiSearchForm');
      const wikiInput = document.getElementById('dashboardWikiSearchInput');
      const wikiResults = document.getElementById('dashboardWikiResults');

      wikiForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const query = wikiInput.value.trim();
        if (!query) return;
        wikiResults.innerHTML = '<div class="text-muted small">Searching...</div>';

        try {
          const url = `https://en.wikipedia.org/w/api.php?action=opensearch&limit=3&namespace=0&format=json&origin=*&search=${encodeURIComponent(query)}`;
          const data = await fetch(url).then(res => res.json());
          const titles = data[1] || [];
          const descriptions = data[2] || [];
          const links = data[3] || [];

          wikiResults.innerHTML = titles.length ? '' : '<div class="text-muted small">No results found.</div>';
          titles.forEach((title, index) => {
            const item = document.createElement('article');
            item.className = 'api-result-item';
            item.innerHTML = '<h3></h3><p></p><a target="_blank" rel="noopener">Open source</a>';
            item.querySelector('h3').textContent = title;
            item.querySelector('p').textContent = descriptions[index] || 'Wikipedia article';
            item.querySelector('a').href = links[index];
            wikiResults.appendChild(item);
          });
        } catch {
          wikiResults.innerHTML = '<div class="text-danger small">Search failed.</div>';
        }
      });

      const saveForm = document.getElementById('dashboardSaveResourceForm');
      const saveInput = document.getElementById('dashboardSaveResourceInput');
      const saveStatus = document.getElementById('dashboardSaveResourceStatus');
      const savedResources = document.getElementById('dashboardSavedResources');

      saveForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const topic = saveInput.value.trim();
        if (!topic) return;
        saveStatus.textContent = 'Fetching and saving...';

        try {
          const response = await fetch('../api/external_resources.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ topic, csrf: document.getElementById('dashboardApiCsrfToken').value })
          });
          const data = await response.json();
          if (!data.success) throw new Error(data.error || 'Could not save resource.');

          const item = document.createElement('article');
          item.className = 'api-result-item';
          item.innerHTML = '<h3></h3><p></p><a target="_blank" rel="noopener">Open source</a>';
          item.querySelector('h3').textContent = data.resource.title;
          item.querySelector('p').textContent = data.resource.summary_text || 'Saved external resource.';
          item.querySelector('a').href = data.resource.source_url;

          if (savedResources.querySelector('.text-muted')) savedResources.innerHTML = '';
          savedResources.prepend(item);
          saveInput.value = '';
          saveStatus.textContent = 'Saved to database.';
        } catch (error) {
          saveStatus.textContent = error.message || 'Could not save resource.';
        }
      });
    });
  </script>
</body>

</html>
