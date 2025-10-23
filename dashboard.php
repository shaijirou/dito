<?php
require_once 'config/config.php';
requireLogin();

// Get dashboard statistics
$stats = [];

try {
    // Total children
    if ($_SESSION['role'] === 'admin') {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM children WHERE status = 'active'");
        $stats['total_children'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } elseif ($_SESSION['role'] === 'teacher') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM children c 
                              JOIN teacher_child tc ON c.id = tc.child_id 
                              WHERE tc.teacher_id = ? AND c.status = 'active'");
        $stmt->execute([$_SESSION['user_id']]);
        $stats['total_children'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    else {
        // For parents, count only their children
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM children c 
                              JOIN parent_child pc ON c.id = pc.child_id 
                              WHERE pc.parent_id = ? AND c.status = 'active'");
        $stmt->execute([$_SESSION['user_id']]);
        $stats['total_children'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    // Active cases
    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'teacher') {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM missing_cases WHERE status = 'active'");
        $stats['active_cases'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM missing_cases mc 
                              JOIN children c ON mc.child_id = c.id 
                              JOIN parent_child pc ON c.id = pc.child_id 
                              WHERE pc.parent_id = ? AND mc.status = 'active'");
        $stmt->execute([$_SESSION['user_id']]);
        $stats['active_cases'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    // Recent alerts
    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'teacher') {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM alerts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stats['recent_alerts'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM alerts a 
                              JOIN children c ON a.child_id = c.id 
                              JOIN parent_child pc ON c.id = pc.child_id 
                              WHERE pc.parent_id = ? AND a.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute([$_SESSION['user_id']]);
        $stats['recent_alerts'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
    
    // Get recent activities
    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'teacher') {
        $stmt = $pdo->query("SELECT mc.case_number, c.first_name, c.last_name, mc.status, mc.priority, mc.created_at 
                            FROM missing_cases mc 
                            JOIN children c ON mc.child_id = c.id 
                            ORDER BY mc.created_at DESC LIMIT 5");
    } else {
        $stmt = $pdo->prepare("SELECT mc.case_number, c.first_name, c.last_name, mc.status, mc.priority, mc.created_at 
                              FROM missing_cases mc 
                              JOIN children c ON mc.child_id = c.id 
                              JOIN parent_child pc ON c.id = pc.child_id 
                              WHERE pc.parent_id = ? 
                              ORDER BY mc.created_at DESC LIMIT 5");
        $stmt->execute([$_SESSION['user_id']]);
    }
    $recent_cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Failed to load dashboard data.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Child Tracking System</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="main-content">
            <h1>Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p><br>
            
            <!-- Updated statistics cards with modern gradient design -->
            <div class="dashboard-grid">
                <div class="stat-card gradient-blue">
                    <div class="stat-number"><?php echo $stats['total_children']; ?></div>
                    <div class="stat-label">
                        <?php echo $_SESSION['role'] === 'parent' ? 'MY CHILDREN' : 'TOTAL CHILDREN'; ?>
                    </div>
                </div>

                <div class="stat-card gradient-green">
                    <div class="stat-number"><?php echo $stats['active_cases']; ?></div>
                    <div class="stat-label">ACTIVE CASES</div>
                </div>

                <div class="stat-card gradient-orange">
                    <div class="stat-number"><?php echo $stats['recent_alerts']; ?></div>
                    <div class="stat-label">ALERTS (24H)</div>
                </div>

                <?php if ($_SESSION['role'] === 'admin'): ?>
                <div class="stat-card gradient-purple">
                    <div class="stat-number">
                        <?php
                        $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
                        echo $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                        ?>
                    </div>
                    <div class="stat-label">ACTIVE USERS</div>
                </div>
                <?php endif; ?>
            </div>

            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Quick Actions</h2>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-2" style="flex-wrap: wrap;">
                        <?php if ($_SESSION['role'] === 'teacher' || $_SESSION['role'] === 'admin'): ?>
                            <a href="report_missing.php" class="btn btn-danger">Report Missing Child</a>
                            <a href="children.php" class="btn btn-primary">Manage Children</a>
                        <?php endif; ?>
                        
                        <?php if ($_SESSION['role'] === 'parent'): ?>
                            <a href="my_children.php" class="btn btn-primary">View My Children</a>
                        <?php endif; ?>
                        
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <a href="users.php" class="btn btn-secondary">Manage Users</a>
                            <a href="settings.php" class="btn btn-secondary">System Settings</a>
                        <?php endif; ?>
                        
                        <a href="alerts.php" class="btn btn-warning">View Alerts</a>
                    </div>
                </div>
            </div>
            
            <!-- Recent Cases -->
            <?php if (!empty($recent_cases)): ?>
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Recent Cases</h2>
                </div>
                <div class="table-responsive">
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
                                <td><?php echo date('M j, Y g:i A', strtotime($case['created_at'])); ?></td>
                                <td>
                                    <a href="case_details.php?id=<?php echo $case['case_number']; ?>" class="btn btn-sm btn-primary">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
