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
    if ($_SESSION['role'] === 'admin') {
        $stmt = $pdo->query("SELECT c.*, 
                            (SELECT COUNT(*) FROM parent_child pc WHERE pc.child_id = c.id) as parent_count,
                            (SELECT COUNT(*) FROM missing_cases mc WHERE mc.child_id = c.id AND mc.status = 'active') as active_cases
                            FROM children c 
                            WHERE c.status = 'active' 
                            ORDER BY c.first_name, c.last_name");
    } else {
        // Teachers see children assigned to them
        $stmt = $pdo->prepare("SELECT c.*, tc.class_name,
                              (SELECT COUNT(*) FROM parent_child pc WHERE pc.child_id = c.id) as parent_count,
                              (SELECT COUNT(*) FROM missing_cases mc WHERE mc.child_id = c.id AND mc.status = 'active') as active_cases
                              FROM children c 
                              JOIN teacher_child tc ON c.id = tc.child_id 
                              WHERE tc.teacher_id = ? AND c.status = 'active' 
                              ORDER BY c.first_name, c.last_name");
        $stmt->execute([$_SESSION['user_id']]);
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
                <div class="card-header">
                    <h2 class="card-title">Registered Children (<?php echo count($children); ?>)</h2>
                </div>
                
                <?php if (empty($children)): ?>
                    <div class="text-center p-3">
                        <p>No children found.</p>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <a href="add_child.php" class="btn btn-primary">Add First Child</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Photo</th>
                                <th>Student ID</th>
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
                                <td>
                                    <?php if ($child['photo']): ?>
                                        <img src="<?php echo htmlspecialchars($child['photo']); ?>" alt="Child Photo" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                    <?php else: ?>
                                        <div style="width: 40px; height: 40px; border-radius: 50%; background: #ddd; display: flex; align-items: center; justify-content: center; font-size: 12px;">
                                            No Photo
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($child['lrn']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($child['grade']); ?></td>
                                <td>
                                    <?php 
                                    $age = date_diff(date_create($child['date_of_birth']), date_create('today'))->y;
                                    echo $age . ' years';
                                    ?>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?php echo $child['parent_count']; ?> parent(s)</span>
                                </td>
                                <td>
                                    <?php if ($child['lrn']): ?>
                                        <span class="badge badge-success">Connected</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">No Device</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($child['active_cases'] > 0): ?>
                                        <span class="badge badge-danger"><?php echo $child['active_cases']; ?> active</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="child_profile.php?id=<?php echo $child['id']; ?>" class="btn btn-sm btn-primary">View</a>
                                        <a href="track_child.php?id=<?php echo $child['id']; ?>" class="btn btn-sm btn-success">Track</a>
                                        <?php if ($_SESSION['role'] === 'admin'): ?>
                                            <a href="edit_child.php?id=<?php echo $child['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                            <a href="?delete=<?php echo $child['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to deactivate this child record?')">Delete</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
