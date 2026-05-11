<?php
require_once __DIR__ . '/../config/helpers.php';
requireGuest();
verifyCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$email    = trim($_POST['email']    ?? '');
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    flash('error', 'Please fill in all fields.');
    header('Location: login.php');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('error', 'Invalid email address.');
    header('Location: login.php');
    exit;
}

$db = getDB();
$st = $db->prepare("SELECT id, name, password FROM users WHERE email = ? LIMIT 1");
$st->execute([$email]);
$user = $st->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    flash('error', 'Incorrect email or password.');
    header('Location: login.php');
    exit;
}

// Regenerate session ID to prevent fixation
session_regenerate_id(true);
$_SESSION['user_id']   = $user['id'];
$_SESSION['user_name'] = $user['name'];

if (!empty($_POST['remember'])) {
    // 30-day persistent session
    $lifetime = 60 * 60 * 24 * 30;
    session_set_cookie_params($lifetime);
    setcookie(session_name(), session_id(), time() + $lifetime, '/');
}

header('Location: ../views/dashboard.php');
exit;
