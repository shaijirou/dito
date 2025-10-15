<?php
require_once 'config/config.php';
requireLogin();



// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete_user':
                $user_id = (int)$_POST['user_id'];
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
                $stmt->execute([$user_id]);
                $success_message = "User deleted successfully.";
                break;
                
            case 'update_role':
                $user_id = (int)$_POST['user_id'];
                $new_role = $_POST['new_role'];
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$new_role, $user_id]);
                $success_message = "User role updated successfully.";
                break;
                
            case 'toggle_status':
                $user_id = (int)$_POST['user_id'];
                $stmt = $pdo->prepare("UPDATE users SET status = NOT is_active WHERE id = ?");
                $stmt->execute([$user_id]);
                $success_message = "User status updated successfully.";
                break;
                
            case 'add_user':
                $username = $_POST['username'];
                $email = $_POST['email'];
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $role = $_POST['role'];
                $full_name = $_POST['full_name'];
                $phone = $_POST['phone'];
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, full_name, phone, status) VALUES (?, ?, ?, ?, ?, ?, 1)");
                    $stmt->execute([$username, $email, $password, $role, $full_name, $phone]);
                    $success_message = "User added successfully.";
                } catch (PDOException $e) {
                    $error_message = "Error adding user: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role_filter) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

if ($status_filter !== '') {
    $where_conditions[] = "status = ?";
    $params[] = (int)$status_filter;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get users with pagination
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users $where_clause");
$count_stmt->execute($params);
$total_users = $count_stmt->fetchColumn();
$total_pages = ceil($total_users / $per_page);

$stmt = $pdo->prepare("SELECT *, DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as formatted_created_at FROM users $where_clause ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get user statistics
$stats_stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role = 'parent' THEN 1 ELSE 0 END) as total_parents,
        SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) as total_teachers,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as total_admins,
        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_30d
    FROM users
");
$stats = $stats_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Child Tracking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/main.css" rel="stylesheet">
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-users"></i> User Management</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus"></i> Add New User
                </button>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row stats-row">
                <div class="col stats-card bg-primary">
                    <div class="stats-content">
                        <div>
                            <div class="stats-number"><?php echo $stats['total_users']; ?></div>
                            <div class="stats-label">Total Users</div>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>

                <div class="col stats-card bg-success">
                    <div class="stats-content">
                        <div>
                            <div class="stats-number"><?php echo $stats['total_parents']; ?></div>
                            <div class="stats-label">Parents</div>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-user-friends"></i>
                        </div>
                    </div>
                </div>
                
                <div class="col stats-card bg-secondary">
                    <div class="stats-content">
                        <div>
                            <div class="stats-number"><?php echo $stats['total_teachers']; ?></div>
                            <div class="stats-label">Total Teachers</div>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                    </div>
                </div>

                <div class="col stats-card bg-warning">
                    <div class="stats-content">
                        <div>
                            <div class="stats-number"><?php echo $stats['total_admins']; ?></div>
                            <div class="stats-label">Admins</div>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                    </div>
                </div>

                <div class="col stats-card bg-info">
                    <div class="stats-content">
                        <div>
                            <div class="stats-number"><?php echo $stats['active_users']; ?></div>
                            <div class="stats-label">Active Users</div>
                        </div>
                        <div class="stats-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                </div>

                
            </div>


            <!-- Search and Filter -->
            <div class="filter-section mb-4">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search Users</label>
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Username, email, or name...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role">
                            <option value="">All Roles</option>
                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="parent" <?php echo $role_filter === 'parent' ? 'selected' : ''; ?>>Parent</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
                            <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <a href="users.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Users Table -->
            <div class="admin-table">
                <div class="card-header">
                    <h5 class="mb-0">Users (<?php echo $total_users; ?> total)</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead class="table-dark ">
                            <tr >
                                <th class="text-center text-white">ID</th>
                                <th class="text-center text-white">Username</th>
                                <th class="text-center text-white">Full Name</th>
                                <th class="text-center text-white">Email</th>
                                <th class="text-center text-white">Phone</th>
                                <th class="text-center text-white">Role</th>
                                <th class="text-center text-white">Status</th>
                                <th class="text-center text-white">Created</th>
                                <th class="text-center text-white">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                <td>
                                    <span class="badge <?php echo $user['role'] === 'admin' ? 'bg-danger' : 'bg-primary'; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $user['status'] ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo $user['status'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo $user['formatted_created_at']; ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($user['role'] !== 'admin' || $_SESSION['user_id'] != $user['id']): ?>
                                            <button class="btn btn-outline-primary btn-sm" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['role']); ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-warning btn-sm" onclick="toggleUserStatus(<?php echo $user['id']; ?>)">
                                                <i class="fas fa-<?php echo $user['status'] ? 'ban' : 'check'; ?>"></i>
                                            </button>
                                            <button class="btn btn-outline-danger btn-sm" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">Current Admin</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($role_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_user">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" class="form-control" name="phone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role" required>
                            <option value="parent">Parent</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
function deleteUser(userId, username) {
    if (confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_user">
            <input type="hidden" name="user_id" value="${userId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function toggleUserStatus(userId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" name="user_id" value="${userId}">
    `;
    document.body.appendChild(form);
    form.submit();
}

function editUser(userId, currentRole) {
    const newRole = prompt(`Change role for user ID ${userId}:`, currentRole);
    if (newRole && (newRole === 'admin' || newRole === 'parent')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="update_role">
            <input type="hidden" name="user_id" value="${userId}">
            <input type="hidden" name="new_role" value="${newRole}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>
