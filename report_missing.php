<?php
require_once 'config/config.php';
requireLogin();

// Only teachers and admin can report missing children
if ($_SESSION['role'] !== 'teacher' && $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// Get children list for the dropdown
try {
    if ($_SESSION['role'] === 'admin') {
        $stmt = $pdo->query("SELECT id, lrn, first_name, last_name, grade FROM children WHERE status = 'active' ORDER BY first_name, last_name");
    } else {
        // Teachers see only their assigned children
        $stmt = $pdo->prepare("SELECT c.id, c.lrn, c.first_name, c.last_name, c.grade 
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $child_id = (int)$_POST['child_id'];
    $priority = sanitizeInput($_POST['priority']);
    $description = sanitizeInput($_POST['description']);
    $last_seen_location = sanitizeInput($_POST['last_seen_location']);
    $last_seen_time = sanitizeInput($_POST['last_seen_time']);
    
    // Validation
    if (empty($child_id) || empty($priority) || empty($description)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            // Check if child exists and user has permission
            if ($_SESSION['role'] === 'admin') {
                $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM children WHERE id = ? AND status = 'active'");
                $stmt->execute([$child_id]);
            } else {
                $stmt = $pdo->prepare("SELECT c.id, c.first_name, c.last_name 
                                      FROM children c 
                                      JOIN teacher_child tc ON c.id = tc.child_id 
                                      WHERE c.id = ? AND tc.teacher_id = ? AND c.status = 'active'");
                $stmt->execute([$child_id, $_SESSION['user_id']]);
            }
            
            $child = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$child) {
                $error = 'Invalid child selection.';
            } else {
                // Check if there's already an active case for this child
                $stmt = $pdo->prepare("SELECT id FROM missing_cases WHERE child_id = ? AND status = 'active'");
                $stmt->execute([$child_id]);
                
                if ($stmt->fetch()) {
                    $error = 'There is already an active missing case for this child.';
                } else {
                    // Generate case number
                    $case_number = generateCaseNumber();
                    
                    // Insert missing case
                    $stmt = $pdo->prepare("INSERT INTO missing_cases (case_number, child_id, reported_by, priority, description, last_seen_location, last_seen_time) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    
                    if ($stmt->execute([$case_number, $child_id, $_SESSION['user_id'], $priority, $description, $last_seen_location, $last_seen_time])) {
                        $case_id = $pdo->lastInsertId();
                        
                        // Create alert
                        $alert_message = "MISSING CHILD ALERT: " . $child['first_name'] . " " . $child['last_name'] . " has been reported missing. Case: " . $case_number;
                        
                        // Get all parents of this child
                        $stmt = $pdo->prepare("SELECT u.id, u.phone FROM users u 
                                              JOIN parent_child pc ON u.id = pc.parent_id 
                                              WHERE pc.child_id = ? AND u.status = 'active'");
                        $stmt->execute([$child_id]);
                        $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Get all teachers and admin
                        $stmt = $pdo->query("SELECT id, phone FROM users WHERE role IN ('teacher', 'admin') AND status = 'active'");
                        $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $all_recipients = array_merge($parents, $staff);
                        $recipient_ids = array_column($all_recipients, 'id');
                        
                        // Insert alert
                        $stmt = $pdo->prepare("INSERT INTO alerts (case_id, child_id, alert_type, message, severity, sent_to) VALUES (?, ?, 'missing', ?, 'critical', ?)");
                        $stmt->execute([$case_id, $child_id, $alert_message, json_encode($recipient_ids)]);
                        
                        // Send SMS alerts
                        foreach ($all_recipients as $recipient) {
                            if (!empty($recipient['phone'])) {
                                sendSMS($recipient['phone'], $alert_message);
                            }
                        }
                        
                        $success = 'Missing child report created successfully. Case Number: ' . $case_number . '. Alerts have been sent to parents and staff.';
                        $_POST = []; // Clear form
                    } else {
                        $error = 'Failed to create missing case. Please try again.';
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again.';
        }
    }
}

// SMS sending function
function sendSMS($phone, $message) {
    global $pdo;
    
    try {
        // Log SMS attempt
        $stmt = $pdo->prepare("INSERT INTO sms_logs (phone_number, message, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$phone, $message]);
        $sms_id = $pdo->lastInsertId();
        
        // Here you would integrate with your SMS provider
        // For now, we'll just mark as sent
        $stmt = $pdo->prepare("UPDATE sms_logs SET status = 'sent', sent_at = NOW() WHERE id = ?");
        $stmt->execute([$sms_id]);
        
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
    <title>Report Missing Child - Child Tracking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css">
   <style>
        body {
            background-color: #f8fafc;
            min-height: 100vh;
        }
        .page-header {
            margin-bottom: 1.5rem;
        }
        .page-title h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #dc3545;
        }
        .card {
            border-radius: 1rem;
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
            border: none;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid #dee2e6;
        }
        .form-section-title {
            font-weight: 600;
            color: #495057;
            margin-bottom: 1rem;
            border-left: 4px solid #dc3545;
            padding-left: .5rem;
        }
        .alert ul {
            margin-bottom: 0;
        }
        .btn-lg i {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center page-header">
            <div class="page-title">
                <h1><i class="fas fa-exclamation-triangle me-2"></i>Report Missing Child</h1>
                <p class="text-muted mb-0">Submit a report to alert parents and staff immediately</p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back
            </a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-file-alt me-2"></i>Missing Child Report Form</h5>
                <small class="text-muted">Please provide complete details to help locate the child quickly</small>
            </div>

            <div class="card-body">
                <form method="POST" action="">
                    <!-- Child Information -->
                    <h6 class="form-section-title mt-3">Child Information</h6>
                    <div class="mb-3">
                        <label for="child_id" class="form-label">Select Child <span class="text-danger">*</span></label>
                        <select id="child_id" name="child_id" class="form-select" required>
                            <option value="">Choose a child...</option>
                            <?php foreach ($children as $child): ?>
                                <option value="<?php echo $child['id']; ?>" 
                                    <?php echo (isset($_POST['child_id']) && $_POST['child_id'] == $child['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($child['first_name'].' '.$child['last_name'].' ('.$child['lrn'].') - '.$child['grade']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Report Details -->
                    <h6 class="form-section-title mt-4">Report Details</h6>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="priority" class="form-label">Priority Level <span class="text-danger">*</span></label>
                            <select id="priority" name="priority" class="form-select" required>
                                <option value="">Select Priority</option>
                                <option value="low" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'low') ? 'selected' : ''; ?>>Low - May have wandered off</option>
                                <option value="medium" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'medium') ? 'selected' : ''; ?>>Medium - Missing for extended time</option>
                                <option value="high" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'high') ? 'selected' : ''; ?>>High - Potential safety concern</option>
                                <option value="critical" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'critical') ? 'selected' : ''; ?>>Critical - Immediate danger suspected</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="last_seen_time" class="form-label">Last Seen Time</label>
                            <input type="datetime-local" id="last_seen_time" name="last_seen_time" 
                                   class="form-control" 
                                   value="<?php echo isset($_POST['last_seen_time']) ? htmlspecialchars($_POST['last_seen_time']) : ''; ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="last_seen_location" class="form-label">Last Seen Location</label>
                        <input type="text" id="last_seen_location" name="last_seen_location" 
                               class="form-control" 
                               placeholder="e.g., School playground, Classroom 3B, Main hallway"
                               value="<?php echo isset($_POST['last_seen_location']) ? htmlspecialchars($_POST['last_seen_location']) : ''; ?>">
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description of Circumstances <span class="text-danger">*</span></label>
                        <textarea id="description" name="description" class="form-control" rows="5" 
                                  required placeholder="Describe what happened, clothing, companions, etc."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>

                    <div class="alert alert-warning d-flex align-items-start">
                        <i class="fas fa-info-circle me-2 mt-1"></i>
                        <div>
                            <strong>Important:</strong> Submitting this form will notify:
                            <ul>
                                <li>Parents/Guardians of the child</li>
                                <li>All teachers and staff</li>
                                <li>School administrators</li>
                            </ul>
                            Double-check all information before submitting.
                        </div>
                    </div>

                    <div class="d-flex gap-3 justify-content-end">
                        <a href="dashboard.php" class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-danger btn-lg">
                            <i class="fas fa-paper-plane"></i> Submit Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>