<?php
require_once 'config/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone = sanitizeInput($_POST['phone']);
    
    if (empty($phone)) {
        $error = 'Please enter your phone number.';
    } else {
        try {
            $normalized_phone = normalizePhoneNumber($phone);
            
            // Search by both original and normalized formats for flexibility
            $stmt = $pdo->prepare("SELECT id, email, phone, full_name FROM users WHERE phone = ? OR phone = ? OR phone LIKE ?");
            $stmt->execute([$phone, $normalized_phone, '%' . substr($normalized_phone, -10)]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $error = 'No account found with this phone number.';
            } else {
                $phone_number = trim($user['phone']);
                $phone_number = normalizePhoneNumber($phone_number);
                
                if (strlen($phone_number) < 10) {
                    $error = 'Invalid phone number format. Please contact the administrator.';
                } else {
                    // Generate reset code (6 digits)
                    $reset_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $reset_token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                    
                    // Save reset token to database
                    $stmt = $pdo->prepare("
                        INSERT INTO password_resets (user_id, reset_code, reset_token, expires_at, created_at) 
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$user['id'], $reset_code, $reset_token, $expires_at]);
                    
                    $message = "Your Child Tracking System password reset code is: {$reset_code}. Valid for 30 minutes.";
                    $sms_sent = sendSMSViaSemaphore($phone_number, $message);
                    
                    if ($sms_sent) {
                        $_SESSION['reset_phone'] = $normalized_phone;
                        $_SESSION['reset_token'] = $reset_token;
                        $_SESSION['reset_user_id'] = $user['id'];
                        header('Location: verify_code.php');
                        exit();
                    } else {
                        error_log("[v0] SMS Failed - User: {$user['id']}, Phone: {$phone_number}");
                        $error = 'Failed to send SMS. Please verify your phone number is correct or contact the administrator.';
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("[v0] Password Reset Error: " . $e->getMessage());
            $error = 'An error occurred. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Child Tracking System</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>Forgot Password?</h1>
                <p>Enter your phone number to receive a reset code</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" id="phone" name="phone" class="form-control" required placeholder="09XX-XXX-XXXX or +63XXXXXXXXXX">
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Send Reset Code</button>
            </form>
            
            <div class="text-center mt-3">
                <p><a href="login.php">Back to Login</a></p>
            </div>
        </div>
    </div>
</body>
</html>
