<?php
require_once 'config/config.php';
requireLogin();

// Only admin can access this page
if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_settings'])) {
    $settings_to_update = [
        'site_name' => $_POST['site_name'] ?? '',
        'admin_email' => $_POST['admin_email'] ?? '',
        'timezone' => $_POST['timezone'] ?? '',
        'session_timeout' => $_POST['session_timeout'] ?? '',
        'password_min_length' => $_POST['password_min_length'] ?? '',
        'max_login_attempts' => $_POST['max_login_attempts'] ?? '',
        'email_notifications' => isset($_POST['email_notifications']) ? '1' : '0',
        'sms_notifications' => isset($_POST['sms_notifications']) ? '1' : '0',
        'gps_update_interval' => $_POST['gps_update_interval'] ?? '',
        'geofence_radius' => $_POST['geofence_radius'] ?? '',
        'location_accuracy_threshold' => $_POST['location_accuracy_threshold'] ?? '',
        'alert_threshold_distance' => $_POST['alert_threshold_distance'] ?? ''
    ];
    
    try {
        $pdo->beginTransaction();
        
        foreach ($settings_to_update as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                                  ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            $stmt->execute([$key, $value, $value]);
        }
        
        $pdo->commit();
        $success = 'Settings updated successfully!';
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Failed to update settings: ' . $e->getMessage();
    }
}


// Load current settings
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $settings = [];
    foreach ($settings_rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Set defaults if not exists
    $default_settings = [
        'site_name' => 'Child Tracking System',
        'admin_email' => 'admin@childtracking.com',
        'timezone' => 'America/New_York',
        'session_timeout' => '3600',
        'password_min_length' => '8',
        'max_login_attempts' => '5',
        'email_notifications' => '1',
        'sms_notifications' => '0',
        'gps_update_interval' => '30',
        'geofence_radius' => '100',
        'location_accuracy_threshold' => '50',
        'alert_threshold_distance' => '500'
    ];
    
    foreach ($default_settings as $key => $default_value) {
        if (!isset($settings[$key])) {
            $settings[$key] = $default_value;
        }
    }
    
} catch (PDOException $e) {
    $error = 'Failed to load settings: ' . $e->getMessage();
    $settings = [];
}

// Get system information
$system_info = [
    'php_version' => phpversion(),
    'mysql_version' => $pdo->query('SELECT VERSION()')->fetchColumn(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'upload_max_filesize' => ini_get('upload_max_filesize')
];

// Get system statistics
try {
    $stmt = $pdo->query("SELECT 
                        (SELECT COUNT(*) FROM users) as total_users,
                        (SELECT COUNT(*) FROM children) as total_children,
                        (SELECT COUNT(*) FROM missing_cases) as total_cases,
                        (SELECT COUNT(*) FROM child_locations) as total_locations,
                        (SELECT COUNT(*) FROM alerts) as total_alerts");
    $system_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $system_stats = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Child Tracking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h2><i class="fas fa-cogs"></i> System Settings</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Settings Tabs -->
            <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                        <i class="fas fa-cog"></i> General
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                        <i class="fas fa-shield-alt"></i> Security
                    </button>
                </li>
    
            </ul>

            <div class="tab-content" id="settingsTabContent">
                <!-- General Settings -->
                <div class="tab-pane fade show active" id="general" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">General Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Site Name</label>
                                            <input type="text" class="form-control" name="site_name" value="<?php echo htmlspecialchars($settings['site_name']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Admin Email</label>
                                            <input type="email" class="form-control" name="admin_email" value="<?php echo htmlspecialchars($settings['admin_email']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Timezone</label>
                                    <select class="form-select" name="timezone">
                                        <option value="America/New_York" <?php echo $settings['timezone'] === 'America/New_York' ? 'selected' : ''; ?>>Eastern Time</option>
                                        <option value="America/Chicago" <?php echo $settings['timezone'] === 'America/Chicago' ? 'selected' : ''; ?>>Central Time</option>
                                        <option value="America/Denver" <?php echo $settings['timezone'] === 'America/Denver' ? 'selected' : ''; ?>>Mountain Time</option>
                                        <option value="America/Los_Angeles" <?php echo $settings['timezone'] === 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time</option>
                                    </select>
                                </div>
                        </div>
                    </div>
                </div>

                <!-- Security Settings -->
                <div class="tab-pane fade" id="security" role="tabpanel">
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5 class="mb-0">Security Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Session Timeout (seconds)</label>
                                        <input type="number" class="form-control" name="session_timeout" value="<?php echo htmlspecialchars($settings['session_timeout']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Password Minimum Length</label>
                                        <input type="number" class="form-control" name="password_min_length" value="<?php echo htmlspecialchars($settings['password_min_length']); ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Max Login Attempts</label>
                                        <input type="number" class="form-control" name="max_login_attempts" value="<?php echo htmlspecialchars($settings['max_login_attempts']); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


               
            </div>

            <!-- Save Button (outside tabs, always visible) -->
            <div class="mt-3">
                <button type="submit" name="update_settings" class="btn btn-primary" form="settingsForm">
                    <i class="fas fa-save"></i> Save Settings
                </button>
            </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Add form ID to all form elements
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form[method="POST"]');
    forms.forEach(function(form, index) {
        if (!form.querySelector('input[name="system_action"]')) {
            form.id = 'settingsForm';
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>
