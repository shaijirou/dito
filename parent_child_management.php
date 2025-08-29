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
        }
    }
}

// Get search parameters
$search_parent = $_GET['search_parent'] ?? '';
$search_child = $_GET['search_child'] ?? '';

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
    
    // Get all parents (for dropdown)
    $stmt = $pdo->prepare("SELECT id, full_name, username, email FROM users WHERE role = 'parent' AND status = 'active' ORDER BY full_name");
    $stmt->execute();
    $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    $parents = [];
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
                <h2><i class="fas fa-users-cog"></i> Parent-Child Management</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignChildModal">
                    <i class="fas fa-plus"></i> Assign Child to Parent
                </button>
            </div>

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

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stats-card bg-primary text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stats-number"><?php echo $stats['total_relationships'] ?? 0; ?></div>
                                <div class="stats-label">Total Relationships</div>
                            </div>
                            <div class="stats-icon">
                                <i class="fas fa-link"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stats-card bg-success text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stats-number"><?php echo $stats['parents_with_children'] ?? 0; ?></div>
                                <div class="stats-label">Parents with Children</div>
                            </div>
                            <div class="stats-icon">
                                <i class="fas fa-user-friends"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stats-card bg-info text-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stats-number"><?php echo $stats['children_with_parents'] ?? 0; ?></div>
                                <div class="stats-label">Children with Parents</div>
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
                                <div class="stats-number"><?php echo ($stats['total_children'] ?? 0) - ($stats['children_with_parents'] ?? 0); ?></div>
                                <div class="stats-label">Unassigned Children</div>
                            </div>
                            <div class="stats-icon">
                                <i class="fas fa-user-times"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="filter-section mb-4">
                <form method="GET" class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label">Search Parent</label>
                        <input type="text" class="form-control" name="search_parent" value="<?php echo htmlspecialchars($search_parent); ?>" placeholder="Parent name, username, or email...">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Search Child</label>
                        <input type="text" class="form-control" name="search_child" value="<?php echo htmlspecialchars($search_child); ?>" placeholder="Child name or student ID...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                            <a href="parent_child_management.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Relationships Table -->
            <div class="admin-table">
                <div class="card-header">
                    <h5 class="mb-0">Parent-Child Relationships (<?php echo count($relationships); ?> total)</h5>
                </div>
                
                <?php if (empty($relationships)): ?>
                    <div class="text-center p-4">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h4>No Relationships Found</h4>
                        <p class="text-muted">No parent-child relationships match your search criteria.</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assignChildModal">
                            <i class="fas fa-plus"></i> Create First Relationship
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead class="table-dark">
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
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <i class="fas fa-user-circle fa-2x text-primary"></i>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($rel['parent_name']); ?></strong><br>
                                                <small class="text-muted">
                                                    @<?php echo htmlspecialchars($rel['parent_username']); ?><br>
                                                    <?php echo htmlspecialchars($rel['parent_email']); ?><br>
                                                    <?php echo htmlspecialchars($rel['parent_phone']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <?php if ($rel['photo']): ?>
                                                    <img src="<?php echo htmlspecialchars($rel['photo']); ?>" alt="Child Photo" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                                <?php else: ?>
                                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: #ddd; display: flex; align-items: center; justify-content: center; font-size: 12px;">
                                                        <i class="fas fa-child"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($rel['first_name'] . ' ' . $rel['last_name']); ?></strong><br>
                                                <small class="text-muted">
                                                    ID: <?php echo htmlspecialchars($rel['lrn']); ?><br>
                                                    Grade: <?php echo htmlspecialchars($rel['grade']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($rel['assigned_date'])); ?><br>
                                        <small class="text-muted"><?php echo date('g:i A', strtotime($rel['assigned_date'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="child_profile.php?id=<?php echo $rel['child_id']; ?>" class="btn btn-outline-info btn-sm" title="View Child Profile">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="track_child.php?id=<?php echo $rel['child_id']; ?>" class="btn btn-outline-success btn-sm" title="Track Child">
                                                <i class="fas fa-map-marker-alt"></i>
                                            </a>
                                            <button class="btn btn-outline-danger btn-sm" onclick="removeRelationship(<?php echo $rel['relationship_id']; ?>, '<?php echo htmlspecialchars($rel['parent_name']); ?>', '<?php echo htmlspecialchars($rel['first_name'] . ' ' . $rel['last_name']); ?>')" title="Remove Relationship">
                                                <i class="fas fa-unlink"></i>
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
        </div>
    </div>
</div>

<!-- Assign Child Modal -->
<div class="modal fade" id="assignChildModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Child to Parent</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="assign_child">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Select Parent *</label>
                                <select class="form-select" name="parent_id" required id="parentSelect">
                                    <option value="">Choose a parent...</option>
                                    <?php foreach ($parents as $parent): ?>
                                        <option value="<?php echo $parent['id']; ?>">
                                            <?php echo htmlspecialchars($parent['full_name'] . ' (' . $parent['username'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div id="parentInfo" class="alert alert-info" style="display: none;">
                                <h6>Parent Details:</h6>
                                <div id="parentDetails"></div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Select Child *</label>
                                <select class="form-select" name="child_id" required id="childSelect">
                                    <option value="">Choose a child...</option>
                                    <?php foreach ($children as $child): ?>
                                        <option value="<?php echo $child['id']; ?>">
                                            <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name'] . ' (ID: ' . $child['lrn'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div id="childInfo" class="alert alert-info" style="display: none;">
                                <h6>Child Details:</h6>
                                <div id="childDetails"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Note:</strong> This will create a parent-child relationship. The parent will be able to view and track this child's location and receive alerts.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-link"></i> Assign Child to Parent
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
// Parent and child data for the modal
const parentData = <?php echo json_encode($parents); ?>;
const childData = <?php echo json_encode($children); ?>;

// Show parent details when selected
document.getElementById('parentSelect').addEventListener('change', function() {
    const parentId = this.value;
    const parentInfo = document.getElementById('parentInfo');
    const parentDetails = document.getElementById('parentDetails');
    
    if (parentId) {
        const parent = parentData.find(p => p.id == parentId);
        if (parent) {
            parentDetails.innerHTML = `
                <strong>Name:</strong> ${parent.full_name}<br>
                <strong>Username:</strong> ${parent.username}<br>
                <strong>Email:</strong> ${parent.email}
            `;
            parentInfo.style.display = 'block';
        }
    } else {
        parentInfo.style.display = 'none';
    }
});

// Show child details when selected
document.getElementById('childSelect').addEventListener('change', function() {
    const childId = this.value;
    const childInfo = document.getElementById('childInfo');
    const childDetails = document.getElementById('childDetails');
    
    if (childId) {
        const child = childData.find(c => c.id == childId);
        if (child) {
            childDetails.innerHTML = `
                <strong>Name:</strong> ${child.first_name} ${child.last_name}<br>
                <strong>Student ID:</strong> ${child.lrn}<br>
                <strong>Grade:</strong> ${child.grade}
            `;
            childInfo.style.display = 'block';
        }
    } else {
        childInfo.style.display = 'none';
    }
});

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

// Auto-refresh every 30 seconds
setInterval(function() {
    // In a real implementation, you might want to check for updates via AJAX
    console.log('Checking for updates...');
}, 30000);
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>
