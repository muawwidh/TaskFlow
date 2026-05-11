<?php
/**
 * TaskFlow - Notifications API
 */
require_once __DIR__ . '/../config/helpers.php';
requireLogin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    jsonResponse(['error' => 'Invalid JSON'], 400);
}

verifyCsrfToken($data['csrf'] ?? null);

$action = $data['action'] ?? '';
$user = currentUser();

switch ($action) {
    case 'mark_all_read':
        markAllNotificationsRead((int)$user['id']);
        jsonResponse(['success' => true]);

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
