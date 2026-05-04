<?php
/**
 * TaskFlow — Entry Point
 * Redirects to dashboard if logged in, otherwise to login
 */
require_once __DIR__ . '/config/helpers.php';

if (isLoggedIn()) {
    header('Location: views/dashboard.php');
} else {
    header('Location: auth/login.php');
}
exit;
