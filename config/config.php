<?php
session_start();

// Database configuration
define('DB_HOST', 'sql209.byethost15.com');
define('DB_NAME', 'b15_40263673_child_tracking_system');
define('DB_USER', 'b15_40263673');
define('DB_PASS', 'ces@2025');

// Application settings
define('SITE_URL', 'http://localhost/child_tracking');
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB



// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('PASSWORD_MIN_LENGTH', 6);
date_default_timezone_set('Asia/Manila');

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

function normalizePhoneNumber($phone) {
    // Remove all non-numeric characters except +
    $phone = preg_replace('/[^0-9+]/', '', trim($phone));
    
    // If it starts with +63, convert to 63format (without +)
    if (strpos($phone, '+63') === 0) {
        $phone = '63' . substr($phone, 3);
    }
    // If it starts with 09, convert to 63format
    else if (strpos($phone, '09') === 0) {
        $phone = '63' . substr($phone, 1);
    }
    // If it starts with 63, keep as is
    else if (strpos($phone, '63') !== 0) {
        // Assume it's a local number starting with 9, add country code
        $phone = '63' . $phone;
    }
    
    return $phone;
}

function sendSMSViaSemaphore($phone, $message) {
    global $pdo;
    
    try {
        if (empty($phone) || empty($message)) {
            error_log("[SMS] Missing phone or message");
            return false;
        }
        
        // Normalize phone number
        $phone = normalizePhoneNumber($phone);
        
        // Check if sms_logs table exists, if not create it
        try {
            $stmt = $pdo->prepare("INSERT INTO sms_logs (phone_number, message, status) VALUES (?, ?, 'pending')");
            $stmt->execute([$phone, $message]);
            $sms_id = $pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("[SMS] sms_logs table error: " . $e->getMessage());
            // Continue without logging if table doesn't exist
            $sms_id = null;
        }
        
        if (empty(SMS_API_KEY) || SMS_API_KEY === 'your_api_key_here') {
            error_log("[SMS] Invalid API key configuration");
            return false;
        }
        
        // Send via Semaphore API
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => SMS_API_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'apikey' => SMS_API_KEY,
                'number' => $phone,
                'message' => $message,
                'sendername' => SMS_SENDER_ID
            ]),
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($curl_error) {
            error_log("[SMS] cURL Error: $curl_error");
            if ($sms_id) {
                $stmt = $pdo->prepare("UPDATE sms_logs SET status = 'failed', response = ? WHERE id = ?");
                $stmt->execute(["cURL Error: $curl_error", $sms_id]);
            }
            return false;
        }
        
        $response_data = json_decode($response, true);

        if ($http_code === 200 && is_array($response_data) && isset($response_data[0]['status']) && in_array($response_data[0]['status'], ['Queued', 'Pending', 'Sent'])) {
            if ($sms_id) {
                $stmt = $pdo->prepare("UPDATE sms_logs SET status = 'sent', sent_at = NOW(), response = ? WHERE id = ?");
                $stmt->execute([json_encode($response_data), $sms_id]);
            }
            error_log("[SMS] Successfully sent to $phone via Semaphore");
            return true;
        } else {
            if ($sms_id) {
                $stmt = $pdo->prepare("UPDATE sms_logs SET status = 'failed', response = ? WHERE id = ?");
                $stmt->execute([$response, $sms_id]);
            }
            error_log("[SMS] Failed to send to $phone. HTTP: $http_code, Response: $response");
            return false;
        }
    } catch (Exception $e) {
        error_log("[SMS] Exception: " . $e->getMessage());
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
