<?php
require_once 'config/config.php';
requireLogin();

$error = '';
$success = '';

// Handle case status updates
if (isset($_POST['update_case'])) {
    $case_id = (int)$_POST['case_id'];
    $new_status = sanitizeInput($_POST['status']);
    $resolution_notes = sanitizeInput($_POST['resolution_notes']);
    
    try {
        if ($new_status === 'resolved') {
            $stmt = $pdo->prepare("UPDATE missing_cases SET status = ?, resolved_at = NOW(), resolved_by = ?, resolution_notes = ? WHERE id = ?");
            $stmt->execute([$new_status, $_SESSION['user_id'], $resolution_notes, $case_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE missing_cases SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $case_id]);
        }
        
        if ($stmt->rowCount() > 0) {
            $success = 'Case status updated successfully.';
            
            // Send notification about case resolution
            if ($new_status === 'resolved') {
                // Get case details
                $stmt = $pdo->prepare("SELECT mc.case_number, c.first_name, c.last_name, c.id as child_id 
                                      FROM missing_cases mc 
                                      JOIN children c ON mc.child_id = c.id 
                                      WHERE mc.id = ?");
                $stmt->execute([$case_id]);
                $case_details = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($case_details) {
                    $alert_message = "CASE RESOLVED: " . $case_details['first_name'] . " " . $case_details['last_name'] . " has been found safe. Case " . $case_details['case_number'] . " is now closed.";
                    
                    // Get all parents of this child
                    $stmt = $pdo->prepare("SELECT u.id, u.phone FROM users u 
                                          JOIN parent_child pc ON u.id = pc.parent_id 
                                          WHERE pc.child_id = ? AND u.status = 'active'");
                    $stmt->execute([$case_details['child_id']]);
                    $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Get all teachers and admin
                    $stmt = $pdo->query("SELECT id, phone FROM users WHERE role IN ('teacher', 'admin') AND status = 'active'");
                    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $all_recipients = array_merge($parents, $staff);
                    $recipient_ids = array_column($all_recipients, 'id');
                    
                    // Insert alert
                    $stmt = $pdo->prepare("INSERT INTO alerts (case_id, child_id, alert_type, message, severity, sent_to) VALUES (?, ?, 'missing', ?, 'info', ?)");
                    $stmt->execute([$case_id, $case_details['child_id'], $alert_message, json_encode($recipient_ids)]);
                    
                    // Send SMS alerts
                    foreach ($all_recipients as $recipient) {
                        if (!empty($recipient['phone'])) {
                            sendSMS($recipient['phone'], $alert_message);
                        }
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $error = 'Failed to update case status.';
    }
}

// Get cases based on user role
try {
    if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'teacher') {
        $stmt = $pdo->query("SELECT mc.*, c.first_name, c.last_name, c.lrn, c.photo,
                            u1.full_name as reported_by_name,
                            u2.full_name as resolved_by_name
                            FROM missing_cases mc 
                            JOIN children c ON mc.child_id = c.id 
                            JOIN users u1 ON mc.reported_by = u1.id
                            LEFT JOIN users u2 ON mc.resolved_by = u2.id
                            ORDER BY mc.created_at DESC");
    } else {
        // Parents see only cases for their children
        $stmt = $pdo->prepare("SELECT mc.*, c.first_name, c.last_name, c.lrn, c.photo,
                              u1.full_name as reported_by_name,
                              u2.full_name as resolved_by_name
                              FROM missing_cases mc 
                              JOIN children c ON mc.child_id = c.id 
                              JOIN parent_child pc ON c.id = pc.child_id
                              JOIN users u1 ON mc.reported_by = u1.id
                              LEFT JOIN users u2 ON mc.resolved_by = u2.id
                              WHERE pc.parent_id = ?
                              ORDER BY mc.created_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
    }
    $cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Failed to load cases data.';
    $cases = [];
}

function sendSMS($phone, $message) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO sms_logs (phone_number, message, status) VALUES (?, ?, 'sent')");
        $stmt->execute([$phone, $message]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cases Management - Child Tracking System</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="main-content">
            <div class="d-flex justify-between align-center mb-3">
                <h1>Cases Management</h1>
                <?php if ($_SESSION['role'] === 'teacher' || $_SESSION['role'] === 'admin'): ?>
                    <a href="report_missing.php" class="btn btn-danger">Report Missing Child</a>
                <?php endif; ?>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <!-- Cases Statistics -->
            <div class="dashboard-grid mb-3">
                 <!-- Active Cases -->
                <div class="stat-card gradient-blue">
                    <div class="stat-icon"><i class="fas fa-exclamation-circle"></i></div>
                    <div class="stat-number">
                        <?php echo count(array_filter($cases, function($case) { return $case['status'] === 'active'; })); ?>
                    </div>
                    <div class="stat-label">Active Cases</div>
                </div>
                
                <!-- Resolved Cases -->
                <div class="stat-card gradient-green">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number">
                        <?php echo count(array_filter($cases, function($case) { return $case['status'] === 'resolved'; })); ?>
                    </div>
                    <div class="stat-label">Resolved Cases</div>
                </div>
                
                <!-- Critical Priority -->
                <div class="stat-card gradient-red">
                    <div class="stat-icon"><i class="fas fa-radiation-alt"></i></div>
                    <div class="stat-number">
                        <?php echo count(array_filter($cases, function($case) { return $case['priority'] === 'critical'; })); ?>
                    </div>
                    <div class="stat-label">Critical Priority</div>
                </div>
            </div>

            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">All Cases (<?php echo count($cases); ?>)</h2>
                </div>
                
                <?php if (empty($cases)): ?>
                    <div class="text-center p-3">
                        <p>No cases found.</p>
                        <?php if ($_SESSION['role'] === 'teacher' || $_SESSION['role'] === 'admin'): ?>
                            <a href="report_missing.php" class="btn btn-primary">Report First Case</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Case #</th>
                                <th>Child</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Reported By</th>
                                <th>Date</th>
                                <th>Duration</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cases as $case): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($case['case_number']); ?></strong>
                                </td>
                                <td>
                                    <div class="d-flex align-center gap-2">
                                        <?php if ($case['photo']): ?>
                                            <img src="<?php echo htmlspecialchars($case['photo']); ?>" alt="Child Photo" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover;">
                                        <?php endif; ?>
                                        <div>
                                            <strong><?php echo htmlspecialchars($case['first_name'] . ' ' . $case['last_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($case['lrn']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $case['status'] === 'active' ? 'danger' : ($case['status'] === 'resolved' ? 'success' : 'secondary'); ?>">
                                        <?php echo ucfirst($case['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $case['priority'] === 'critical' ? 'danger' : ($case['priority'] === 'high' ? 'warning' : 'info'); ?>">
                                        <?php echo ucfirst($case['priority']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($case['reported_by_name']); ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($case['created_at'])); ?></td>
                                <td>
                                    <?php
                                    if ($case['status'] === 'resolved' && $case['resolved_at']) {
                                        $duration = strtotime($case['resolved_at']) - strtotime($case['created_at']);
                                        $hours = floor($duration / 3600);
                                        $minutes = floor(($duration % 3600) / 60);
                                        echo $hours . 'h ' . $minutes . 'm';
                                    } else {
                                        $duration = time() - strtotime($case['created_at']);
                                        $hours = floor($duration / 3600);
                                        $minutes = floor(($duration % 3600) / 60);
                                        echo '<span class="text-danger">' . $hours . 'h ' . $minutes . 'm</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="case_details.php?case=<?php echo urlencode($case['case_number']); ?>" class="btn btn-sm btn-primary">View</a>
                                        <a href="track_child.php?id=<?php echo $case['child_id']; ?>" class="btn btn-sm btn-success">Track</a>
                                        
                                        <?php if (($_SESSION['role'] === 'teacher' || $_SESSION['role'] === 'admin') && $case['status'] === 'active'): ?>
                                            <button type="button" class="btn btn-sm btn-warning" onclick="showResolveModal(<?php echo $case['id']; ?>, '<?php echo htmlspecialchars($case['case_number']); ?>')">Resolve</button>
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
    
    <!-- Resolve Case Modal -->
    <div id="resolveModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 2rem; border-radius: 8px; width: 90%; max-width: 500px;">
            <h3>Resolve Case</h3>
            <form method="POST" action="">
                <input type="hidden" id="modal_case_id" name="case_id">
                <input type="hidden" name="update_case" value="1">
                
                <div class="form-group">
                    <label for="modal_status" class="form-label">Status</label>
                    <select id="modal_status" name="status" class="form-control" required>
                        <option value="resolved">Resolved - Child Found Safe</option>
                        <option value="cancelled">Cancelled - False Alarm</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="modal_resolution_notes" class="form-label">Resolution Notes</label>
                    <textarea id="modal_resolution_notes" name="resolution_notes" class="form-control" rows="3" placeholder="Please describe how the case was resolved, where the child was found, and any other relevant details..." required></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success">Update Case</button>
                    <button type="button" class="btn btn-secondary" onclick="hideResolveModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        function showResolveModal(caseId, caseNumber) {
            document.getElementById('modal_case_id').value = caseId;
            document.getElementById('resolveModal').style.display = 'block';
        }
        
        function hideResolveModal() {
            document.getElementById('resolveModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        document.getElementById('resolveModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideResolveModal();
            }
        });
    </script>
</body>
</html>
