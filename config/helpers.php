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

function verifyCsrfToken(?string $token): void {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token ?? '')) {
        jsonResponse(['error' => 'Invalid CSRF token'], 403);
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

/* ── Notifications ──────────────────────────────────── */

function notifyUser(
    int $userId,
    string $type,
    string $title,
    string $body,
    ?string $linkUrl = null,
    ?int $actorId = null,
    ?int $teamId = null,
    ?int $taskId = null
): void {
    $db = getDB();
    $st = $db->prepare("
        INSERT INTO notifications (user_id, actor_id, team_id, task_id, type, title, body, link_url)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $st->execute([$userId, $actorId, $teamId, $taskId, $type, $title, $body, $linkUrl]);
}

function notifyTeamMembers(
    int $teamId,
    string $type,
    string $title,
    string $body,
    ?string $linkUrl = null,
    ?int $actorId = null,
    ?int $taskId = null,
    array $excludeUserIds = []
): void {
    $db = getDB();
    $st = $db->prepare("SELECT user_id FROM team_members WHERE team_id = ?");
    $st->execute([$teamId]);
    $exclude = array_map('intval', $excludeUserIds);

    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $memberId) {
        $memberId = (int)$memberId;
        if (in_array($memberId, $exclude, true)) continue;
        notifyUser($memberId, $type, $title, $body, $linkUrl, $actorId, $teamId, $taskId);
    }
}

function unreadNotifications(int $userId, int $limit = 6): array {
    $db = getDB();
    $st = $db->prepare("
        SELECT n.*, u.name AS actor_name, u.avatar AS actor_avatar
        FROM notifications n
        LEFT JOIN users u ON u.id = n.actor_id
        WHERE n.user_id = ? AND n.is_read = 0
        ORDER BY n.created_at DESC
        LIMIT " . max(1, min(20, $limit))
    );
    $st->execute([$userId]);
    return $st->fetchAll();
}

function unreadNotificationCount(int $userId): int {
    $db = getDB();
    $st = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $st->execute([$userId]);
    return (int)$st->fetchColumn();
}

function markAllNotificationsRead(int $userId): void {
    $db = getDB();
    $st = $db->prepare("UPDATE notifications SET is_read = 1, read_at = CURRENT_TIMESTAMP WHERE user_id = ? AND is_read = 0");
    $st->execute([$userId]);
}

function notificationIcon(string $type): array {
    return match ($type) {
        'team_joined' => ['bi-person-plus', 'bg-primary-subtle text-primary'],
        'task_assigned' => ['bi-person-check', 'bg-info-subtle text-info'],
        'task_status_changed' => ['bi-arrow-repeat', 'bg-success-subtle text-success'],
        default => ['bi-bell', 'bg-secondary-subtle text-secondary'],
    };
}
