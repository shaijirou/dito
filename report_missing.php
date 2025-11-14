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
    $reported_at = date('Y-m-d H:i:s');
    
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
                    $stmt = $pdo->prepare("INSERT INTO missing_cases (case_number, child_id, reported_by, priority, description, last_seen_location, last_seen_time, created_at) VALUES (?, ?, ?, ?, ?, ?, ?,?)");
                    
                    if ($stmt->execute([$case_number, $child_id, $_SESSION['user_id'], $priority, $description, $last_seen_location, $last_seen_time, $reported_at])) {
                        $case_id = $pdo->lastInsertId();
                        
                        // Create alert message
                        $alert_message = "MISSING CHILD ALERT: " . $child['first_name'] . " " . $child['last_name'] . " has been reported missing. Case: " . $case_number . ". Please Wait for further instructions". date('M d, Y h:i A') ;
                        
                        $stmt = $pdo->prepare("SELECT u.id, u.phone, u.full_name FROM users u 
                                              JOIN parent_child pc ON u.id = pc.parent_id 
                                              WHERE pc.child_id = ? AND u.status = 'active'");
                        $stmt->execute([$child_id]);
                        $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $stmt = $pdo->prepare("SELECT u.id, u.phone, u.full_name FROM users u 
                                              JOIN teacher_child tc ON u.id = tc.teacher_id 
                                              WHERE tc.child_id = ? AND u.status = 'active'");
                        $stmt->execute([$child_id]);
                        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Get all admins
                        $stmt = $pdo->query("SELECT id, phone, full_name FROM users WHERE role = 'admin' AND status = 'active'");
                        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Combine all recipients
                        $all_recipients = array_merge($parents, $teachers, $admins);
                        $recipient_ids = array_column($all_recipients, 'id');
                        
                        // Insert alert
                        $stmt = $pdo->prepare("INSERT INTO alerts (case_id, child_id, alert_type, message, severity, sent_to) VALUES (?, ?, 'missing', ?, 'critical', ?)");
                        $stmt->execute([$case_id, $child_id, $alert_message, json_encode($recipient_ids)]);
                        
                        $sms_sent_count = 0;
                        foreach ($all_recipients as $recipient) {
                            if (!empty($recipient['phone'])) {
                                if (sendSMSViaSemaphore($recipient['phone'], $alert_message)) {
                                    $sms_sent_count++;
                                }
                            }
                        }
                        
                        $success = 'Missing child report created successfully. Case Number: ' . $case_number . '. SMS alerts sent to ' . $sms_sent_count . ' parents and teachers.';
                        $_POST = []; // Clear form
                    } else {
                        $error = 'Failed to create missing case. Please try again.';
                    }
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error. Please try again.';
            error_log("[MISSING_CASE] Error: " . $e->getMessage());
        }
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
       .suggestions-list div {
    padding: 8px 12px;
    cursor: pointer;
}
.suggestions-list div:hover, .suggestions-list .highlighted {
    background-color: #e9ecef;
}

    </style>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const children = [
        <?php foreach ($children as $child): ?>
        {
            id: '<?php echo $child['id']; ?>', 
            name: '<?php echo htmlspecialchars($child['first_name'].' '.$child['last_name'].' ('.$child['lrn'].') - '.$child['grade']); ?>'
        },
        <?php endforeach; ?>
    ];

    const input = document.getElementById('child_search');
    const suggestionsContainer = document.getElementById('child_suggestions');
    const hiddenId = document.getElementById('child_id');

    let currentFocus = -1;

    input.addEventListener('input', function() {
        const val = this.value.toLowerCase();
        suggestionsContainer.innerHTML = '';
        currentFocus = -1;

        if (!val) {
            suggestionsContainer.style.display = 'none';
            hiddenId.value = '';
            return;
        }

        const matches = children.filter(c => c.name.toLowerCase().includes(val));
        if (matches.length === 0) {
            suggestionsContainer.style.display = 'none';
            hiddenId.value = '';
            return;
        }

        matches.forEach(child => {
            const div = document.createElement('div');
            div.textContent = child.name;
            div.dataset.id = child.id;
            div.addEventListener('click', function() {
                input.value = this.textContent;
                hiddenId.value = this.dataset.id;
                suggestionsContainer.innerHTML = '';
                suggestionsContainer.style.display = 'none';
            });
            suggestionsContainer.appendChild(div);
        });

        suggestionsContainer.style.display = 'block';
    });

    // Keyboard navigation in suggestions (up/down/enter)
    input.addEventListener('keydown', function(e) {
        let items = suggestionsContainer.getElementsByTagName('div');
        if (items.length === 0) return;

        if (e.key === 'ArrowDown') {
            currentFocus++;
            if (currentFocus >= items.length) currentFocus = 0;
            addActive(items);
            e.preventDefault();
        } else if (e.key === 'ArrowUp') {
            currentFocus--;
            if (currentFocus < 0) currentFocus = items.length -1;
            addActive(items);
            e.preventDefault();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (currentFocus > -1) {
                items[currentFocus].click();
            }
        }
    });

    function addActive(items) {
        for (let i=0; i<items.length; i++) {
            items[i].classList.remove('highlighted');
        }
        if (currentFocus >= 0 && currentFocus < items.length) {
            items[currentFocus].classList.add('highlighted');
        }
    }

    // Close suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target !== input) {
            suggestionsContainer.style.display = 'none';
        }
    });
});
</script>

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
                <small  style="color: white;">Please provide complete details to help locate the child quickly</small>
            </div>

            <div class="card-body">
                <form method="POST" action="">
                    <!-- Child Information -->
                    <h6 class="form-section-title mt-3">Child Information</h6>
                    <div class="mb-3">
                        <label for="child_search" class="form-label">Select Child <span class="text-danger">*</span></label>
                        <input 
                            type="text" 
                            id="child_search" 
                            name="child_search" 
                            class="form-control" 
                            placeholder="Search for a child..." 
                            autocomplete="off"
                            required
                        />

                        <input type="hidden" id="child_id" name="child_id" value="<?php echo isset($_POST['child_id']) ? htmlspecialchars($_POST['child_id']) : ''; ?>" />

                        <div id="child_suggestions" class="suggestions-list" style="border: 1px solid #ced4da; max-height: 150px; overflow-y: auto; display: none; position: absolute; background: white; width: 100%; z-index: 1000;"></div>

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
                                <li>Teachers assigned to the child</li>
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
