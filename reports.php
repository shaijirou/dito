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

// Get system statistics
try {
    $stats = [];
    
    // Total counts
    $stmt = $pdo->query("SELECT 
                        (SELECT COUNT(*) FROM users WHERE status = 1) as active_users,
                        (SELECT COUNT(*) FROM children WHERE status = 'active') as active_children,
                        (SELECT COUNT(*) FROM missing_cases WHERE status = 'open') as open_cases,
                        (SELECT COUNT(*) FROM alerts WHERE status = 'unread') as unread_alerts");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Recent activity (last 7 days)
    $stmt = $pdo->query("SELECT 
                        (SELECT COUNT(*) FROM missing_cases WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as recent_cases,
                        (SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as new_users,
                        (SELECT COUNT(*) FROM alerts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as recent_alerts");
    $recent_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Cases by status
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM missing_cases GROUP BY status");
    $case_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Users by role
    $stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users WHERE status = 1 GROUP BY role");
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

<!-- Replaced container-fluid with container and added main-content class for proper centering and margins -->
<div class="container">
    <div class="main-content">
        <h1 style="font-size: 2rem; font-weight: 700; color: #1a1a1a; margin-bottom: 1.5rem;">
            <i class="fas fa-chart-bar" style="margin-right: 0.5rem;"></i>System Reports
        </h1>
        
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

        <!-- Updated stat cards row with proper spacing -->
        <div class="row g-3 mb-5">
            <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                <div class="stat-card stat-card-blue">
                    <div class="stat-header">
                        <div>
                            <div class="stat-count"><?php echo $stats['active_users'] ?? 0; ?></div>
                            <div class="stat-label">Active Users</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                <div class="stat-card stat-card-green">
                    <div class="stat-header">
                        <div>
                            <div class="stat-count"><?php echo $stats['active_children'] ?? 0; ?></div>
                            <div class="stat-label">Active Children</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-child"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                <div class="stat-card stat-card-yellow">
                    <div class="stat-header">
                        <div>
                            <div class="stat-count"><?php echo $stats['open_cases'] ?? 0; ?></div>
                            <div class="stat-label">Open Cases</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6 col-12">
                <div class="stat-card stat-card-red">
                    <div class="stat-header">
                        <div>
                            <div class="stat-count"><?php echo $stats['unread_alerts'] ?? 0; ?></div>
                            <div class="stat-label">Unread Alerts</div>
                        </div>
                        <div class="stat-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts section -->
        <div class="row g-3 mb-5">
            <div class="col-lg-6 col-md-12">
                <div class="card h-100 shadow-sm" style="border: none; border-radius: 8px;">
                    <div class="card-header bg-dark text-white" style="border-radius: 8px 8px 0 0; border: none;">
                        <h5 class="mb-0"><i class="fas fa-pie-chart" style="margin-right: 0.5rem;"></i>Cases by Status</h5>
                    </div>
                    <div class="card-body" style="padding: 2rem;">
                        <canvas id="casesChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-12">
                <div class="card h-100 shadow-sm" style="border: none; border-radius: 8px;">
                    <div class="card-header bg-dark text-white" style="border-radius: 8px 8px 0 0; border: none;">
                        <h5 class="mb-0"><i class="fas fa-bar-chart" style="margin-right: 0.5rem;"></i>Users by Role</h5>
                    </div>
                    <div class="card-body" style="padding: 2rem;">
                        <canvas id="usersChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Cases section -->
        <div class="card shadow-sm" style="border: none; border-radius: 8px;">
            <div class="card-header bg-dark text-white" style="border-radius: 8px 8px 0 0; border: none;">
                <h5 class="mb-0"><i class="fas fa-clock" style="margin-right: 0.5rem;"></i>Recent Cases (Last 7 Days)</h5>
            </div>
            <div class="card-body">
                <?php
                try {
                    $stmt = $pdo->query("SELECT mc.*, c.first_name, c.last_name, c.lrn
                                       FROM missing_cases mc
                                       JOIN children c ON mc.child_id = c.id
                                       WHERE mc.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                       ORDER BY mc.created_at DESC
                                       LIMIT 5");
                    $recent_cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $recent_cases = [];
                }
                ?>
                
                <?php if (empty($recent_cases)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No recent cases in the last 7 days.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_cases as $case): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center py-3" style="border-bottom: 1px solid #e0e0e0;">
                            <div>
                                <strong style="font-size: 1rem; color: #1a1a1a;"><?php echo htmlspecialchars($case['first_name'] . ' ' . $case['last_name']); ?></strong>
                                <br>
                                <small class="text-muted">
                                    ID: <?php echo htmlspecialchars($case['lrn']); ?> | 
                                    <?php echo date('M j, g:i A', strtotime($case['created_at'])); ?>
                                </small>
                            </div>
                            <span class="badge bg-<?php echo $case['status'] === 'open' ? 'warning' : 'success'; ?>" style="font-size: 0.85rem; padding: 0.5rem 0.75rem;">
                                <?php echo ucfirst($case['status']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
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
            borderColor: '#fff',
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 15,
                    font: {
                        size: 12
                    }
                }
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
            backgroundColor: ['#007bff', '#28a745', '#ffc107'],
            borderRadius: 4,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        indexAxis: 'x',
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                },
                grid: {
                    color: '#e0e0e0'
                }
            },
            x: {
                grid: {
                    display: false
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
