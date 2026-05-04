<?php
require_once __DIR__ . '/../config/helpers.php';
requireGuest();
verifyCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php'); exit;
}

$name     = trim($_POST['name']             ?? '');
$email    = trim($_POST['email']            ?? '');
$password = $_POST['password']         ?? '';
$confirm  = $_POST['password_confirm'] ?? '';
$avatar   = $_POST['avatar']           ?? '🧑';

$errors = [];
if (strlen($name) < 2)              $errors[] = 'Name must be at least 2 characters.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
if (strlen($password) < 8)          $errors[] = 'Password must be at least 8 characters.';
if (!preg_match('/[0-9]/', $password)) $errors[] = 'Password must contain a number.';
if ($password !== $confirm)         $errors[] = 'Passwords do not match.';

if ($errors) {
    flash('error', implode(' ', $errors));
    header('Location: register.php'); exit;
}

$db = getDB();
$check = $db->prepare("SELECT id FROM users WHERE email = ?");
$check->execute([$email]);
if ($check->fetch()) {
    flash('error', 'That email is already registered.');
    header('Location: register.php'); exit;
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
$ins  = $db->prepare("INSERT INTO users (name, email, password, avatar) VALUES (?,?,?,?)");
$ins->execute([$name, $email, $hash, $avatar]);

flash('success', 'Account created! Please sign in.');
header('Location: login.php');
exit;
