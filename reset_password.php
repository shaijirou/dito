<?php
require_once 'config/config.php';

$error = '';
$success = '';
$step = null;

// Check if user has started reset process
if (!isset($_SESSION['reset_token']) || !isset($_SESSION['reset_user_id'])) {
    header('Location: forgot_password.php');
    exit();
}

$step = isset($_SESSION['reset_verified']) ? 'password' : 'code';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['reset_code']) && $step === 'code') {
        $reset_code = sanitizeInput($_POST['reset_code']);
        
        if (empty($reset_code)) {
            $error = 'Please enter the reset code.';
        } else {
            try {
                // Verify reset code
                $stmt = $pdo->prepare("
                    SELECT id, user_id FROM password_resets 
                    WHERE user_id = ? 
                    AND reset_code = ? 
                    AND reset_token = ?
                    AND expires_at > NOW()
                    AND verified_at IS NULL
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$_SESSION['reset_user_id'], $reset_code, $_SESSION['reset_token']]);
                $reset_record = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$reset_record) {
                    $error = 'Invalid or expired reset code.';
                } else {
                    // Mark code as verified
                    $stmt = $pdo->prepare("UPDATE password_resets SET verified_at = NOW() WHERE id = ?");
                    $stmt->execute([$reset_record['id']]);
                    $_SESSION['reset_verified'] = true;
                    $success = 'Code verified. Please enter your new password.';
                    $step = 'password';
                }
            } catch (PDOException $e) {
                $error = 'An error occurred. Please try again.';
            }
        }
    } elseif (isset($_POST['password']) && isset($_POST['confirm_password']) && $step === 'password') {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($password) || empty($confirm_password)) {
            $error = 'Please fill in all fields.';
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            $error = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            try {
                // Update user password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $_SESSION['reset_user_id']]);
                
                // Clear reset session
                unset($_SESSION['reset_token']);
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_verified']);
                
                $success = 'Password has been reset successfully. Redirecting to login...';
                header('refresh:3;url=login.php');
            } catch (PDOException $e) {
                $error = 'Failed to update password. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Child Tracking System</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>Reset Password</h1>
                <p><?php echo $step === 'code' ? 'Enter the code sent to your phone' : 'Enter your new password'; ?></p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($step === 'code'): ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="reset_code" class="form-label">Reset Code</label>
                        <input type="text" id="reset_code" name="reset_code" class="form-control" required placeholder="Enter 6-digit code" maxlength="6" inputmode="numeric">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Verify Code</button>
                </form>
            <?php elseif ($step === 'password'): ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="password" class="form-label">New Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Reset Password</button>
                </form>
            <?php endif; ?>
            
            <div class="text-center mt-3">
                <p><a href="login.php">Back to Login</a></p>
            </div>
        </div>
    </div>
</body>
</html>
