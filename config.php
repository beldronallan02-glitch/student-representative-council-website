<?php
/**
 * config.php
 * Central configuration file for MPPConnect
 * Handles database connection and global settings
 */

// Database connection settings (overridable via environment, e.g. Docker Compose)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'mppconnect');
define('DB_USER', getenv('DB_USER') ?: 'mpp_user');
define('DB_PASS', getenv('DB_PASS') ?: 'YourNewStrongPassword!');
define('DB_PORT', getenv('DB_PORT') ?: '3306');


// Site-wide constants (optional)
define('SITE_NAME', 'MPPConnect');
define('SITE_SUBTITLE', 'Centralized Notice & Feedback System — Universiti Malaysia Sabah');

// Attempt database connection (PDO)
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
}   catch (PDOException $e) {
    // production-safe: log details and show friendly message
    error_log('DB connect error: ' . $e->getMessage());
    http_response_code(500);
    echo "Database connection failed. Please contact the administrator.";
    exit;
}




?>
