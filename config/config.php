<?php
session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'child_tracking_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application settings
define('SITE_URL', 'http://localhost/child_tracking');
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB

// SMS API settings (configure with your SMS provider)
define('SMS_API_URL', 'https://api.sms-provider.com/send');
define('SMS_API_KEY', 'your_sms_api_key_here');
define('SMS_SENDER_ID', 'CALUMALA');

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 6);

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function generateCaseNumber() {
    return 'CASE-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

// Database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("set names utf8");
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
