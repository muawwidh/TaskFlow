<?php
/**
 * TaskFlow - External Resources API
 * Fetches Wikipedia data and stores selected results in the local database.
 */
require_once __DIR__ . '/../config/helpers.php';
requireLogin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    jsonResponse(['error' => 'Invalid JSON'], 400);
}

verifyCsrfToken($data['csrf'] ?? null);

$topic = trim($data['topic'] ?? '');
if (strlen($topic) < 2 || strlen($topic) > 120) {
    jsonResponse(['error' => 'Enter a topic between 2 and 120 characters.'], 422);
}

function httpJson(string $url): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: TaskFlowCoursework/1.0 (http://localhost/Team_Management)'
        ],
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $status < 200 || $status >= 300) return null;
    $json = json_decode($body, true);
    return is_array($json) ? $json : null;
}

$searchUrl = 'https://en.wikipedia.org/w/api.php?action=opensearch&limit=1&namespace=0&format=json&search=' . rawurlencode($topic);
$search = httpJson($searchUrl);
$title = $search[1][0] ?? '';
$sourceUrl = $search[3][0] ?? '';

if (!$title || !$sourceUrl) {
    jsonResponse(['error' => 'No Wikipedia result found for that topic.'], 404);
}

$summaryUrl = 'https://en.wikipedia.org/api/rest_v1/page/summary/' . rawurlencode(str_replace(' ', '_', $title));
$summary = httpJson($summaryUrl) ?? [];
$summaryText = $summary['extract'] ?? '';
$savedTitle = $summary['title'] ?? $title;
$savedUrl = $summary['content_urls']['desktop']['page'] ?? $sourceUrl;

$user = currentUser();
$db = getDB();
$st = $db->prepare("
    INSERT INTO external_resources (user_id, topic, title, summary_text, source_url, api_source)
    VALUES (?, ?, ?, ?, ?, 'Wikipedia')
");
$st->execute([(int)$user['id'], $topic, $savedTitle, $summaryText, $savedUrl]);

jsonResponse([
    'success' => true,
    'resource' => [
        'id' => (int)$db->lastInsertId(),
        'topic' => $topic,
        'title' => $savedTitle,
        'summary_text' => $summaryText,
        'source_url' => $savedUrl,
        'api_source' => 'Wikipedia',
        'created_at' => date('Y-m-d H:i:s'),
    ],
]);
