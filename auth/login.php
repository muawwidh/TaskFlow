<?php
require_once __DIR__ . '/../config/helpers.php';
requireGuest();

$error = flash('error');
$success = flash('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — TaskFlow</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="../assets/css/main.css" rel="stylesheet">
<style>
  .auth-split { min-height:100vh; display:grid; grid-template-columns:1fr 1fr; }
  @media(max-width:768px){ .auth-split{ grid-template-columns:1fr; } .auth-hero{ display:none!important; } }
  .auth-hero {
    background: linear-gradient(135deg, var(--accent) 0%, #7c3aed 50%, #0f172a 100%);
    display:flex; flex-direction:column; justify-content:center; align-items:center;
    padding:3rem; position:relative; overflow:hidden;
  }
  .auth-hero::before {
    content:''; position:absolute; inset:0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Ccircle cx='30' cy='30' r='4'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
  }
  .auth-hero-content { position:relative; z-index:1; text-align:center; color:#fff; }
  .hero-logo { font-family:'Syne',sans-serif; font-size:3rem; font-weight:800; letter-spacing:-2px; }
  .hero-tagline { font-size:1.1rem; opacity:.8; margin-top:.5rem; }
  .floating-card {
    background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.15);
    border-radius:16px; padding:1.5rem; margin:2rem 0; backdrop-filter:blur(10px);
    text-align:left;
  }
  .feature-item { display:flex; align-items:center; gap:.75rem; color:#fff; margin:.5rem 0; font-size:.9rem; }
  .auth-form-side { display:flex; align-items:center; justify-content:center; background:var(--bg); padding:2rem; }
  .auth-card { width:100%; max-width:420px; }
  .auth-title { font-family:'Syne',sans-serif; font-size:2rem; font-weight:800; color:var(--text); }
</style>
</head>
<body>
<main class="auth-split">
  <!-- Hero Side -->
  <section class="auth-hero">
    <div class="auth-hero-content">
      <div class="hero-logo">⚡ TaskFlow</div>
      <p class="hero-tagline">Organize. Collaborate. Deliver.</p>
      <div class="floating-card">
        <div class="feature-item"><i class="bi bi-people-fill"></i> Create and manage student teams</div>
        <div class="feature-item"><i class="bi bi-kanban-fill"></i> Track tasks with Kanban-style boards</div>
        <div class="feature-item"><i class="bi bi-share-fill"></i> Invite teammates via unique codes</div>
        <div class="feature-item"><i class="bi bi-shield-check-fill"></i> Role-based permissions & security</div>
      </div>
      <!-- Embedded intro video -->
      <figure style="border-radius:12px;overflow:hidden;margin-top:1rem;">
        <video width="340" autoplay muted loop playsinline style="border-radius:12px;display:block;">
          <source src="../assets/videos/taskflow-project-management.mp4" type="video/mp4">
        </video>
        <figcaption style="color:rgba(255,255,255,.5);font-size:.75rem;margin-top:.5rem;">Project management workflow overview</figcaption>
      </figure>
    </div>
  </section>

  <!-- Form Side -->
  <section class="auth-form-side">
    <div class="auth-card">
      <div class="auth-title mb-1">Welcome back</div>
      <p class="text-muted mb-4">Sign in to your TaskFlow account</p>

      <?php if ($error): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2">
          <i class="bi bi-exclamation-circle-fill"></i> <?= e($error) ?>
        </div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success d-flex align-items-center gap-2">
          <i class="bi bi-check-circle-fill"></i> <?= e($success) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="login_action.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrf() ?>">

        <div class="mb-3">
          <label class="form-label fw-medium" for="email">Email address</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" id="email" name="email" class="form-control" placeholder="you@university.edu" required autofocus>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-medium" for="password">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
            <button class="btn btn-outline-secondary" type="button" id="togglePwd" title="Toggle visibility">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="remember" id="remember">
            <label class="form-check-label text-muted" for="remember">Remember me</label>
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
          Sign In <i class="bi bi-arrow-right ms-1"></i>
        </button>
      </form>

      <hr class="my-4">
      <p class="text-center text-muted mb-2">Don't have an account?
        <a href="register.php" class="link-primary fw-medium">Create one free</a>
      </p>
      <p class="text-center" style="font-size:.8rem;color:#94a3b8;">
        Demo: alice@demo.com / Password1!
      </p>
    </div>
  </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('togglePwd').addEventListener('click', function() {
  const inp = document.getElementById('password');
  const icon = this.querySelector('i');
  if (inp.type === 'password') { inp.type='text'; icon.className='bi bi-eye-slash'; }
  else { inp.type='password'; icon.className='bi bi-eye'; }
});
</script>
</body>
</html>
