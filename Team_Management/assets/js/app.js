/**
 * TaskFlow — Main JavaScript
 * DOM interactions, theme toggle, search, filters, etc.
 */

/* ── Theme Toggle ───────────────────────────────────── */
(function initTheme() {
  const saved = localStorage.getItem('tf-theme') || 'light';
  document.documentElement.setAttribute('data-theme', saved);
  updateThemeIcon(saved);
})();

function updateThemeIcon(theme) {
  const btn = document.getElementById('themeToggle');
  if (!btn) return;
  btn.innerHTML = theme === 'dark'
    ? '<i class="bi bi-sun"></i>'
    : '<i class="bi bi-moon-stars"></i>';
  btn.title = theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
}

document.addEventListener('DOMContentLoaded', () => {
  /* ── 1. THEME TOGGLE ─────────────────────────────── */
  const themeBtn = document.getElementById('themeToggle');
  if (themeBtn) {
    themeBtn.addEventListener('click', () => {
      const current = document.documentElement.getAttribute('data-theme') || 'light';
      const next = current === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', next);
      localStorage.setItem('tf-theme', next);
      updateThemeIcon(next);
    });
  }

  /* ── 2. MOBILE MENU TOGGLE ───────────────────────── */
  const mobileBtn = document.getElementById('mobileMenuBtn');
  const mobileDrawer = document.getElementById('mobileDrawer');
  if (mobileBtn && mobileDrawer) {
    mobileBtn.addEventListener('click', () => {
      const isHidden = mobileDrawer.hidden;
      mobileDrawer.hidden = !isHidden;
      mobileBtn.querySelector('i').className = isHidden ? 'bi bi-x fs-5' : 'bi bi-list fs-5';
    });
    // Close on outside click
    document.addEventListener('click', e => {
      if (!mobileBtn.contains(e.target) && !mobileDrawer.contains(e.target)) {
        mobileDrawer.hidden = true;
        mobileBtn.querySelector('i').className = 'bi bi-list fs-5';
      }
    });
  }

  /* ── 3. TASK SEARCH (live filter, no reload) ─────── */
  const searchInput = document.getElementById('taskSearch');
  if (searchInput) {
    searchInput.addEventListener('input', debounce(filterTasks, 200));
  }

  /* ── 4. STATUS CHIP FILTERS ──────────────────────── */
  document.querySelectorAll('.chip[data-filter]').forEach(chip => {
    chip.addEventListener('click', () => {
      const group = chip.dataset.filterGroup || 'status';
      document.querySelectorAll(`.chip[data-filter-group="${group}"]`).forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      filterTasks();
    });
  });

  /* ── 5. COPY INVITE CODE ─────────────────────────── */
  document.querySelectorAll('.copy-invite').forEach(el => {
    el.addEventListener('click', () => {
      const code = el.dataset.code;
      navigator.clipboard.writeText(code).then(() => {
        const orig = el.innerHTML;
        el.innerHTML = '<i class="bi bi-check2"></i> Copied!';
        el.style.color = '#10b981';
        setTimeout(() => { el.innerHTML = orig; el.style.color = ''; }, 2000);
      });
    });
  });

  /* ── 6. FLASH AUTO-DISMISS ───────────────────────── */
  document.querySelectorAll('.alert-auto').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity .4s';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 400);
    }, 4000);
  });

  /* ── 7. CONFIRM DELETES ──────────────────────────── */
  document.querySelectorAll('[data-confirm]').forEach(btn => {
    btn.addEventListener('click', e => {
      if (!confirm(btn.dataset.confirm || 'Are you sure?')) e.preventDefault();
    });
  });

  /* ── 8. TASK CARD EXPAND / COLLAPSE ─────────────── */
  document.querySelectorAll('.task-card[data-expandable]').forEach(card => {
    card.addEventListener('click', e => {
      if (e.target.closest('a,button')) return;
      card.classList.toggle('expanded');
      const desc = card.querySelector('.task-desc');
      if (desc) {
        if (card.classList.contains('expanded')) {
          desc.style.webkitLineClamp = 'unset';
        } else {
          desc.style.webkitLineClamp = '2';
        }
      }
    });
  });

  /* ── 9. DUE DATE COLORING ────────────────────────── */
  document.querySelectorAll('[data-due]').forEach(el => {
    const due = new Date(el.dataset.due);
    const now = new Date();
    const diff = (due - now) / 86400000;
    if (diff < 0) el.classList.add('due-overdue');
    else if (diff < 3) el.classList.add('due-soon');
    else el.classList.add('due-ok');
  });

  /* ── 10. SECTION REVEAL ON SCROLL ────────────────── */
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('fade-in');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: .1 });
    document.querySelectorAll('.reveal').forEach(el => io.observe(el));
  }
});

/* ── Live Task Filter Function ───────────────────────── */
function filterTasks() {
  const query = (document.getElementById('taskSearch')?.value || '').toLowerCase();
  const statusChip = document.querySelector('.chip[data-filter-group="status"].active');
  const prioChip = document.querySelector('.chip[data-filter-group="priority"].active');

  const statusFilter = statusChip ? statusChip.dataset.filter : 'all';
  const prioFilter = prioChip ? prioChip.dataset.filter : 'all';

  let visibleCount = 0;

  document.querySelectorAll('.task-card, .kanban-task').forEach(card => {
    const title = (card.dataset.title || '').toLowerCase();
    const desc = (card.dataset.desc || '').toLowerCase();
    const status = (card.dataset.status || '');
    const prio = (card.dataset.priority || '');

    const matchSearch = !query || title.includes(query) || desc.includes(query);
    const matchStatus = statusFilter === 'all' || status === statusFilter;
    const matchPrio = prioFilter === 'all' || prio === prioFilter;

    const show = matchSearch && matchStatus && matchPrio;
    card.style.display = show ? '' : 'none';
    if (show) visibleCount++;
  });

  const empty = document.getElementById('noTasksMsg');
  if (empty) empty.hidden = visibleCount > 0;
}

/* ── AJAX Task Operations ────────────────────────────── */
async function deleteTask(taskId, btn) {
  if (!confirm('Delete this task? This cannot be undone.')) return;
  btn.disabled = true;
  try {
    const res = await fetch('../api/tasks.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete', task_id: taskId, csrf: document.querySelector('[name=csrf_token]')?.value })
    });
    const data = await res.json();
    if (data.success) {
      const card = btn.closest('.task-card, .kanban-task, tr');
      if (card) { card.style.opacity = '0'; setTimeout(() => { card.remove(); filterTasks(); }, 300); }
      showToast('Task deleted.', 'success');
    } else {
      showToast(data.error || 'Could not delete task.', 'error');
      btn.disabled = false;
    }
  } catch {
    showToast('Network error.', 'error');
    btn.disabled = false;
  }
}

async function updateTaskStatus(taskId, status, csrfToken) {
  try {
    const res = await fetch('../api/tasks.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'update_status', task_id: taskId, status, csrf: csrfToken })
    });
    const data = await res.json();
    if (data.success) showToast('Status updated!', 'success');
    else showToast(data.error || 'Failed.', 'error');
  } catch { showToast('Network error.', 'error'); }
}

/* ── Toast Notifications ─────────────────────────────── */
function showToast(msg, type = 'info') {
  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    container.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;';
    document.body.appendChild(container);
  }
  const icons = { success: 'check-circle-fill', error: 'exclamation-circle-fill', info: 'info-circle-fill' };
  const colors = { success: '#10b981', error: '#ef4444', info: '#3b82f6' };
  const toast = document.createElement('div');
  toast.style.cssText = `background:var(--surface);border:1px solid var(--border);border-left:3px solid ${colors[type]};border-radius:10px;padding:.75rem 1rem;box-shadow:var(--shadow-lg);display:flex;align-items:center;gap:.6rem;font-size:.875rem;min-width:240px;max-width:320px;animation:fadeInUp .25s ease;`;
  toast.innerHTML = `<i class="bi bi-${icons[type]}" style="color:${colors[type]};font-size:1rem;"></i><span>${msg}</span>`;
  container.appendChild(toast);
  setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity .3s'; setTimeout(() => toast.remove(), 300); }, 3000);
}

/* ── Utility ─────────────────────────────────────────── */
function debounce(fn, ms) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}


document.addEventListener('DOMContentLoaded', () => {
  const markReadBtn = document.querySelector('.dropdown-menu .btn-link');
  if (markReadBtn) {
    markReadBtn.addEventListener('click', (e) => {
      e.preventDefault();
      const badge = document.querySelector('#notifDropdown .badge');
      if (badge) badge.style.display = 'none';
      showToast('All notifications marked as read', 'success');
    });
  }
});
