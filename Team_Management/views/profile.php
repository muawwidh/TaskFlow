<?php
require_once __DIR__ . '/../config/helpers.php';
requireLogin();
$activeNav = '';
$user = currentUser();
$db   = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name   = trim($_POST['name'] ?? '');
        $bio    = trim($_POST['bio']  ?? '');
        $avatar = $_POST['avatar'] ?? $user['avatar'];
        if (strlen($name) >= 2) {
            $db->prepare("UPDATE users SET name=?,bio=?,avatar=? WHERE id=?")->execute([$name,$bio,$avatar,$user['id']]);
            flash('success','Profile updated!');
        } else {
            flash('error','Name must be at least 2 characters.');
        }
        header('Location: profile.php'); exit;
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $st = $db->prepare("SELECT password FROM users WHERE id=?"); $st->execute([$user['id']]);
        $row = $st->fetch();
        if (!password_verify($current, $row['password'])) {
            flash('error','Current password is incorrect.');
        } elseif (strlen($new) < 8) {
            flash('error','New password must be at least 8 characters.');
        } elseif ($new !== $confirm) {
            flash('error','Passwords do not match.');
        } else {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost'=>12]);
            $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash,$user['id']]);
            flash('success','Password changed!');
        }
        header('Location: profile.php'); exit;
    }
}

// Stats
$st = $db->prepare("SELECT COUNT(*) FROM tasks WHERE creator_id=?"); $st->execute([$user['id']]);
$totalTasks = $st->fetchColumn();
$st = $db->prepare("SELECT COUNT(*) FROM tasks WHERE creator_id=? AND status='done'"); $st->execute([$user['id']]);
$doneTasks = $st->fetchColumn();
$st = $db->prepare("SELECT COUNT(*) FROM team_members WHERE user_id=?"); $st->execute([$user['id']]);
$teamsCount = $st->fetchColumn();

$flash_success = flash('success');
$flash_error   = flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile — TaskFlow</title>
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

  <div style="max-width:800px;margin:0 auto;">
    <!-- Profile Header -->
    <div class="card-tf card-tf-body mb-4 fade-in" style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap;">
      <div style="font-size:4rem;"><?= e($user['avatar']) ?></div>
      <div style="flex:1;">
        <h1 style="font-size:1.5rem;font-weight:800;"><?= e($user['name']) ?></h1>
        <p style="color:var(--text-muted);margin:.25rem 0;"><?= e($user['email']) ?></p>
        <?php if ($user['bio']): ?>
          <p style="color:var(--text-muted);font-size:.875rem;"><?= e($user['bio']) ?></p>
        <?php endif; ?>
        <p style="font-size:.78rem;color:var(--text-subtle);margin-top:.5rem;">
          Member since <?= date('F Y', strtotime($user['created_at'])) ?>
        </p>
      </div>
      <div style="display:flex;gap:1.5rem;text-align:center;">
        <div><div style="font-size:1.5rem;font-weight:800;font-family:var(--font-head);"><?= $teamsCount ?></div><div style="font-size:.75rem;color:var(--text-muted);">Teams</div></div>
        <div><div style="font-size:1.5rem;font-weight:800;font-family:var(--font-head);"><?= $totalTasks ?></div><div style="font-size:.75rem;color:var(--text-muted);">Tasks</div></div>
        <div><div style="font-size:1.5rem;font-weight:800;font-family:var(--font-head);"><?= $doneTasks ?></div><div style="font-size:.75rem;color:var(--text-muted);">Done</div></div>
      </div>
    </div>

    <!-- Edit Profile -->
    <div class="card-tf mb-4 fade-in">
      <div class="card-tf-header"><span style="font-weight:700;">Edit Profile</span></div>
      <div class="card-tf-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
          <input type="hidden" name="action" value="update_profile">
          <input type="hidden" name="avatar" id="profileAvatarInput" value="<?= e($user['avatar']) ?>">

          <div class="mb-3">
            <label class="form-label fw-medium">Avatar</label>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
              <?php foreach(['🧑','👩','👨','🧑‍💻','👩‍💻','👨‍💻','🎓','🧑‍🎓','🦊','🐼','🦁','🐸'] as $av): ?>
                <button type="button" class="avatar-btn <?= $av===$user['avatar']?'selected':'' ?>" data-avatar="<?= $av ?>"><?= $av ?></button>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Full Name</label>
            <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Bio</label>
            <textarea name="bio" class="form-control" rows="2" placeholder="Tell your team a bit about yourself…"><?= e($user['bio'] ?? '') ?></textarea>
          </div>
          <button type="submit" class="btn btn-primary">Save Profile</button>
        </form>
      </div>
    </div>

    <!-- Change Password -->
    <div class="card-tf fade-in">
      <div class="card-tf-header"><span style="font-weight:700;">Change Password</span></div>
      <div class="card-tf-body">
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
          <input type="hidden" name="action" value="change_password">
          <div class="mb-3">
            <label class="form-label fw-medium">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">New Password</label>
            <input type="password" name="new_password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-medium">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-primary">Change Password</button>
        </form>
      </div>
    </div>
  </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
<script>
document.querySelectorAll('.avatar-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.avatar-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    document.getElementById('profileAvatarInput').value = btn.dataset.avatar;
  });
});
</script>
<style>
.avatar-btn { font-size:1.5rem; background:var(--surface-2); border:2px solid transparent;
  border-radius:10px; width:48px; height:48px; cursor:pointer; transition:all .2s; }
.avatar-btn:hover,.avatar-btn.selected { border-color:var(--accent); background:rgba(99,102,241,.1); }
</style>
</body>
</html>
