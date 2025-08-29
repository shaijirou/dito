<?php
require_once 'config/config.php';

try {
    // Generate correct password hash
    $password = 'admin123';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Update admin user password
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
    $result = $stmt->execute([$hashed_password]);
    
    if ($result) {
        echo "Admin password updated successfully!\n";
        echo "Username: admin\n";
        echo "Password: admin123\n";
        echo "You can now login with these credentials.\n";
    } else {
        echo "Failed to update password.\n";
    }
    
    // Verify the password works
    $stmt = $pdo->prepare("SELECT password FROM users WHERE username = 'admin'");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        echo "Password verification: SUCCESS\n";
    } else {
        echo "Password verification: FAILED\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>
