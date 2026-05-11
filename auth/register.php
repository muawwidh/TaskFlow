<?php
require_once __DIR__ . '/../config/helpers.php';
requireGuest();
$error = flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account — TaskFlow</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="../assets/css/main.css" rel="stylesheet">
<style>
  .auth-split { min-height:100vh; display:grid; grid-template-columns:1fr 1fr; }
  @media(max-width:768px){ .auth-split{ grid-template-columns:1fr; } .auth-hero{ display:none!important; } }
  .auth-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 60%, var(--accent) 100%);
    display:flex; flex-direction:column; justify-content:center; align-items:center;
    padding:3rem; position:relative; overflow:hidden;
  }
  .auth-hero::after {
    content:''; position:absolute; bottom:-50px; right:-50px;
    width:250px; height:250px; border-radius:50%;
    background:rgba(99,102,241,.15); border:1px solid rgba(99,102,241,.3);
  }
  .auth-form-side { display:flex; align-items:center; justify-content:center; background:var(--bg); padding:2rem; }
  .auth-card { width:100%; max-width:460px; }
  .auth-title { font-family:'Syne',sans-serif; font-size:2rem; font-weight:800; color:var(--text); }
  .avatar-picker { display:flex; gap:.5rem; flex-wrap:wrap; }
  .avatar-btn { font-size:1.5rem; background:var(--surface); border:2px solid transparent;
    border-radius:10px; width:48px; height:48px; cursor:pointer; transition:all .2s; }
  .avatar-btn:hover,.avatar-btn.selected { border-color:var(--accent); background:rgba(99,102,241,.1); }
  .strength-bar { height:4px; border-radius:2px; background:#e2e8f0; margin-top:.4rem; }
  .strength-fill { height:100%; border-radius:2px; transition:all .3s; }
</style>
</head>
<body>
<main class="auth-split">
  <!-- Hero -->
  <section class="auth-hero" style="color:#fff;">
    <div style="position:relative;z-index:1;text-align:center;">
      <div style="font-family:'Syne',sans-serif;font-size:2.8rem;font-weight:800;letter-spacing:-2px;">⚡ TaskFlow</div>
      <p style="opacity:.8;margin:.5rem 0 2rem;">Join thousands of student teams</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;text-align:left;">
        <article style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:1.2rem;">
          <div style="font-size:1.5rem">🚀</div>
          <div style="font-weight:600;margin:.5rem 0 .25rem;">Fast Setup</div>
          <div style="font-size:.85rem;opacity:.75;">Create a team and invite members in seconds</div>
        </article>
        <article style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:1.2rem;">
          <div style="font-size:1.5rem">🎯</div>
          <div style="font-weight:600;margin:.5rem 0 .25rem;">Stay Focused</div>
          <div style="font-size:.85rem;opacity:.75;">Prioritize tasks and meet every deadline</div>
        </article>
        <article style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:1.2rem;">
          <div style="font-size:1.5rem">🔐</div>
          <div style="font-weight:600;margin:.5rem 0 .25rem;">Secure</div>
          <div style="font-size:.85rem;opacity:.75;">Role-based access keeps data safe</div>
        </article>
        <article style="background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:1.2rem;">
          <div style="font-size:1.5rem">📱</div>
          <div style="font-weight:600;margin:.5rem 0 .25rem;">Responsive</div>
          <div style="font-size:.85rem;opacity:.75;">Works on any device, anytime</div>
        </article>
      </div>
      <figure style="border-radius:12px;overflow:hidden;margin-top:1rem;">
        <video width="340" autoplay muted loop playsinline style="border-radius:12px;display:block;">
          <source src="../assets/videos/taskflow-project-management.mp4" type="video/mp4">
        </video>
        <figcaption style="color:rgba(255,255,255,.5);font-size:.75rem;margin-top:.5rem;">Project management workflow overview</figcaption>
      </figure>
    </div>
  </section>

  <!-- Form -->
  <section class="auth-form-side">
    <div class="auth-card">
      <div class="auth-title mb-1">Create account</div>
      <p class="text-muted mb-4">Free forever for student teams</p>

      <?php if ($error): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle-fill me-2"></i><?= e($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="register_action.php" novalidate id="regForm">
        <input type="hidden" name="csrf_token" value="<?= csrf() ?>">
        <input type="hidden" name="avatar" id="avatarInput" value="🧑">

        <div class="mb-3">
          <label class="form-label fw-medium">Choose your avatar</label>
          <div class="avatar-picker" role="group" aria-label="Avatar picker">
            <?php foreach(['🧑','👩','👨','🧑‍💻','👩‍💻','👨‍💻','🎓','🧑‍🎓','🦊','🐼','🦁','🐸'] as $av): ?>
              <button type="button" class="avatar-btn <?= $av==='🧑'?'selected':'' ?>" data-avatar="<?= $av ?>"><?= $av ?></button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-medium" for="name">Full name</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-person"></i></span>
            <input type="text" id="name" name="name" class="form-control" placeholder="Jane Doe" required>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-medium" for="email">Email</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
            <input type="email" id="email" name="email" class="form-control" placeholder="you@university.edu" required>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-medium" for="password">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock"></i></span>
            <input type="password" id="password" name="password" class="form-control" placeholder="Min 8 chars, 1 number" required>
            <button class="btn btn-outline-secondary" type="button" id="togglePwd"><i class="bi bi-eye"></i></button>
          </div>
          <div class="strength-bar mt-2"><div class="strength-fill" id="strengthFill"></div></div>
          <small id="strengthLabel" class="text-muted"></small>
        </div>

        <div class="mb-4">
          <label class="form-label fw-medium" for="password_confirm">Confirm password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
            <input type="password" id="password_confirm" name="password_confirm" class="form-control" placeholder="Repeat password" required>
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
          Create Account <i class="bi bi-arrow-right ms-1"></i>
        </button>
      </form>

      <hr class="my-3">
      <p class="text-center text-muted">Already have an account?
        <a href="login.php" class="link-primary fw-medium">Sign in</a>
      </p>
    </div>
  </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Avatar picker
document.querySelectorAll('.avatar-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.avatar-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    document.getElementById('avatarInput').value = btn.dataset.avatar;
  });
});

// Password toggle
document.getElementById('togglePwd').addEventListener('click', function() {
  const inp = document.getElementById('password');
  const icon = this.querySelector('i');
  if (inp.type === 'password') { inp.type='text'; icon.className='bi bi-eye-slash'; }
  else { inp.type='password'; icon.className='bi bi-eye'; }
});

// Password strength
document.getElementById('password').addEventListener('input', function() {
  const val = this.value;
  const fill = document.getElementById('strengthFill');
  const label = document.getElementById('strengthLabel');
  let score = 0;
  if (val.length >= 8) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;
  const colors = ['','#ef4444','#f97316','#eab308','#22c55e'];
  const labels = ['','Weak','Fair','Good','Strong'];
  fill.style.width = (score * 25) + '%';
  fill.style.background = colors[score] || '';
  label.textContent = labels[score] || '';
  label.style.color = colors[score] || '';
});
</script>
</body>
</html>
