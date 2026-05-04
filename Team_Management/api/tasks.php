<?php
/**
 * TaskFlow — Tasks REST API
 * Handles AJAX task operations (delete, update status, etc.)
 */
require_once __DIR__ . '/../config/helpers.php';
requireLogin();

header('Content-Type: application/json');

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    jsonResponse(['error' => 'Invalid JSON'], 400);
}

$action  = $data['action']  ?? '';
$taskId  = (int)($data['task_id'] ?? 0);
$user    = currentUser();
$db      = getDB();

// Verify task belongs to a team the user is in
function verifyTaskAccess(PDO $db, int $taskId, int $userId): ?array {
    $st = $db->prepare("
        SELECT t.*, tm.user_id AS member_check
        FROM tasks t
        JOIN team_members tm ON tm.team_id = t.team_id AND tm.user_id = ?
        WHERE t.id = ?
        LIMIT 1
    ");
    $st->execute([$userId, $taskId]);
    return $st->fetch() ?: null;
}

switch ($action) {
    /* ── DELETE ──────────────────────────────────────── */
    case 'delete':
        if (!$taskId) jsonResponse(['error' => 'Missing task ID'], 400);
        $task = verifyTaskAccess($db, $taskId, $user['id']);
        if (!$task) jsonResponse(['error' => 'Task not found or access denied'], 403);
        if ($task['creator_id'] != $user['id']) {
            jsonResponse(['error' => 'You can only delete your own tasks'], 403);
        }
        $db->prepare("DELETE FROM tasks WHERE id = ?")->execute([$taskId]);
        jsonResponse(['success' => true]);

    /* ── UPDATE STATUS ───────────────────────────────── */
    case 'update_status':
        $allowed = ['todo','in_progress','review','done'];
        $status  = $data['status'] ?? '';
        if (!in_array($status, $allowed)) jsonResponse(['error' => 'Invalid status'], 400);
        $task = verifyTaskAccess($db, $taskId, $user['id']);
        if (!$task) jsonResponse(['error' => 'Task not found'], 404);
        $db->prepare("UPDATE tasks SET status=? WHERE id=?")->execute([$status, $taskId]);
        jsonResponse(['success' => true]);

    /* ── LIST (for dynamic filtering) ───────────────── */
    case 'list':
        $teamId = (int)($data['team_id'] ?? 0);
        if (!$teamId) jsonResponse(['error' => 'Missing team_id'], 400);
        // Check membership
        $st = $db->prepare("SELECT 1 FROM team_members WHERE team_id=? AND user_id=?");
        $st->execute([$teamId, $user['id']]);
        if (!$st->fetch()) jsonResponse(['error' => 'Access denied'], 403);
        $st = $db->prepare("
            SELECT t.*, u.name AS creator_name, a.name AS assignee_name
            FROM tasks t
            JOIN users u ON u.id = t.creator_id
            LEFT JOIN users a ON a.id = t.assignee_id
            WHERE t.team_id = ?
            ORDER BY t.created_at DESC
        ");
        $st->execute([$teamId]);
        jsonResponse(['tasks' => $st->fetchAll()]);

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
