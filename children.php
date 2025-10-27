<?php
require_once 'config/config.php';
require_once 'api/auto_assign_relationships.php';
requireLogin();

// Check if user has permission to view all children
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'teacher' ) {
    header('Location: my_children.php');
    exit();
}

$error = '';
$success = '';

if (!isset($_SESSION['user_id']) || 
    ($_SESSION['role'] !== 'teacher' && $_SESSION['role'] !== 'admin')) {
    header('Location: ../index.php');
    exit();
}



$teacher_id = $_SESSION['user_id'];


// Step 1: Auto-assign all children before displaying
try {




$stmt = $pdo->prepare("SELECT id FROM children WHERE status = 'active'");
$stmt->execute();
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);


foreach ($children as $child) {
autoAssignRelationships($child['id'], $pdo);
}
} catch (PDOException $e) {
echo '<div class="alert alert-danger">Error during auto-assignment: ' . htmlspecialchars($e->getMessage()) . '</div>';
}


// Step 2: Fetch updated list of children assigned to this teacher
$stmt = $pdo->prepare("
SELECT c.*
FROM children c
INNER JOIN teacher_child tc ON c.id = tc.child_id
WHERE tc.teacher_id = ?
ORDER BY c.first_name, c.last_name ASC
");
$stmt->execute([$teacher_id]);
$assigned_children = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle child deletion
if (isset($_GET['delete']) && $_SESSION['role'] === 'admin') {
    $child_id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("UPDATE children SET status = 'inactive' WHERE id = ?");
        if ($stmt->execute([$child_id])) {
            $success = 'Child record deactivated successfully.';
        }
    } catch (PDOException $e) {
        $error = 'Failed to deactivate child record.';
    }
}

// Get children list
try {
    // Search filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_sql = '';
$params = [];

if (!empty($search)) {
    $search_sql = "AND (c.first_name LIKE ? OR c.last_name LIKE ? OR c.lrn LIKE ? OR c.grade LIKE ?)";
    $like = "%{$search}%";
    $params = [$like, $like, $like, $like];
}

    
    if ($_SESSION['role'] === 'admin') {
        $sql = "SELECT c.*, 
                    (SELECT COUNT(*) FROM parent_child pc WHERE pc.child_id = c.id) as parent_count,
                    (SELECT COUNT(*) FROM missing_cases mc WHERE mc.child_id = c.id AND mc.status = 'active') as active_cases
                FROM children c 
                WHERE c.status = 'active' $search_sql
                ORDER BY c.first_name, c.last_name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        $sql = "SELECT c.*, tc.class_name,
                    (SELECT COUNT(*) FROM parent_child pc WHERE pc.child_id = c.id) as parent_count,
                    (SELECT COUNT(*) FROM missing_cases mc WHERE mc.child_id = c.id AND mc.status = 'active') as active_cases
                FROM children c 
                JOIN teacher_child tc ON c.id = tc.child_id 
                WHERE tc.teacher_id = ? AND c.status = 'active' $search_sql
                ORDER BY c.first_name, c.last_name";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$_SESSION['user_id']], $params));
    }
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Failed to load children data.';
    $children = [];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Children Management - Child Tracking System</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="main-content">
            <div class="d-flex justify-between align-center mb-3">
                <h1>Children Management</h1>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="add_child.php" class="btn btn-primary">Add New Child</a>
                <?php endif; ?>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header d-flex justify-between align-center">
                    <h2 class="card-title">Registered Children</h2>
                    <form method="get" class="d-flex" style="gap:8px;">
                        <input type="text" name="search" class="form-control" placeholder="Search by name, grade, or LRN"
                            value="<?php echo htmlspecialchars($search); ?>" style="max-width:250px;">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <?php if (!empty($search)): ?>
                            <a href="children.php" class="btn btn-secondary">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                
                <?php if (empty($children)): ?>
                    <div class="text-center p-3">
                        <p>No children found.</p>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <a href="add_child.php" class="btn btn-primary">Add First Child</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>LRN</th>
                                    <th>Name</th>
                                    <th>Grade</th>
                                    <th>Age</th>
                                    <th>Parents</th>
                                    <th>Device Status</th>
                                    <th>Active Cases</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($children as $child): ?>
                                <tr>
                                    <td data-label="LRN"><?php echo htmlspecialchars($child['lrn']); ?></td>
                                    <td data-label="Name">
                                        <strong><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></strong>
                                    </td>
                                    <td data-label="Grade"><?php echo htmlspecialchars($child['grade']); ?></td>
                                    <td data-label="Age">
                                        <?php 
                                        $age = date_diff(date_create($child['date_of_birth']), date_create('today'))->y;
                                        echo $age . ' years';
                                        ?>
                                    </td>
                                    <td data-label="Parents">
                                        <span class="badge badge-info"><?php echo $child['parent_count']; ?> parent(s)</span>
                                    </td>
                                    <td data-label="Device Status">
                                        <?php if ($child['lrn']): ?>
                                            <span class="badge badge-success">Connected</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">No Device</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Active Cases">
                                        <?php if ($child['active_cases'] > 0): ?>
                                            <span class="badge badge-danger"><?php echo $child['active_cases']; ?> active</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Actions">
                                        <div class="action-buttons">

                                            <a href="child_profile.php?id=<?php echo $child['id']; ?>" class="btn btn-lg btn-primary btn-icon fs-4" title="View Profile">👁️</a>

                                            <a href="track_child.php?id=<?php echo $child['id']; ?>" class="btn btn-lg btn-success btn-icon fs-4" title="Track Child">📍</a>

                                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                                <a href="edit_child.php?id=<?php echo $child['id']; ?>" class="btn btn-lg btn-warning btn-icon fs-4" title="Edit Child">✏️</a>
                                                <a href="?delete=<?php echo $child['id']; ?>" class="btn btn-lg btn-danger btn-icon fs-4" onclick="return confirm('Are you sure you want to deactivate this child record?')" title="Delete Child">🗑️</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('childSearch').addEventListener('keyup', function() {
            const query = this.value.toLowerCase();
            const rows = document.querySelectorAll('table tbody tr');

            rows.forEach(row => {
                const name = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                const grade = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
                const lrn = row.querySelector('td:nth-child(2)').textContent.toLowerCase();

                if (name.includes(query) || grade.includes(query) || lrn.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
