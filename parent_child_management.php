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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'assign_child':
                $parent_id = (int)$_POST['parent_id'];
                $child_id = (int)$_POST['child_id'];
                
                try {
                    // Check if relationship already exists
                    $stmt = $pdo->prepare("SELECT id FROM parent_child WHERE parent_id = ? AND child_id = ?");
                    $stmt->execute([$parent_id, $child_id]);
                    
                    if ($stmt->fetch()) {
                        $error = 'This child is already assigned to this parent.';
                    } else {
                        // Insert new relationship
                        $stmt = $pdo->prepare("INSERT INTO parent_child (parent_id, child_id, created_at) VALUES (?, ?, NOW())");
                        if ($stmt->execute([$parent_id, $child_id])) {
                            $success = 'Child successfully assigned to parent.';
                        } else {
                            $error = 'Failed to assign child to parent.';
                        }
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
                break;
            
            case 'assign_teacher':
                $teacher_id = (int)$_POST['teacher_id'];
                $child_id = (int)$_POST['child_id'];
                $class_name = trim($_POST['class_name']);
                
                try {
                    // Check if relationship already exists
                    $stmt = $pdo->prepare("SELECT id FROM teacher_child WHERE teacher_id = ? AND child_id = ?");
                    $stmt->execute([$teacher_id, $child_id]);
                    
                    if ($stmt->fetch()) {
                        $error = 'This child is already assigned to this teacher.';
                    } else {
                        // Insert new relationship
                        $stmt = $pdo->prepare("INSERT INTO teacher_child (teacher_id, child_id, class_name, assigned_at) VALUES (?, ?, ?, NOW())");
                        if ($stmt->execute([$teacher_id, $child_id, $class_name])) {
                            $success = 'Child successfully assigned to teacher.';
                        } else {
                            $error = 'Failed to assign child to teacher.';
                        }
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
                break;
                
            case 'remove_relationship':
                $relationship_id = (int)$_POST['relationship_id'];
                
                try {
                    $stmt = $pdo->prepare("DELETE FROM parent_child WHERE id = ?");
                    if ($stmt->execute([$relationship_id])) {
                        $success = 'Parent-child relationship removed successfully.';
                    } else {
                        $error = 'Failed to remove relationship.';
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
                break;
            
            case 'remove_teacher_relationship':
                $relationship_id = (int)$_POST['relationship_id'];
                
                try {
                    $stmt = $pdo->prepare("DELETE FROM teacher_child WHERE id = ?");
                    if ($stmt->execute([$relationship_id])) {
                        $success = 'Teacher-child relationship removed successfully.';
                    } else {
                        $error = 'Failed to remove relationship.';
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get search parameters
$search_parent = $_GET['search_parent'] ?? '';
$search_child = $_GET['search_child'] ?? '';
$search_teacher = $_GET['search_teacher'] ?? '';
$view_type = $_GET['view'] ?? 'parent'; // parent or teacher

// Get all parent-child relationships
try {
    $where_conditions = [];
    $params = [];
    
    if ($search_parent) {
        $where_conditions[] = "(p.full_name LIKE ? OR p.username LIKE ? OR p.email LIKE ?)";
        $params[] = "%$search_parent%";
        $params[] = "%$search_parent%";
        $params[] = "%$search_parent%";
    }
    
    if ($search_child) {
        $where_conditions[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.lrn LIKE ?)";
        $params[] = "%$search_child%";
        $params[] = "%$search_child%";
        $params[] = "%$search_child%";
    }
    
    $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    $stmt = $pdo->prepare("SELECT pc.id as relationship_id, 
                          p.id as parent_id, p.full_name as parent_name, p.username as parent_username, p.email as parent_email, p.phone as parent_phone,
                          c.id as child_id, c.first_name, c.last_name, c.lrn, c.grade, c.photo,
                          pc.created_at as assigned_date
                          FROM parent_child pc
                          JOIN users p ON pc.parent_id = p.id
                          JOIN children c ON pc.child_id = c.id
                          $where_clause
                          ORDER BY p.full_name, c.first_name, c.last_name");
    $stmt->execute($params);
    $relationships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $where_conditions_teacher = [];
    $params_teacher = [];
    
    if ($search_teacher) {
        $where_conditions_teacher[] = "(t.full_name LIKE ? OR t.username LIKE ? OR t.email LIKE ?)";
        $params_teacher[] = "%$search_teacher%";
        $params_teacher[] = "%$search_teacher%";
        $params_teacher[] = "%$search_teacher%";
    }
    
    if ($search_child) {
        $where_conditions_teacher[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.lrn LIKE ?)";
        $params_teacher[] = "%$search_child%";
        $params_teacher[] = "%$search_child%";
        $params_teacher[] = "%$search_child%";
    }
    
    $where_clause_teacher = $where_conditions_teacher ? 'WHERE ' . implode(' AND ', $where_conditions_teacher) : '';
    
    $stmt = $pdo->prepare("SELECT tc.id as relationship_id, 
                          t.id as teacher_id, t.full_name as teacher_name, t.username as teacher_username, t.email as teacher_email, t.phone as teacher_phone,
                          c.id as child_id, c.first_name, c.last_name, c.lrn, c.grade, c.photo,
                          tc.class_name, tc.assigned_at as assigned_date
                          FROM teacher_child tc
                          JOIN users t ON tc.teacher_id = t.id
                          JOIN children c ON tc.child_id = c.id
                          $where_clause_teacher
                          ORDER BY t.full_name, c.first_name, c.last_name");
    $stmt->execute($params_teacher);
    $teacher_relationships = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all parents (for dropdown)
    $stmt = $pdo->prepare("SELECT id, full_name, username, email FROM users WHERE role = 'parent' AND status = 'active' ORDER BY full_name");
    $stmt->execute();
    $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT id, full_name, username, email FROM users WHERE role = 'teacher' AND status = 'active' ORDER BY full_name");
    $stmt->execute();
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all children (for dropdown)
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, lrn, grade FROM children WHERE status = 'active' ORDER BY first_name, last_name");
    $stmt->execute();
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stmt = $pdo->query("SELECT 
                        COUNT(DISTINCT pc.parent_id) as parents_with_children,
                        COUNT(DISTINCT pc.child_id) as children_with_parents,
                        COUNT(*) as total_relationships,
                        (SELECT COUNT(*) FROM users WHERE role = 'parent' AND status = 'active') as total_parents,
                        (SELECT COUNT(*) FROM children WHERE status = 'active') as total_children
                        FROM parent_child pc");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Failed to load data: ' . $e->getMessage();
    $relationships = [];
    $teacher_relationships = [];
    $parents = [];
    $teachers = [];
    $children = [];
    $stats = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent-Child Management - Child Tracking System</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="main-content">
        <!-- Updated header with modern design -->
        <div class="d-flex justify-between align-center mb-4" style="flex-wrap: wrap; gap: 1rem;">
            <h1 style="margin: 0;">Parent/Teacher-Child Management</h1>
            <div class="d-flex gap-2" style="flex-wrap: wrap;">
                <button class="btn btn-primary" onclick="document.getElementById('assignChildModal').style.display='block'">
                    + Assign Child to Parent
                </button>
                <button class="btn btn-success" onclick="document.getElementById('assignTeacherModal').style.display='block'">
                    + Assign Child to Teacher
                </button>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Updated statistics cards with modern gradient design matching dashboard -->
        <div class="dashboard-grid">
            <div class="stat-card" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                <div class="stat-number"><?php echo $stats['total_relationships'] ?? 0; ?></div>
                <div class="stat-label">TOTAL RELATIONSHIPS</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #10b981, #059669);">
                <div class="stat-number"><?php echo $stats['parents_with_children'] ?? 0; ?></div>
                <div class="stat-label">PARENTS WITH CHILDREN</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #06b6d4, #0891b2);">
                <div class="stat-number"><?php echo $stats['children_with_parents'] ?? 0; ?></div>
                <div class="stat-label">CHILDREN WITH PARENTS</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                <div class="stat-number"><?php echo ($stats['total_children'] ?? 0) - ($stats['children_with_parents'] ?? 0); ?></div>
                <div class="stat-label">UNASSIGNED CHILDREN</div>
            </div>
        </div>

        <!-- Added view toggle tabs -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-body" style="padding: 1rem;">
                <div class="d-flex gap-2">
                    <a href="?view=parent" class="btn <?php echo $view_type === 'parent' ? 'btn-primary' : 'btn-secondary'; ?>">
                        Parent-Child Relationships
                    </a>
                    <a href="?view=teacher" class="btn <?php echo $view_type === 'teacher' ? 'btn-primary' : 'btn-secondary'; ?>">
                        Teacher-Child Assignments
                    </a>
                </div>
            </div>
        </div>

        <?php if ($view_type === 'parent'): ?>
        <!-- Search and Filter for Parents -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-body">
                <form method="GET" class="d-flex gap-3" style="flex-wrap: wrap; align-items: flex-end;">
                    <input type="hidden" name="view" value="parent">
                    <div style="flex: 1; min-width: 200px;">
                        <label class="form-label">Search Parent</label>
                        <input type="text" class="form-control" name="search_parent" value="<?php echo htmlspecialchars($search_parent); ?>" placeholder="Parent name, username, or email...">
                    </div>
                    <div style="flex: 1; min-width: 200px;">
                        <label class="form-label">Search Child</label>
                        <input type="text" class="form-control" name="search_child" value="<?php echo htmlspecialchars($search_child); ?>" placeholder="Child name or student ID...">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="?view=parent" class="btn btn-secondary">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Relationships Table -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Parent-Child Relationships (<?php echo count($relationships); ?> total)</h2>
            </div>
            
            <?php if (empty($relationships)): ?>
                <div class="card-body text-center" style="padding: 3rem;">
                    <h4>No Relationships Found</h4>
                    <p style="color: var(--gray-500);">No parent-child relationships match your search criteria.</p>
                    <button class="btn btn-primary" onclick="document.getElementById('assignChildModal').style.display='block'">
                        Create First Relationship
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Parent Information</th>
                                <th>Child Information</th>
                                <th>Assigned Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($relationships as $rel): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-center gap-3">
                                        <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary-100, #e0e9ff); display: flex; align-items: center; justify-content: center; color: var(--primary-700);">
                                            <strong><?php echo strtoupper(substr($rel['parent_name'], 0, 1)); ?></strong>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($rel['parent_name']); ?></strong><br>
                                            <small style="color: var(--gray-500);">
                                                @<?php echo htmlspecialchars($rel['parent_username']); ?><br>
                                                <?php echo htmlspecialchars($rel['parent_email']); ?><br>
                                                <?php echo htmlspecialchars($rel['parent_phone']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-center gap-3">
                                        <div>
                                            <?php if ($rel['photo']): ?>
                                                <img src="<?php echo htmlspecialchars($rel['photo']); ?>" alt="Child Photo" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                            <?php else: ?>
                                                <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--gray-200); display: flex; align-items: center; justify-content: center;">
                                                    <strong><?php echo strtoupper(substr($rel['first_name'], 0, 1)); ?></strong>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($rel['first_name'] . ' ' . $rel['last_name']); ?></strong><br>
                                            <small style="color: var(--gray-500);">
                                                ID: <?php echo htmlspecialchars($rel['lrn']); ?><br>
                                                Grade: <?php echo htmlspecialchars($rel['grade']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($rel['assigned_date'])); ?><br>
                                    <small style="color: var(--gray-500);"><?php echo date('g:i A', strtotime($rel['assigned_date'])); ?></small>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="child_profile.php?id=<?php echo $rel['child_id']; ?>" class="btn btn-sm btn-primary" title="View Child Profile">
                                            View
                                        </a>
                                        <a href="track_child.php?id=<?php echo $rel['child_id']; ?>" class="btn btn-sm btn-success" title="Track Child">
                                            Track
                                        </a>
                                        <button class="btn btn-sm btn-danger" onclick="removeRelationship(<?php echo $rel['relationship_id']; ?>, '<?php echo htmlspecialchars($rel['parent_name']); ?>', '<?php echo htmlspecialchars($rel['first_name'] . ' ' . $rel['last_name']); ?>')" title="Remove Relationship">
                                            Remove
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <?php else: ?>
        <!-- Teacher-Child view -->
        <!-- Search and Filter for Teachers -->
        <div class="card" style="margin-bottom: 1.5rem;">
            <div class="card-body">
                <form method="GET" class="d-flex gap-3" style="flex-wrap: wrap; align-items: flex-end;">
                    <input type="hidden" name="view" value="teacher">
                    <div style="flex: 1; min-width: 200px;">
                        <label class="form-label">Search Teacher</label>
                        <input type="text" class="form-control" name="search_teacher" value="<?php echo htmlspecialchars($search_teacher); ?>" placeholder="Teacher name, username, or email...">
                    </div>
                    <div style="flex: 1; min-width: 200px;">
                        <label class="form-label">Search Child</label>
                        <input type="text" class="form-control" name="search_child" value="<?php echo htmlspecialchars($search_child); ?>" placeholder="Child name or student ID...">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="?view=teacher" class="btn btn-secondary">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Teacher Relationships Table -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Teacher-Child Assignments (<?php echo count($teacher_relationships); ?> total)</h2>
            </div>
            
            <?php if (empty($teacher_relationships)): ?>
                <div class="card-body text-center" style="padding: 3rem;">
                    <h4>No Assignments Found</h4>
                    <p style="color: var(--gray-500);">No teacher-child assignments match your search criteria.</p>
                    <button class="btn btn-success" onclick="document.getElementById('assignTeacherModal').style.display='block'">
                        Create First Assignment
                    </button>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Teacher Information</th>
                                <th>Child Information</th>
                                <th>Class</th>
                                <th>Assigned Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teacher_relationships as $rel): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-center gap-3">
                                        <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--success-100, #d1fae5); display: flex; align-items: center; justify-content: center; color: var(--success-700, #047857);">
                                            <strong><?php echo strtoupper(substr($rel['teacher_name'], 0, 1)); ?></strong>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($rel['teacher_name']); ?></strong><br>
                                            <small style="color: var(--gray-500);">
                                                @<?php echo htmlspecialchars($rel['teacher_username']); ?><br>
                                                <?php echo htmlspecialchars($rel['teacher_email']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-center gap-3">
                                        <div>
                                            <?php if ($rel['photo']): ?>
                                                <img src="<?php echo htmlspecialchars($rel['photo']); ?>" alt="Child Photo" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                            <?php else: ?>
                                                <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--gray-200); display: flex; align-items: center; justify-content: center;">
                                                    <strong><?php echo strtoupper(substr($rel['first_name'], 0, 1)); ?></strong>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($rel['first_name'] . ' ' . $rel['last_name']); ?></strong><br>
                                            <small style="color: var(--gray-500);">
                                                ID: <?php echo htmlspecialchars($rel['lrn']); ?><br>
                                                Grade: <?php echo htmlspecialchars($rel['grade']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?php echo htmlspecialchars($rel['class_name'] ?: 'N/A'); ?></span>
                                </td>
                                <td>
                                    <?php echo date('M j, Y', strtotime($rel['assigned_date'])); ?><br>
                                    <small style="color: var(--gray-500);"><?php echo date('g:i A', strtotime($rel['assigned_date'])); ?></small>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <a href="child_profile.php?id=<?php echo $rel['child_id']; ?>" class="btn btn-sm btn-primary" title="View Child Profile">
                                            View
                                        </a>
                                        <button class="btn btn-sm btn-danger" onclick="removeTeacherRelationship(<?php echo $rel['relationship_id']; ?>, '<?php echo htmlspecialchars($rel['teacher_name']); ?>', '<?php echo htmlspecialchars($rel['first_name'] . ' ' . $rel['last_name']); ?>')" title="Remove Assignment">
                                            Remove
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Assign Child Modal -->
<div id="assignChildModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Assign Child to Parent</h2>
            <span class="close" onclick="document.getElementById('assignChildModal').style.display='none'">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="assign_child">
                
                <div class="form-group">
                    <label class="form-label">Select Parent *</label>
                    <select class="form-control" name="parent_id" required>
                        <option value="">Choose a parent...</option>
                        <?php foreach ($parents as $parent): ?>
                            <option value="<?php echo $parent['id']; ?>">
                                <?php echo htmlspecialchars($parent['full_name'] . ' (' . $parent['username'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Select Child *</label>
                    <select class="form-control" name="child_id" required>
                        <option value="">Choose a child...</option>
                        <?php foreach ($children as $child): ?>
                            <option value="<?php echo $child['id']; ?>">
                                <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name'] . ' (ID: ' . $child['lrn'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="alert alert-warning">
                    <strong>Note:</strong> This will create a parent-child relationship. The parent will be able to view and track this child's location and receive alerts.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('assignChildModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-primary">Assign Child to Parent</button>
            </div>
        </form>
    </div>
</div>

<!-- Added Assign Teacher Modal -->
<div id="assignTeacherModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Assign Child to Teacher</h2>
            <span class="close" onclick="document.getElementById('assignTeacherModal').style.display='none'">&times;</span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="assign_teacher">
                
                <div class="form-group">
                    <label class="form-label">Select Teacher *</label>
                    <select class="form-control" name="teacher_id" required>
                        <option value="">Choose a teacher...</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>">
                                <?php echo htmlspecialchars($teacher['full_name'] . ' (' . $teacher['username'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Select Child *</label>
                    <select class="form-control" name="child_id" required>
                        <option value="">Choose a child...</option>
                        <?php foreach ($children as $child): ?>
                            <option value="<?php echo $child['id']; ?>">
                                <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name'] . ' (ID: ' . $child['lrn'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Class Name</label>
                    <input type="text" class="form-control" name="class_name" placeholder="e.g., Math 101, Science A">
                </div>
                
                <div class="alert alert-info">
                    <strong>Note:</strong> This will assign the child to the teacher's class. The teacher will be able to monitor and track this child.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('assignTeacherModal').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-success">Assign Child to Teacher</button>
            </div>
        </form>
    </div>
</div>

<script>
function removeRelationship(relationshipId, parentName, childName) {
    if (confirm(`Are you sure you want to remove the relationship between "${parentName}" and "${childName}"?\n\nThis will prevent the parent from accessing the child's information and tracking data.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="remove_relationship">
            <input type="hidden" name="relationship_id" value="${relationshipId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function removeTeacherRelationship(relationshipId, teacherName, childName) {
    if (confirm(`Are you sure you want to remove the assignment between "${teacherName}" and "${childName}"?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="remove_teacher_relationship">
            <input type="hidden" name="relationship_id" value="${relationshipId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const assignChildModal = document.getElementById('assignChildModal');
    const assignTeacherModal = document.getElementById('assignTeacherModal');
    if (event.target == assignChildModal) {
        assignChildModal.style.display = 'none';
    }
    if (event.target == assignTeacherModal) {
        assignTeacherModal.style.display = 'none';
    }
}
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>
