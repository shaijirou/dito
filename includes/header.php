<header class="header">
    <div class="container">
        <div class="header-content">
            <div class="logo">
                <a href="dashboard.php">
                    Child Tracking System
                </a>
            </div>
            
            <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
                <span></span>
                <span></span>
                <span></span>
            </button>
            
            <nav class="nav" id="mainNav">
                <ul>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="users.php">Users</a></li>
                        <li><a href="children.php">Children</a></li>
                        <!-- Removed link to parent_child_management.php - now automated -->
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
                    <li><a href="alerts.php">Alerts</a></li>
                </ul>
            </nav>
            
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <span class="badge badge-info"><?php echo ucfirst($_SESSION['role']); ?></span>
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
    }
});
</script>
