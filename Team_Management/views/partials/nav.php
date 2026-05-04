<?php
// views/partials/nav.php
// Requires $activeNav to be set by the including page
$user = currentUser();
$nav = $activeNav ?? '';
?>
<nav class="app-nav" role="navigation" aria-label="Main navigation">
  <div class="nav-inner">
    <!-- Brand -->
    <a href="../views/dashboard.php" class="nav-brand">
      <span class="brand-icon">⚡</span>
      <span class="brand-name">TaskFlow</span>
    </a>

    <!-- Desktop Links -->
    <ul class="nav-links" role="list">
      <li><a href="../views/dashboard.php" class="nav-link <?= $nav === 'dashboard' ? 'active' : '' ?>">
          <i class="bi bi-grid-1x2"></i> Dashboard
        </a></li>
      <li><a href="../views/teams.php" class="nav-link <?= $nav === 'teams' ? 'active' : '' ?>">
          <i class="bi bi-people"></i> Teams
        </a></li>
      <li><a href="../views/tasks.php" class="nav-link <?= $nav === 'tasks' ? 'active' : '' ?>">
          <i class="bi bi-kanban"></i> Tasks
        </a></li>
    </ul>

    <!-- Right Side -->
    <div class="nav-actions">
      <!-- Theme Toggle (JS-powered) -->
      <button id="themeToggle" class="icon-btn" title="Toggle dark mode" aria-label="Toggle theme">
        <i class="bi bi-moon-stars"></i>
      </button>

      <!-- Notifications placeholder -->
      <div class="dropdown">
        <button class="icon-btn position-relative dropdown-toggle no-caret" id="notifDropdown" data-bs-toggle="dropdown"
          aria-expanded="false" title="Notifications">
          <i class="bi bi-bell"></i>
          <span class="badge bg-danger position-absolute top-0 end-0" style="font-size:.55rem;padding:2px 4px;">3</span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-lg py-0" aria-labelledby="notifDropdown"
          style="width: 280px; max-height: 400px; overflow-y: auto;">
          <li class="p-3 border-bottom d-flex justify-content-between align-items-center">
            <span class="fw-bold small text-uppercase">Notifications</span>
            <button class="btn btn-link btn-sm p-0 text-decoration-none" style="font-size:.7rem;">Mark all read</button>
          </li>
          <li>
            <a class="dropdown-item p-3 d-flex gap-3 border-bottom" href="#">
              <div
                class="notif-icon bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center"
                style="width:32px; height:32px; flex-shrink:0;">
                <i class="bi bi-plus-lg"></i>
              </div>
              <div>
                <div class="small fw-semibold">New task assigned</div>
                <div class="text-muted" style="font-size: .75rem;">Design the login page mockup</div>
                <div class="text-accent mt-1" style="font-size: .65rem;">2 minutes ago</div>
              </div>
            </a>
          </li>
          <li>
            <a class="dropdown-item p-3 d-flex gap-3 border-bottom" href="#">
              <div
                class="notif-icon bg-success-subtle text-success rounded-circle d-flex align-items-center justify-content-center"
                style="width:32px; height:32px; flex-shrink:0;">
                <i class="bi bi-check2-all"></i>
              </div>
              <div>
                <div class="small fw-semibold">Task Completed</div>
                <div class="text-muted" style="font-size: .75rem;">Team Alpha updated task status to "Done"</div>
                <div class="text-accent mt-1" style="font-size: .65rem;">1 hour ago</div>
              </div>
            </a>
          </li>
          <li>
            <a class="dropdown-item p-3 d-flex gap-3" href="#">
              <div
                class="notif-icon bg-warning-subtle text-warning rounded-circle d-flex align-items-center justify-content-center"
                style="width:32px; height:32px; flex-shrink:0;">
                <i class="bi bi-exclamation-triangle"></i>
              </div>
              <div>
                <div class="small fw-semibold">Deadline Approaching</div>
                <div class="text-muted" style="font-size: .75rem;">"Database Migration" is due in 3 hours</div>
                <div class="text-accent mt-1" style="font-size: .65rem;">3 hours ago</div>
              </div>
            </a>
          </li>
          <li class="p-2 text-center bg-light">
            <a href="#" class="small text-muted text-decoration-none">View all notifications</a>
          </li>
        </ul>
      </div>

      <!-- Avatar dropdown -->
      <div class="dropdown">
        <button class="avatar-trigger dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="avatar-emoji"><?= e($user['avatar'] ?? '🧑') ?></span>
          <span class="avatar-name d-none d-md-inline"><?= e(explode(' ', $user['name'])[0]) ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-lg">
          <li>
            <div class="px-3 py-2 border-bottom">
              <div class="fw-semibold"><?= e($user['name']) ?></div>
              <div class="text-muted small"><?= e($user['email']) ?></div>
            </div>
          </li>
          <li><a class="dropdown-item" href="../views/profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
          <li>
            <hr class="dropdown-divider">
          </li>
          <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i
                class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
        </ul>
      </div>

      <!-- Mobile burger -->
      <button class="icon-btn d-md-none" id="mobileMenuBtn" aria-label="Open menu">
        <i class="bi bi-list fs-5"></i>
      </button>
    </div>
  </div>

  <!-- Mobile drawer -->
  <div class="mobile-drawer" id="mobileDrawer" hidden>
    <a href="../views/dashboard.php" class="mobile-link">
      <i class="bi bi-grid-1x2"></i> Dashboard
    </a>
    <a href="../views/teams.php" class="mobile-link">
      <i class="bi bi-people"></i> Teams
    </a>
    <a href="../views/tasks.php" class="mobile-link">
      <i class="bi bi-kanban"></i> Tasks
    </a>
    <a href="../auth/logout.php" class="mobile-link text-danger">
      <i class="bi bi-box-arrow-right"></i> Sign Out
    </a>
  </div>
</nav>