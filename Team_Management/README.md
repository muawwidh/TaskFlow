# TaskFlow — Deployment Guide

## Folder Architecture

```
taskflow/
├── index.php                  ← Entry point (redirects to login/dashboard)
├── config/
│   ├── database.php           ← DB credentials + PDO singleton
│   ├── helpers.php            ← Auth, CSRF, flash, session helpers
│   └── migration.sql          ← Database schema + seed data
├── auth/
│   ├── login.php              ← Login form
│   ├── login_action.php       ← Login POST handler
│   ├── register.php           ← Registration form
│   ├── register_action.php    ← Registration POST handler
│   └── logout.php             ← Session destroy + redirect
├── views/
│   ├── dashboard.php          ← Authenticated home
│   ├── teams.php              ← Team CRUD (create, join, leave, delete)
│   ├── tasks.php              ← Task CRUD with grid + kanban views
│   ├── profile.php            ← User profile + password change
│   └── partials/
│       └── nav.php            ← Shared navigation bar
├── api/
│   └── tasks.php              ← JSON REST API (delete, update_status, list)
└── assets/
    ├── css/main.css           ← Full custom stylesheet (CSS vars, dark mode)
    └── js/app.js              ← All JS interactions (theme, search, filter, toast)
```

---

## Database Tables

| Table          | Purpose                                      |
|----------------|----------------------------------------------|
| `users`        | Registered users with avatar, bio, role      |
| `teams`        | Team records with invite code + color        |
| `team_members` | Many-to-many: users ↔ teams + role           |
| `tasks`        | Tasks with status, priority, due date, assignee |
| `task_comments`| (Bonus) Comments on tasks                   |

---

## Step 1 — Server Requirements

- PHP 8.0+ with PDO + PDO_MySQL extensions
- MySQL 5.7+ or MariaDB 10.3+
- Apache or Nginx with mod_rewrite (optional for clean URLs)

---

## Step 2 — Database Setup

```bash
# Option A: MySQL CLI
mysql -u root -p < config/migration.sql

# Option B: phpMyAdmin
# Import config/migration.sql via the Import tab

# Option C: MySQL Workbench
# File > Run SQL Script > select migration.sql
```

---

## Step 3 — Configuration

Edit `config/database.php`:

```php
define('DB_HOST', 'localhost');        // Your DB host
define('DB_NAME', 'taskflow_db');      // Database name
define('DB_USER', 'your_db_user');     // DB username
define('DB_PASS', 'your_db_password'); // DB password
define('APP_URL',  'https://yourdomain.com/taskflow');
define('APP_ENV',  'production');      // disables error output
```

---

## Step 4 — File Transfer

### Via FTP/SFTP (FileZilla, Cyberduck)
1. Connect to your hosting server
2. Upload entire `taskflow/` folder to `public_html/taskflow/` (or `www/`)
3. Ensure file permissions: directories `755`, PHP files `644`

### Via SSH + Git
```bash
cd /var/www/html
git clone https://github.com/yourrepo/taskflow.git
chmod -R 755 taskflow/
```

### Via cPanel File Manager
1. Zip the `taskflow/` folder locally
2. Upload zip to `public_html/`
3. Extract via File Manager
4. Delete the zip

---

## Step 5 — Web Server Config

### Apache (.htaccess in root)
```apache
RewriteEngine On
RewriteRule ^$ index.php [L]
```

### Nginx (server block)
```nginx
location / {
    try_files $uri $uri/ /taskflow/index.php;
}
```

---

## Step 6 — Security Checklist (Production)

- [ ] Change all demo passwords
- [ ] Set `APP_ENV` to `'production'` in `config/database.php`
- [ ] Use HTTPS (Let's Encrypt / hosting SSL)
- [ ] Set strong DB password, restrict DB user permissions
- [ ] Enable `session.cookie_secure = 1` in PHP for HTTPS
- [ ] Remove seed users from `migration.sql` if already imported
- [ ] Set file upload size limits if adding file attachments later

---

## Step 7 — Post-Deployment Testing

### Manual Test Checklist

**Authentication**
- [ ] Register a new account → redirects to login
- [ ] Login with valid credentials → reaches dashboard
- [ ] Login with wrong password → shows error, no session
- [ ] Logout → session destroyed, redirected to login
- [ ] Access dashboard URL while logged out → redirected to login

**Teams**
- [ ] Create a team → invite code generated, team appears in list
- [ ] Copy invite code → clipboard copy works
- [ ] Join team with valid code → member added
- [ ] Join team with invalid code → error shown
- [ ] Team owner can delete team; non-owner cannot
- [ ] Non-owner can leave team; owner cannot

**Tasks**
- [ ] Create task for a joined team → appears in grid + kanban
- [ ] Edit own task → changes saved
- [ ] Cannot see edit/delete buttons on others' tasks
- [ ] Delete task → removed without page reload (AJAX)
- [ ] Quick status dropdown → updates via AJAX
- [ ] Search bar filters tasks in real time (no reload)
- [ ] Status chips filter correctly
- [ ] Priority chips filter correctly
- [ ] Grid ↔ Kanban view toggle works

**UI / Frontend**
- [ ] Dark mode toggle → persists across pages (localStorage)
- [ ] Mobile hamburger menu opens/closes
- [ ] Gallery hover effects on dashboard
- [ ] Videos load on dashboard sidebar
- [ ] Toast notifications appear for AJAX actions
- [ ] Due date coloring (red = overdue, yellow = soon, green = ok)

---

## Demo Credentials

| Email           | Password   | Role  |
|-----------------|------------|-------|
| alice@demo.com  | Password1! | User  |
| bob@demo.com    | Password1! | User  |
| carol@demo.com  | Password1! | User  |

---

## Feature Summary

| Requirement                        | Implementation                              |
|------------------------------------|---------------------------------------------|
| User registration + login          | `auth/register.php`, `auth/login.php`       |
| Session-based authentication       | PHP sessions + `requireLogin()` guard       |
| CSRF protection                    | `csrf()` token + `verifyCsrf()` on POST     |
| Create / join teams via invite code| `views/teams.php` — POST handlers           |
| Full task CRUD                      | `views/tasks.php` + `api/tasks.php`         |
| Edit/delete own tasks only         | `creator_id == user id` checks              |
| Structured folder architecture     | `/config /auth /tasks /teams /assets /views`|
| MySQL relational tables            | 5 tables with FK constraints                |
| 10+ HTML5 elements                 | article, section, figure, figcaption, video, nav, header, main, aside, details |
| 5 visual components w/ hover       | Gallery grid, team cards, task cards, avatar stack, stat cards |
| 2 embedded videos                  | Dashboard sidebar — `<video>` tags          |
| Navigation + anchor sections       | Nav bar + `#stats`, `#recent-tasks`, etc.   |
| 3+ JS DOM interactions             | Theme toggle, live search filter, chip filters, show/hide panels, AJAX delete |
| Search without page reload         | `taskSearch` input → `filterTasks()`        |
| Responsive layout                  | CSS Grid + Flexbox + Bootstrap breakpoints  |
| User permission tiers              | Unauthenticated: login/register only; Authenticated: full app |
| Dark mode                          | CSS `data-theme` attribute + localStorage   |
| Deployment config                  | `config/database.php` + this README         |
