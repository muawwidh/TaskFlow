<?php
/**
 * TaskFlow - Database Configuration
 * Edit these values to match your hosting environment
 */

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'taskflow_db');
define('DB_USER', 'root');          // Change in production
define('DB_PASS', '');              // Change in production
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'TaskFlow');
define('APP_URL', 'http://localhost/taskflow');  // Change in production
define('APP_ENV', 'development');   // 'production' in prod
define('SESSION_LIFETIME', 3600);  // 1 hour

/**
 * PDO Connection (singleton pattern)
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT .
               ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            if (APP_ENV === 'development') {
                die(json_encode(['error' => $e->getMessage()]));
            }
            die(json_encode(['error' => 'Database connection failed.']));
        }
    }
    return $pdo;
}
