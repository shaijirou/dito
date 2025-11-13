<?php
require_once 'config/config.php';

$error = '';
$success = '';

if (!isset($_SESSION['reset_token']) || !isset($_SESSION['reset_user_id'])) {
    header('Location: forgot_password.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $reset_code = sanitizeInput($_POST['reset_code']);
    
    if (empty($reset_code)) {
        $error = 'Please enter the reset code.';
    } else {
        try {
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
                header('Location: reset_password.php');
                exit();
            }
        } catch (PDOException $e) {
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
    <title>Verify Code - Child Tracking System</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h1>Verify Code</h1>
                <p>Enter the 6-digit code sent to your phone</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="reset_code" class="form-label">Reset Code</label>
                    <input type="text" id="reset_code" name="reset_code" class="form-control" required placeholder="Enter 6-digit code" maxlength="6" inputmode="numeric">
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Verify Code</button>
            </form>
            
            <div class="text-center mt-3">
                <p><a href="forgot_password.php">Back to Forgot Password</a></p>
            </div>
        </div>
    </div>
</body>
</html>
