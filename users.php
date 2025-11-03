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
                $stmt = $pdo->prepare("UPDATE users SET status = NOT status WHERE id = ?");
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
                $class_name = $_POST['class_name'] ?? null;
                
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, full_name, phone, class_name, status) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                    $stmt->execute([$username, $email, $password, $role, $full_name, $phone, $class_name]);
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
$stats_stmt = $pdo->query("SELECT COUNT(*) as total_users, SUM(CASE WHEN role = 'parent' THEN 1 ELSE 0 END) as total_parents, SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) as total_teachers, SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as total_admins, SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_users, SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_30d FROM users");
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
    <style>
        /* Modern users page styling */
        .users-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .users-header h1 {
            font-size: clamp(1.5rem, 5vw, 2rem);
            color: var(--gray-900);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .add-user-btn {
            white-space: nowrap;
            flex-shrink: 0;
        }

        /* Stats Grid - Modern Card Design */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            border-color: var(--gray-300);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
        }

        .stat-count {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-top: 0.5rem;
            font-weight: 500;
        }

        .stat-icon {
            font-size: 2rem;
            opacity: 0.2;
            flex-shrink: 0;
        }

        .stat-card-blue { border-left: 4px solid #3b82f6; }
        .stat-card-green { border-left: 4px solid #10b981; }
        .stat-card-yellow { border-left: 4px solid #f59e0b; }

        /* Search Card */
        .search-card {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .search-card .card-body {
            padding: 1.5rem;
        }

        .search-card .card-title {
            font-size: 0.95rem;
            color: var(--gray-900);
            font-weight: 600;
        }

        .search-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            align-items: flex-end;
        }

        @media (max-width: 768px) {
            .search-form {
                grid-template-columns: 1fr;
            }
        }

        .search-buttons {
            display: flex;
            gap: 0.5rem;
            width: 100%;
        }

        .search-buttons .btn {
            flex: 1;
        }

        /* Table Container */
        .table-card {
            background: white;
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .table-card .card-header {
            background: linear-gradient(135deg, var(--gray-900) 0%, var(--gray-800) 100%);
            border: none;
            padding: 1.5rem;
        }

        .table-card .card-header h5 {
            font-size: 1rem;
            margin: 0;
            font-weight: 600;
        }

        /* Modern Table Styling */
        .table {
            margin-bottom: 0;
            font-size: 0.95rem;
        }

        .table thead {
            background: var(--gray-100);
        }

        .table thead th {
            background: var(--gray-100);
            border-bottom: 2px solid var(--gray-200);
            color: var(--gray-900);
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 1rem;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--gray-200);
        }

        .table tbody tr {
            transition: background-color 0.2s ease;
        }

        .table tbody tr:hover {
            background-color: var(--gray-50);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            flex-direction: row;
            gap: 0.5rem;
            align-items: center;
        }

        .action-buttons .btn {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .action-buttons .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        /* Badges */
        .badge {
            padding: 0.375rem 0.75rem;
            font-weight: 500;
            font-size: 0.75rem;
            border-radius: 6px;
            text-transform: capitalize;
        }

        /* Pagination */
        .pagination {
            margin-top: 2rem;
        }

        .page-link {
            border-radius: 6px;
            margin: 0 0.25rem;
            border: 1px solid var(--gray-300);
            color: var(--gray-900);
            transition: all 0.2s ease;
        }

        .page-link:hover {
            background-color: var(--gray-100);
            border-color: var(--gray-400);
        }

        .page-item.active .page-link {
            background-color: var(--gray-900);
            border-color: var(--gray-900);
        }

        /* Modal */
        .modal-content {
            border-radius: 12px;
            border: 1px solid var(--gray-200);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--gray-900) 0%, var(--gray-800) 100%);
            color: white;
            border: none;
            padding: 1.5rem;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .form-control, .form-select {
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            padding: 0.625rem 1rem;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--gray-900);
            box-shadow: 0 0 0 3px rgba(17, 24, 39, 0.1);
        }

        /* Mobile Table Display */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .search-form {
                grid-template-columns: 1fr;
            }

            .table {
                font-size: 0.85rem;
            }

            .table thead {
                display: none;
            }

            .table tbody tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid var(--gray-200);
                border-radius: 8px;
                background: white;
                overflow: hidden;
            }

            .table tbody td {
                display: flex;
                justify-content: space-between;
                padding: 0.75rem;
                border: none;
                border-bottom: 1px solid var(--gray-200);
            }

            .table tbody td::before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--gray-900);
                min-width: 80px;
            }

            .table tbody td:last-child {
                border-bottom: none;
            }

            .action-buttons {
                 display: flex;
        flex-direction: row;
        justify-content: center;
        gap: 0.5rem;
        flex-wrap: nowrap;
            }

            .action-buttons .btn {
             width: auto; /* fix: prevents full width stacking */
        flex: 0 0 auto;
            }
        }

        @media (max-width: 480px) {
            .users-header {
                flex-direction: column;
                align-items: stretch;
            }

            .add-user-btn {
                width: 100%;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .table tbody td {
                padding: 0.5rem;
                font-size: 0.8rem;
            }

            .stat-count {
                font-size: 1.5rem;
            }

            .stat-icon {
                font-size: 1.5rem;
            }
        }

        /* Alert Styling */
        .alert {
            border-radius: 8px;
            border: 1px solid;
            padding: 1rem;
        }

        .alert-success {
            background-color: var(--success-50);
            border-color: var(--success-200);
            color: var(--success-800);
        }

        .alert-danger {
            background-color: var(--danger-50);
            border-color: var(--danger-200);
            color: var(--danger-800);
        }
    </style>
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="main-content">
    <div class="container">
        <div class="users-header">
            <h1><i class="fas fa-users"></i> User Management</h1>
            <button class="btn btn-dark add-user-btn" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-plus"></i> Add New User
            </button>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card stat-card-blue">
                <div class="stat-header">
                    <div>
                        <div class="stat-count"><?php echo $stats['total_users']; ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card stat-card-green">
                <div class="stat-header">
                    <div>
                        <div class="stat-count"><?php echo $stats['total_parents']; ?></div>
                        <div class="stat-label">Parents</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-user-friends"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card" style="border-left: 4px solid #6b7280;">
                <div class="stat-header">
                    <div>
                        <div class="stat-count"><?php echo $stats['total_teachers']; ?></div>
                        <div class="stat-label">Teachers</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-chalkboard-user"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card stat-card-yellow">
                <div class="stat-header">
                    <div>
                        <div class="stat-count"><?php echo $stats['total_admins']; ?></div>
                        <div class="stat-label">Admins</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="search-card">
            <div class="card-body">
                <h6 class="card-title"><i class="fas fa-search me-2"></i>Search Users</h6>
                <form method="GET" class="search-form">
                    <div>
                        <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Username, email, or name...">
                    </div>
                    <div>
                        <select class="form-select" name="role">
                            <option value="">All Roles</option>
                            <option value="parent" <?php echo $role_filter === 'parent' ? 'selected' : ''; ?>>Parent</option>
                            <option value="teacher" <?php echo $role_filter === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                        </select>
                    </div>
                    <div>
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
                            <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="search-buttons">
                        <button type="submit" class="btn btn-dark">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="users.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-card">
            <div class="card-header">
                <h5><i class="fas fa-table me-2"></i>Users (<?php echo $total_users; ?> total)</h5>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td data-label="ID"><?php echo $user['id']; ?></td>
                            <td data-label="Username">
                                <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                            </td>
                            <td data-label="Full Name"><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td data-label="Email"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td data-label="Phone"><?php echo htmlspecialchars($user['phone']); ?></td>
                            <td data-label="Role">
                                <span class="badge <?php echo $user['role'] === 'admin' ? 'bg-danger' : ($user['role'] === 'teacher' ? 'bg-primary' : 'bg-secondary'); ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td data-label="Status">
                                <span class="badge <?php echo $user['status'] ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo $user['status'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td data-label="Created"><?php echo $user['formatted_created_at']; ?></td>
                            <td data-label="Actions">
                                <div class="action-buttons">
                                    <?php if ($user['role'] !== 'admin' || $_SESSION['user_id'] != $user['id']): ?>
                                        <button class="btn btn-outline-primary btn-sm" onclick="editUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['role']); ?>')" title="Edit user">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-warning btn-sm" onclick="toggleUserStatus(<?php echo $user['id']; ?>)" title="Toggle status">
                                            <i class="fas fa-<?php echo $user['status'] ? 'ban' : 'check'; ?>"></i>
                                        </button>
                                        <button class="btn btn-outline-danger btn-sm" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" title="Delete user">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">Current Admin</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

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

<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Add New User</h5>
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
                            <option value="teacher">Teacher</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Class Name (for teachers)</label>
                        <input type="text" class="form-control" name="class_name">
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
    if (newRole && (newRole === 'admin' || newRole === 'parent' || newRole === 'teacher')) {
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
