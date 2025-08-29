<?php
require_once 'config/config.php';
requireLogin();

$error = '';
$success = '';

// Get alerts based on user role
try {
    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'teacher') {
        $stmt = $pdo->query("SELECT a.*, c.first_name, c.last_name, c.lrn, mc.case_number
                            FROM alerts a 
                            JOIN children c ON a.child_id = c.id 
                            LEFT JOIN missing_cases mc ON a.case_id = mc.id
                            ORDER BY a.created_at DESC 
                            LIMIT 100");
    } else {
        // Parents see only alerts for their children
        $stmt = $pdo->prepare("SELECT a.*, c.first_name, c.last_name, c.lrn, mc.case_number
                              FROM alerts a 
                              JOIN children c ON a.child_id = c.id 
                              JOIN parent_child pc ON c.id = pc.child_id
                              LEFT JOIN missing_cases mc ON a.case_id = mc.id
                              WHERE pc.parent_id = ? 
                              ORDER BY a.created_at DESC 
                              LIMIT 100");
        $stmt->execute([$_SESSION['user_id']]);
    }
    $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Failed to load alerts data.';
    $alerts = [];
}

// Mark alert as read
if (isset($_POST['mark_read'])) {
    $alert_id = (int)$_POST['alert_id'];
    try {
        $stmt = $pdo->prepare("UPDATE alerts SET status = 'sent' WHERE id = ?");
        $stmt->execute([$alert_id]);
        $success = 'Alert marked as read.';
        // Refresh alerts
        header('Location: alerts.php');
        exit();
    } catch (PDOException $e) {
        $error = 'Failed to update alert status.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerts - Child Tracking System</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="main-content">
            <div class="d-flex justify-between align-center mb-3">
                <h1>Alerts & Notifications</h1>
                <div class="d-flex gap-2">
                    <span class="badge badge-danger">
                        <?php echo count(array_filter($alerts, function($alert) { return $alert['severity'] === 'critical'; })); ?> Critical
                    </span>
                    <span class="badge badge-warning">
                        <?php echo count(array_filter($alerts, function($alert) { return $alert['severity'] === 'warning'; })); ?> Warning
                    </span>
                </div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- Alert Statistics -->
            <div class="dashboard-grid mb-3">
                <div class="stat-card">
                    <div class="stat-number">
                        <?php echo count(array_filter($alerts, function($alert) { return $alert['alert_type'] === 'missing'; })); ?>
                    </div>
                    <div class="stat-label">Missing Child Alerts</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number">
                        <?php echo count(array_filter($alerts, function($alert) { return $alert['alert_type'] === 'geofence_exit'; })); ?>
                    </div>
                    <div class="stat-label">Geofence Alerts</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number">
                        <?php echo count(array_filter($alerts, function($alert) { return $alert['created_at'] >= date('Y-m-d H:i:s', strtotime('-24 hours')); })); ?>
                    </div>
                    <div class="stat-label">Last 24 Hours</div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Alerts (<?php echo count($alerts); ?>)</h2>
                </div>
                
                <?php if (empty($alerts)): ?>
                    <div class="text-center p-3">
                        <p>No alerts found.</p>
                    </div>
                <?php else: ?>
                    <div style="max-height: 600px; overflow-y: auto;">
                        <?php foreach ($alerts as $alert): ?>
                        <div class="alert alert-<?php echo $alert['severity'] === 'critical' ? 'danger' : ($alert['severity'] === 'warning' ? 'warning' : 'info'); ?>" style="margin: 1rem; border-left: 4px solid;">
                            <div class="d-flex justify-between align-center">
                                <div style="flex: 1;">
                                    <div class="d-flex align-center gap-2 mb-2">
                                        <span class="badge badge-<?php echo $alert['alert_type'] === 'missing' ? 'danger' : ($alert['alert_type'] === 'geofence_exit' ? 'warning' : 'info'); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $alert['alert_type'])); ?>
                                        </span>
                                        
                                        <span class="badge badge-<?php echo $alert['severity'] === 'critical' ? 'danger' : ($alert['severity'] === 'warning' ? 'warning' : 'info'); ?>">
                                            <?php echo ucfirst($alert['severity']); ?>
                                        </span>
                                        
                                        <?php if ($alert['case_number']): ?>
                                            <span class="badge badge-secondary">Case: <?php echo htmlspecialchars($alert['case_number']); ?></span>
                                        <?php endif; ?>
                                        
                                        <small class="text-muted">
                                            <?php echo date('M j, Y g:i A', strtotime($alert['created_at'])); ?>
                                        </small>
                                    </div>
                                    
                                    <div>
                                        <strong>Child:</strong> <?php echo htmlspecialchars($alert['first_name'] . ' ' . $alert['last_name']); ?> 
                                        (<?php echo htmlspecialchars($alert['lrn']); ?>)
                                    </div>
                                    
                                    <div class="mt-2">
                                        <strong>Message:</strong> <?php echo htmlspecialchars($alert['message']); ?>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <small>
                                            <strong>SMS:</strong> <?php echo $alert['sms_sent'] ? 'Sent' : 'Not Sent'; ?> | 
                                            <strong>Email:</strong> <?php echo $alert['email_sent'] ? 'Sent' : 'Not Sent'; ?> | 
                                            <strong>Status:</strong> <?php echo ucfirst($alert['status']); ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-1">
                                    <?php if ($alert['case_number']): ?>
                                        <a href="case_details.php?case=<?php echo urlencode($alert['case_number']); ?>" class="btn btn-sm btn-primary">View Case</a>
                                    <?php endif; ?>
                                    
                                    <a href="track_child.php?id=<?php echo $alert['child_id']; ?>" class="btn btn-sm btn-success">Track</a>
                                    
                                    <?php if ($alert['status'] === 'pending'): ?>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                            <button type="submit" name="mark_read" class="btn btn-sm btn-secondary">Mark Read</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        // Auto-refresh alerts every 30 seconds
        setInterval(function() {
            // In a real implementation, you might use AJAX to refresh alerts without reloading the page
            // For now, we'll just add a visual indicator that alerts are being checked
            console.log('Checking for new alerts...');
        }, 30000);
        
        // Play notification sound for critical alerts (if any)
        document.addEventListener('DOMContentLoaded', function() {
            const criticalAlerts = document.querySelectorAll('.alert-danger');
            if (criticalAlerts.length > 0) {
                // You could add audio notification here
                console.log('Critical alerts detected');
            }
        });
    </script>
</body>
</html>
