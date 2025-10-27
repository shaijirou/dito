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

define('SMS_API_URL', 'https://api.semaphore.co/api/v4/messages');
define('SMS_API_KEY', 'f17b086e3d1e0a96cfb1a922f62dc33d'); // Get from https://semaphore.co
define('SMS_SENDER_ID', 'SNIHS');

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

function sendSMSViaSemaphore($phone, $message) {
    global $pdo;
    
    try {
        // Log SMS attempt
        $stmt = $pdo->prepare("INSERT INTO sms_logs (phone_number, message, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$phone, $message]);
        $sms_id = $pdo->lastInsertId();
        
        // Format phone number for Semaphore (ensure it starts with country code)
        $formatted_phone = $phone;
        if (strpos($phone, '+') === false && strpos($phone, '63') !== 0) {
            $formatted_phone = '63' . ltrim($phone, '0');
        }
        
        // Send via Semaphore API
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => SMS_API_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'apikey' => SMS_API_KEY,
                'number' => $formatted_phone,
                'message' => $message,
                'sendername' => SMS_SENDER_ID
            ]),
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Parse response
        $response_data = json_decode($response, true);
        
        if ($http_code === 200 && isset($response_data['status']) && $response_data['status'] === 'success') {
            // Update SMS log as sent
            $stmt = $pdo->prepare("UPDATE sms_logs SET status = 'sent', sent_at = NOW(), response = ? WHERE id = ?");
            $stmt->execute([json_encode($response_data), $sms_id]);
            error_log("[SMS] Successfully sent to $formatted_phone via Semaphore");
            return true;
        } else {
            // Update SMS log as failed
            $stmt = $pdo->prepare("UPDATE sms_logs SET status = 'failed', response = ? WHERE id = ?");
            $stmt->execute([$response, $sms_id]);
            error_log("[SMS] Failed to send to $formatted_phone. Response: $response");
            return false;
        }
    } catch (Exception $e) {
        error_log("[SMS] Error: " . $e->getMessage());
        return false;
    }
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
