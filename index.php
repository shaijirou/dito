<?php
require_once 'config/config.php';

// If user is logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
} else {
    // If not logged in, redirect to login page
    header('Location: login.php');
    exit();
}
?>
