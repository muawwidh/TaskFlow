<?php
/**
 * TaskFlow - Application Helpers
 */

require_once __DIR__ . '/database.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

/* ── Auth helpers ─────────────────────────────────────── */

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    static $user = null;
    if ($user === null) {
        $db = getDB();
        $st = $db->prepare("SELECT id, name, email, avatar, bio, role, created_at FROM users WHERE id = ?");
        $st->execute([$_SESSION['user_id']]);
        $user = $st->fetch() ?: null;
    }
    return $user;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/auth/login.php');
        exit;
    }
}

function requireGuest(): void {
    if (isLoggedIn()) {
        header('Location: ' . APP_URL . '/views/dashboard.php');
        exit;
    }
}

/* ── Security helpers ─────────────────────────────────── */

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid CSRF token']));
    }
}

/* ── Flash messages ───────────────────────────────────── */

function flash(string $key, string $msg = ''): ?string {
    if ($msg) {
        $_SESSION['flash'][$key] = $msg;
        return null;
    }
    $val = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $val;
}

/* ── Misc ─────────────────────────────────────────────── */

function generateInviteCode(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code  = '';
    for ($i = 0; $i < 8; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    // Ensure uniqueness
    $db = getDB();
    $st = $db->prepare("SELECT id FROM teams WHERE invite_code = ?");
    $st->execute([$code]);
    if ($st->fetch()) return generateInviteCode();
    return $code;
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)      return 'just now';
    if ($diff < 3600)    return floor($diff/60)   . 'm ago';
    if ($diff < 86400)   return floor($diff/3600)  . 'h ago';
    if ($diff < 604800)  return floor($diff/86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
