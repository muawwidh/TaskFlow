<?php
require_once __DIR__ . '/../config/helpers.php';
session_unset();
session_destroy();
header('Location: ../auth/login.php');
exit;
