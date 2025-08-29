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

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_report'])) {
    $report_type = $_POST['report_type'];
    $date_from = $_POST['date_from'];
    $date_to = $_POST['date_to'];
    $export_format = $_POST['export_format'] ?? 'view';
    
    // Validate dates
    if (empty($date_from) || empty($date_to)) {
        $error = 'Please select both start and end dates.';
    } elseif (strtotime($date_from) > strtotime($date_to)) {
        $error = 'Start date cannot be later than end date.';
    } else {
        // Generate report based on type
        try {
            switch ($report_type) {
                case 'missing_cases':
                    $stmt = $pdo->prepare("SELECT mc.*, c.first_name, c.last_name, c.lrn, c.grade, c.photo,
                                          u.full_name as reported_by_name, u.role as reporter_role
                                          FROM missing_cases mc
                                          JOIN children c ON mc.child_id = c.id
                                          JOIN users u ON mc.reported_by = u.id
                                          WHERE DATE(mc.reported_at) BETWEEN ? AND ?
                                          ORDER BY mc.reported_at DESC");
                    $stmt->execute([$date_from, $date_to]);
                    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $report_title = 'Missing Cases Report';
                    break;
                    
                case 'user_activity':
                    $stmt = $pdo->prepare("SELECT u.id, u.username, u.full_name, u.email, u.role, u.is_active,
                                          u.created_at, u.last_login,
                                          COUNT(DISTINCT mc.id) as cases_reported,
                                          COUNT(DISTINCT pc.child_id) as children_assigned
                                          FROM users u
                                          LEFT JOIN missing_cases mc ON u.id = mc.reported_by AND DATE(mc.reported_at) BETWEEN ? AND ?
                                          LEFT JOIN parent_child pc ON u.id = pc.parent_id
                                          WHERE u.created_at BETWEEN ? AND ?
                                          GROUP BY u.id
                                          ORDER BY u.created_at DESC");
                    $stmt->execute([$date_from, $date_to, $date_from, $date_to]);
                    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $report_title = 'User Activity Report';
                    break;
                    
                case 'location_tracking':
                    $stmt = $pdo->prepare("SELECT cl.*, c.first_name, c.last_name, c.lrn,
                                          u.full_name as parent_name
                                          FROM child_locations cl
                                          JOIN children c ON cl.child_id = c.id
                                          LEFT JOIN parent_child pc ON c.id = pc.child_id
                                          LEFT JOIN users u ON pc.parent_id = u.id
                                          WHERE DATE(cl.timestamp) BETWEEN ? AND ?
                                          ORDER BY cl.timestamp DESC
                                          LIMIT 1000");
                    $stmt->execute([$date_from, $date_to]);
                    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $report_title = 'Location Tracking Report';
                    break;
                    
                case 'alerts':
                    $stmt = $pdo->prepare("SELECT a.*, c.first_name, c.last_name, c.lrn,
                                          u.full_name as user_name, u.role as user_role
                                          FROM alerts a
                                          JOIN children c ON a.child_id = c.id
                                          LEFT JOIN users u ON a.user_id = u.id
                                          WHERE DATE(a.created_at) BETWEEN ? AND ?
                                          ORDER BY a.created_at DESC");
                    $stmt->execute([$date_from, $date_to]);
                    $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $report_title = 'Alerts Report';
                    break;
                    
                default:
                    $error = 'Invalid report type selected.';
                    break;
            }
            
            // Handle export
            if (!$error && $export_format === 'csv') {
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . strtolower(str_replace(' ', '_', $report_title)) . '_' . date('Y-m-d') . '.csv"');
                
                $output = fopen('php://output', 'w');
                
                if (!empty($report_data)) {
                    // Write headers
                    fputcsv($output, array_keys($report_data[0]));
                    
                    // Write data
                    foreach ($report_data as $row) {
                        fputcsv($output, $row);
                    }
                }
                
                fclose($output);
                exit();
            }
            
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get system statistics
try {
    $stats = [];
    
    // Total counts
    $stmt = $pdo->query("SELECT 
                        (SELECT COUNT(*) FROM users WHERE is_active = 1) as active_users,
                        (SELECT COUNT(*) FROM children WHERE status = 'active') as active_children,
                        (SELECT COUNT(*) FROM missing_cases WHERE status = 'open') as open_cases,
                        (SELECT COUNT(*) FROM alerts WHERE status = 'unread') as unread_alerts");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Recent activity (last 7 days)
    $stmt = $pdo->query("SELECT 
                        (SELECT COUNT(*) FROM missing_cases WHERE reported_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as recent_cases,
                        (SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as new_users,
                        (SELECT COUNT(*) FROM alerts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as recent_alerts");
    $recent_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Cases by status
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM missing_cases GROUP BY status");
    $case_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Users by role
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users WHERE is_active = 1 GROUP BY role");
    $user_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Failed to load statistics: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Child Tracking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <h2><i class="fas fa-chart-bar"></i> System Reports</h2>
            
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

             Statistics Dashboard 
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stats-card bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stats-number"><?php echo $stats['active_users'] ?? 0; ?></div>
                                <div class="stats-label">Active Users</div>
                            </div>
                            <div class="stats-icon">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stats-card bg-success text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stats-number"><?php echo $stats['active_children'] ?? 0; ?></div>
                                <div class="stats-label">Active Children</div>
                            </div>
                            <div class="stats-icon">
                                <i class="fas fa-child"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stats-card bg-warning text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stats-number"><?php echo $stats['open_cases'] ?? 0; ?></div>
                                <div class="stats-label">Open Cases</div>
                            </div>
                            <div class="stats-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stats-card bg-danger text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stats-number"><?php echo $stats['unread_alerts'] ?? 0; ?></div>
                                <div class="stats-label">Unread Alerts</div>
                            </div>
                            <div class="stats-icon">
                                <i class="fas fa-bell"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

             Charts Row 
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Cases by Status</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="casesChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Users by Role</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="usersChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>

             Report Generation Form 
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Generate Report</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Report Type *</label>
                                    <select class="form-select" name="report_type" required>
                                        <option value="">Select report type...</option>
                                        <option value="missing_cases">Missing Cases</option>
                                        <option value="user_activity">User Activity</option>
                                        <option value="location_tracking">Location Tracking</option>
                                        <option value="alerts">Alerts</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">From Date *</label>
                                    <input type="date" class="form-control" name="date_from" required value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">To Date *</label>
                                    <input type="date" class="form-control" name="date_to" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label class="form-label">Export Format</label>
                                    <select class="form-select" name="export_format">
                                        <option value="view">View in Browser</option>
                                        <option value="csv">Download CSV</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="generate_report" class="btn btn-primary">
                            <i class="fas fa-chart-line"></i> Generate Report
                        </button>
                    </form>
                </div>
            </div>

             Report Results 
            <?php if (isset($report_data) && !$error): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><?php echo htmlspecialchars($report_title); ?></h5>
                    <small class="text-muted">
                        <?php echo date('M j, Y', strtotime($date_from)); ?> - <?php echo date('M j, Y', strtotime($date_to)); ?>
                        (<?php echo count($report_data); ?> records)
                    </small>
                </div>
                <div class="card-body">
                    <?php if (empty($report_data)): ?>
                        <div class="text-center p-4">
                            <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                            <h4>No Data Found</h4>
                            <p class="text-muted">No records found for the selected date range and report type.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <?php foreach (array_keys($report_data[0]) as $header): ?>
                                            <th><?php echo ucwords(str_replace('_', ' ', $header)); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $key => $value): ?>
                                            <td>
                                                <?php 
                                                if (in_array($key, ['created_at', 'reported_at', 'timestamp', 'last_login'])) {
                                                    echo $value ? date('M j, Y g:i A', strtotime($value)) : 'Never';
                                                } elseif ($key === 'photo' && $value) {
                                                    echo '<img src="' . htmlspecialchars($value) . '" alt="Photo" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover;">';
                                                } elseif ($key === 'is_active') {
                                                    echo $value ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>';
                                                } elseif ($key === 'status') {
                                                    $badge_class = $value === 'open' ? 'bg-warning' : ($value === 'resolved' ? 'bg-success' : 'bg-secondary');
                                                    echo '<span class="badge ' . $badge_class . '">' . ucfirst($value) . '</span>';
                                                } else {
                                                    echo htmlspecialchars($value);
                                                }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

             Quick Reports 
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Cases (Last 7 Days)</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT mc.*, c.first_name, c.last_name, c.lrn
                                                   FROM missing_cases mc
                                                   JOIN children c ON mc.child_id = c.id
                                                   WHERE mc.reported_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                                   ORDER BY mc.reported_at DESC
                                                   LIMIT 5");
                                $recent_cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch (PDOException $e) {
                                $recent_cases = [];
                            }
                            ?>
                            
                            <?php if (empty($recent_cases)): ?>
                                <p class="text-muted">No recent cases in the last 7 days.</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_cases as $case): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($case['first_name'] . ' ' . $case['last_name']); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                ID: <?php echo htmlspecialchars($case['lrn']); ?> | 
                                                <?php echo date('M j, g:i A', strtotime($case['reported_at'])); ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-<?php echo $case['status'] === 'open' ? 'warning' : 'success'; ?>">
                                            <?php echo ucfirst($case['status']); ?>
                                        </span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">System Health</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Database Connection</span>
                                <span class="badge bg-success">Online</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>GPS Tracking</span>
                                <span class="badge bg-success">Active</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Email Notifications</span>
                                <span class="badge bg-success">Working</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>SMS Alerts</span>
                                <span class="badge bg-warning">Limited</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span>System Load</span>
                                <span class="badge bg-info">Normal</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Cases Chart
const casesData = <?php echo json_encode($case_stats); ?>;
const casesCtx = document.getElementById('casesChart').getContext('2d');
new Chart(casesCtx, {
    type: 'doughnut',
    data: {
        labels: casesData.map(item => item.status.charAt(0).toUpperCase() + item.status.slice(1)),
        datasets: [{
            data: casesData.map(item => item.count),
            backgroundColor: ['#ffc107', '#28a745', '#6c757d', '#dc3545'],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Users Chart
const usersData = <?php echo json_encode($user_stats); ?>;
const usersCtx = document.getElementById('usersChart').getContext('2d');
new Chart(usersCtx, {
    type: 'bar',
    data: {
        labels: usersData.map(item => item.role.charAt(0).toUpperCase() + item.role.slice(1)),
        datasets: [{
            label: 'Number of Users',
            data: usersData.map(item => item.count),
            backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545'],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>
