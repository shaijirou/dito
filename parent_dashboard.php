<?php
require_once 'config/config.php';
requireLogin();

// Ensure only parents can access this dashboard
if ($_SESSION['role'] !== 'parent') {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$parent_id = $_SESSION['user_id'];

try {
    // Get parent's children
    $stmt = $pdo->prepare("SELECT c.*, 
                          (SELECT COUNT(*) FROM missing_cases mc WHERE mc.child_id = c.id AND mc.status = 'active') as active_cases,
                          (SELECT COUNT(*) FROM alerts a WHERE a.child_id = c.id AND a.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as recent_alerts,
                          (SELECT timestamp FROM location_tracking lt WHERE lt.child_id = c.id ORDER BY timestamp DESC LIMIT 1) as last_location_update
                          FROM children c 
                          JOIN parent_child pc ON c.id = pc.child_id 
                          WHERE pc.parent_id = ? AND c.status = 'active'
                          ORDER BY c.first_name, c.last_name");
    $stmt->execute([$parent_id]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $total_children = count($children);
    $children_with_devices = count(array_filter($children, function($child) { return !empty($child['lrn']); }));
    $total_active_cases = array_sum(array_column($children, 'active_cases'));
    $total_recent_alerts = array_sum(array_column($children, 'recent_alerts'));
    
    // Get recent alerts for parent's children
    $stmt = $pdo->prepare("SELECT a.*, c.first_name, c.last_name, c.lrn, mc.case_number
                          FROM alerts a 
                          JOIN children c ON a.child_id = c.id 
                          JOIN parent_child pc ON c.id = pc.child_id
                          LEFT JOIN missing_cases mc ON a.case_id = mc.id
                          WHERE pc.parent_id = ?
                          ORDER BY a.created_at DESC 
                          LIMIT 10");
    $stmt->execute([$parent_id]);
    $recent_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent cases for parent's children
    $stmt = $pdo->prepare("SELECT mc.*, c.first_name, c.last_name, c.lrn
                          FROM missing_cases mc 
                          JOIN children c ON mc.child_id = c.id 
                          JOIN parent_child pc ON c.id = pc.child_id
                          WHERE pc.parent_id = ?
                          ORDER BY mc.created_at DESC 
                          LIMIT 5");
    $stmt->execute([$parent_id]);
    $recent_cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Failed to load dashboard data.';
    $children = [];
    $recent_alerts = [];
    $recent_cases = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard - Child Tracking System</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="parent-dashboard">
             Welcome Section 
            <div class="welcome-section">
                <h1>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h1>
                <p>Keep track of your children's safety and location</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
             Quick Statistics 
            <div class="quick-stats">
                <div class="stat-card-parent">
                    <div class="stat-number-parent"><?php echo $total_children; ?></div>
                    <div class="stat-label-parent">My Children</div>
                </div>
                
                <div class="stat-card-parent">
                    <div class="stat-number-parent"><?php echo $children_with_devices; ?></div>
                    <div class="stat-label-parent">With Tracking Device</div>
                </div>
                
                <div class="stat-card-parent">
                    <div class="stat-number-parent"><?php echo $total_active_cases; ?></div>
                    <div class="stat-label-parent">Active Cases</div>
                </div>
                
                <div class="stat-card-parent">
                    <div class="stat-number-parent"><?php echo $total_recent_alerts; ?></div>
                    <div class="stat-label-parent">Alerts (24h)</div>
                </div>
            </div>
            
            <?php if (empty($children)): ?>
                 No Children Message 
                <div class="no-children-message">
                    <h2>No Children Registered</h2>
                    <p>It looks like you don't have any children registered in the system yet.</p>
                    <p>Please contact the school administration to register your children.</p>
                    <a href="contact.php" class="btn btn-primary">Contact School</a>
                </div>
            <?php else: ?>
                 Children Cards 
                <div class="children-grid">
                    <?php foreach ($children as $child): ?>
                    <div class="child-card">
                        <div class="child-header">
                            <?php if ($child['photo']): ?>
                                <img src="<?php echo htmlspecialchars($child['photo']); ?>" alt="Child Photo" class="child-photo">
                            <?php else: ?>
                                <div class="child-photo-placeholder">No Photo</div>
                            <?php endif; ?>
                            
                            <div class="child-info">
                                <h3><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></h3>
                                <div class="child-details">
                                    Grade <?php echo htmlspecialchars($child['grade']); ?> • 
                                    LRN : <?php echo htmlspecialchars($child['lrn']); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="child-status">
                            
                            <div class="status-item">
                                <div class="status-indicator <?php echo $child['active_cases'] > 0 ? 'status-danger' : 'status-safe'; ?>"></div>
                                <span><?php echo $child['active_cases'] > 0 ? $child['active_cases'] . ' Active Case(s)' : 'No Active Cases'; ?></span>
                            </div>
                            
                            <?php if ($child['recent_alerts'] > 0): ?>
                            <div class="status-item">
                                <div class="status-indicator status-warning"></div>
                                <span><?php echo $child['recent_alerts']; ?> Alert(s) Today</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($child['last_location_update']): ?>
                        <div style="font-size: 0.85rem; color: #666; margin-bottom: 1rem;">
                            <strong>Last Location Update:</strong><br>
                            <?php echo date('M j, Y g:i A', strtotime($child['last_location_update'])); ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="child-actions">
                            <a href="track_child.php?id=<?php echo $child['id']; ?>" class="btn btn-sm btn-success">
                                📍 Track Location
                            </a>
                            <a href="child_profile.php?id=<?php echo $child['id']; ?>" class="btn btn-sm btn-primary">
                                👤 View Profile
                            </a>
                            <?php if ($child['active_cases'] > 0): ?>
                                <a href="my_cases.php?child_id=<?php echo $child['id']; ?>" class="btn btn-sm btn-danger">
                                    🚨 View Cases
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                 Recent Alerts Section 
                <?php if (!empty($recent_alerts)): ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Recent Alerts</h2>
                        <a href="alerts.php" class="btn btn-sm btn-primary">View All Alerts</a>
                    </div>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php foreach ($recent_alerts as $alert): ?>
                        <div class="alert-item <?php echo $alert['severity']; ?>">
                            <div class="d-flex justify-between align-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($alert['first_name'] . ' ' . $alert['last_name']); ?></strong>
                                    <span class="badge badge-<?php echo $alert['alert_type'] === 'missing' ? 'danger' : 'warning'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $alert['alert_type'])); ?>
                                    </span>
                                    <br>
                                    <small><?php echo htmlspecialchars($alert['message']); ?></small>
                                    <br>
                                    <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($alert['created_at'])); ?></small>
                                </div>
                                <div>
                                    <a href="track_child.php?id=<?php echo $alert['child_id']; ?>" class="btn btn-sm btn-success">Track</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                 Recent Cases Section 
                <?php if (!empty($recent_cases)): ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Recent Cases</h2>
                        <a href="my_cases.php" class="btn btn-sm btn-primary">View All Cases</a>
                    </div>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Case Number</th>
                                <th>Child</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_cases as $case): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($case['case_number']); ?></td>
                                <td><?php echo htmlspecialchars($case['first_name'] . ' ' . $case['last_name']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $case['status'] === 'active' ? 'danger' : ($case['status'] === 'resolved' ? 'success' : 'secondary'); ?>">
                                        <?php echo ucfirst($case['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $case['priority'] === 'critical' ? 'danger' : ($case['priority'] === 'high' ? 'warning' : 'info'); ?>">
                                        <?php echo ucfirst($case['priority']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($case['created_at'])); ?></td>
                                <td>
                                    <a href="case_details.php?case=<?php echo urlencode($case['case_number']); ?>" class="btn btn-sm btn-primary">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                 Quick Actions 
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Quick Actions</h2>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="my_children.php" class="btn btn-primary">📋 View All My Children</a>
                        <a href="alerts.php" class="btn btn-warning">🔔 View All Alerts</a>
                        <a href="my_cases.php" class="btn btn-danger">📁 View All Cases</a>
                        <a href="emergency_contacts.php" class="btn btn-info">📞 Emergency Contacts</a>
                        <a href="settings.php" class="btn btn-secondary">⚙️ Settings</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        // Auto-refresh dashboard every 2 minutes
        setInterval(function() {
            // Check for new alerts or updates
            fetch('api/check_updates.php')
                .then(response => response.json())
                .then(data => {
                    if (data.new_alerts > 0) {
                        // Show notification or update UI
                        console.log('New alerts available');
                        // You could add a notification badge or refresh the page
                    }
                })
                .catch(error => console.log('Update check failed'));
        }, 120000); // 2 minutes
        
        // Add click tracking for analytics
        document.querySelectorAll('.child-card').forEach(card => {
            card.addEventListener('click', function(e) {
                if (!e.target.closest('.btn')) {
                    // If not clicking a button, go to child profile
                    const trackBtn = this.querySelector('a[href*="child_profile"]');
                    if (trackBtn) {
                        window.location.href = trackBtn.href;
                    }
                }
            });
        });
        
        // Show loading state for buttons
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!this.href || this.href.includes('#')) return;
                
                this.style.opacity = '0.7';
                this.style.pointerEvents = 'none';
                
                // Reset after 3 seconds in case of slow loading
                setTimeout(() => {
                    this.style.opacity = '1';
                    this.style.pointerEvents = 'auto';
                }, 3000);
            });
        });
    </script>
</body>
</html>
