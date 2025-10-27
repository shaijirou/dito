<?php
$alertCount = 0;
try {
    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'teacher') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM alerts WHERE status = 'pending' AND DATE(created_at) = CURDATE()");
    } else {
        // Parents see only alerts for their children from today
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM alerts a 
                              JOIN children c ON a.child_id = c.id 
                              JOIN parent_child pc ON c.id = pc.child_id
                              WHERE pc.parent_id = ? AND a.status = 'pending' AND DATE(a.created_at) = CURDATE()");
        $stmt->execute([$_SESSION['user_id']]);
    }
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $alertCount = $result['count'] ?? 0;
} catch (PDOException $e) {
    $alertCount = 0;
}
?>

<header class="header">
    <div class="container">
        <div class="header-content">
            <!-- Improved logo styling with icon -->
            <div class="logo">
                <a href="dashboard.php" class="logo-link">
                    <span class="logo-text">Child Tracker</span>
                </a>
            </div>
            
            <!-- Enhanced mobile menu toggle with better styling -->
            <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            
            <!-- Improved navigation with better structure -->
            <nav class="nav" id="mainNav">
                <ul>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="users.php">Users</a></li>
                        <li><a href="children.php">Children</a></li>
                        <li><a href="cases.php">Cases</a></li>  
                        <li><a href="reports.php">Reports</a></li>
                        <li><a href="settings.php">Settings</a></li>
                    <?php elseif ($_SESSION['role'] === 'teacher'): ?>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="children.php">Children</a></li>
                        <li><a href="cases.php">Cases</a></li>
                        <li><a href="report_missing.php">Report Missing</a></li>
                    <?php elseif ($_SESSION['role'] === 'parent'): ?>
                        <li><a href="parent_dashboard.php">Dashboard</a></li>
                        <li><a href="my_children.php">My Children</a></li>
                        <li><a href="my_cases.php">My Cases</a></li>
                    <?php endif; ?>
                    <!-- Add notification badge to Alerts menu item -->
                    <li>
                        <a href="alerts.php" class="alerts-link">
                            Alerts
                            <?php if ($alertCount > 0): ?>
                                <span class="notification-badge" title="<?php echo $alertCount; ?> new alert(s)">
                                    <?php echo $alertCount > 99 ? '99+' : $alertCount; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </nav>
            
            <!-- Modernized user info section with better layout -->
            <div class="user-info">
                <div class="user-details">
                    <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <span class="badge badge-info"><?php echo ucfirst($_SESSION['role']); ?></span>
                </div>
                <a href="logout.php" class="btn btn-sm btn-secondary">Logout</a>
            </div>
        </div>
    </div>
</header>

<script>
// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('mobileMenuToggle');
    const nav = document.getElementById('mainNav');
    
    if (toggle && nav) {
        toggle.addEventListener('click', function() {
            nav.classList.toggle('active');
            toggle.classList.toggle('active');
        });
        
        // Close menu when a link is clicked
        const navLinks = nav.querySelectorAll('a');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                nav.classList.remove('active');
                toggle.classList.remove('active');
            });
        });
    }
});
</script>
