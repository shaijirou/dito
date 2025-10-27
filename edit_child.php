<?php
require_once 'config/config.php';
require_once 'includes/header.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$child_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// Get child data
if ($child_id) {
    $stmt = $pdo->prepare("SELECT * FROM children WHERE id = ?");
    $stmt->execute([$child_id]);
    $child = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$child) {
        header('Location: children.php');
        exit();
    }
} else {
    // New child
    $child = [
        'id' => 0,
        'first_name' => '',
        'last_name' => '',
        'date_of_birth' => '',
        // Using 'lrn' as per the updates
        'lrn' => '',
        'grade' => '',
        'emergency_contact' => '',
        'status' => 'active',
        'medical_info' => '',
       
        // Added 'gender' field
        'gender' => '',
        // Added 'age' field (though not used in the form, it was in the initial $child array in updates)
        'age' => ''
    ];
}

// Get all parents for dropdown
$stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE role = 'parent' ORDER BY full_name");
$stmt->execute();
$parents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Validate required fields
        // Removed 'student_id' from required fields as 'lrn' is now used and is not strictly required
        $required_fields = ['first_name', 'last_name', 'date_of_birth'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields.");
            }
        }
        
        if ($child_id) {
            // Update using only children table columns, including 'lrn', 'gender'
            
            $stmt = $pdo->prepare("UPDATE children SET 
                                  first_name = ?, last_name = ?, date_of_birth = ?, 
                                  lrn = ?, grade = ?, emergency_contact = ?, 
                                  status = ?, medical_info = ?, gender = ?, updated_at = NOW()
                                  WHERE id = ?");
            $stmt->execute([
                $_POST['first_name'], 
                $_POST['last_name'], 
                $_POST['date_of_birth'],
                $_POST['lrn'] ?? '', // Use empty string if not set
                $_POST['grade'] ?? '', // Use empty string if not set
                $_POST['emergency_contact'] ?? '', // Use empty string if not set
                $_POST['status'], 
                $_POST['medical_info'] ?? '', // Use empty string if not set
                $_POST['gender'] ?? '', // Use empty string if not set
                $child_id
            ]);
        } else {
            // Insert using only children table columns, including 'lrn', 'gender'
            // Removed 'student_id', 'device_id',  related logic from the main insert statement
            $stmt = $pdo->prepare("INSERT INTO children 
                                  (first_name, last_name, date_of_birth, lrn, grade, 
                                   emergency_contact, status, medical_info, gender,
                                   created_at, updated_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([
                $_POST['first_name'], 
                $_POST['last_name'], 
                $_POST['date_of_birth'],
                $_POST['lrn'] ?? '', // Use empty string if not set
                $_POST['grade'] ?? '', // Use empty string if not set
                $_POST['emergency_contact'] ?? '', // Use empty string if not set
                $_POST['status'], 
                $_POST['medical_info'] ?? '', // Use empty string if not set
              
                $_POST['gender'] ?? '' // Use empty string if not set
            ]);
            $child_id = $pdo->lastInsertId();
        }
        
        $pdo->commit();
        $success = $child_id ? 'Child profile updated successfully!' : 'Child added successfully!';
        
        // Refresh child data
        // Refreshed data from the simplified query
        $stmt = $pdo->prepare("SELECT * FROM children WHERE id = ?");
        $stmt->execute([$child_id]);
        $child = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $child_id ? 'Edit Child' : 'Add Child'; ?> - Child Tracking System</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>

<body>
<div class="container">
    <div class="main-content">
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-user-edit"></i> <?php echo $child_id ? 'Edit Child Profile' : 'Add New Child'; ?></h1>
                <p><?php echo $child_id ? 'Update child information and settings' : 'Add a new child to the tracking system'; ?></p>
            </div>
            <div class="page-actions">
                <a href="children.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Children
                </a>
                <?php if ($child_id): ?>
                    <a href="child_profile.php?id=<?php echo $child_id; ?>" class="btn btn-info">
                        <i class="fas fa-eye"></i> View Profile
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            </div>
        <?php endif; ?>

        <!-- Form now includes 'lrn', 'gender', and  -->
        <form method="POST" enctype="multipart/form-data" class="edit-form">
            <div class="row">
                <!-- Basic Information -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h4><i class="fas fa-user"></i> Basic Information</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="first_name" class="form-label required">First Name</label>
                                        <input type="text" id="first_name" name="first_name" 
                                               value="<?php echo htmlspecialchars($child['first_name']); ?>" 
                                               class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="last_name" class="form-label required">Last Name</label>
                                        <input type="text" id="last_name" name="last_name" 
                                               value="<?php echo htmlspecialchars($child['last_name']); ?>" 
                                               class="form-control" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="date_of_birth" class="form-label required">Date of Birth</label>
                                        <input type="date" id="date_of_birth" name="date_of_birth" 
                                               value="<?php echo $child['date_of_birth']; ?>" 
                                               class="form-control" required>
                                    </div>
                                </div>
                                <!-- Changed from Student ID to LRN -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="lrn" class="form-label">LRN (Learner Reference Number)</label>
                                        <input type="text" id="lrn" name="lrn" 
                                               value="<?php echo htmlspecialchars($child['lrn'] ?? ''); ?>" 
                                               class="form-control" placeholder="Learner Reference Number">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="grade" class="form-label">Grade</label>
                                        <select id="grade" name="grade" class="form-control">
                                            <option value="">Select Grade</option>
                                            <option value="1st Grade" <?php echo $child['grade'] == '1st Grade' ? 'selected' : ''; ?>>1st Grade</option>
                                            <option value="2nd Grade" <?php echo $child['grade'] == '2nd Grade' ? 'selected' : ''; ?>>2nd Grade</option>
                                            <option value="3rd Grade" <?php echo $child['grade'] == '3rd Grade' ? 'selected' : ''; ?>>3rd Grade</option>
                                            <option value="4th Grade" <?php echo $child['grade'] == '4th Grade' ? 'selected' : ''; ?>>4th Grade</option>
                                            <option value="5th Grade" <?php echo $child['grade'] == '5th Grade' ? 'selected' : ''; ?>>5th Grade</option>
                                            <option value="6th Grade" <?php echo $child['grade'] == '6th Grade' ? 'selected' : ''; ?>>6th Grade</option>
                                        </select>
                                    </div>
                                </div>
                                <!-- Added Gender dropdown -->
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="gender" class="form-label">Gender</label>
                                        <select id="gender" name="gender" class="form-control">
                                            <option value="">Select Gender</option>
                                            <option value="Male" <?php echo ($child['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                            <option value="Female" <?php echo ($child['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                            <option value="Other" <?php echo ($child['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="status" class="form-label">Status</label>
                                        <select id="status" name="status" class="form-control">
                                            <option value="active" <?php echo $child['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $child['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            <option value="suspended" <?php echo $child['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Removed Parent/Guardian selection as it's not in the simplified updates -->
                            <!-- Removed Device ID (IMEI) field as it's not in the simplified updates -->
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="emergency_contact" class="form-label">Emergency Contact</label>
                                        <input type="text" id="emergency_contact" name="emergency_contact" 
                                               value="<?php echo htmlspecialchars($child['emergency_contact'] ?? ''); ?>" 
                                               class="form-control" placeholder="Phone number or contact info">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="medical_info" class="form-label">Medical Information</label>
                                <textarea id="medical_info" name="medical_info" rows="3" 
                                          class="form-control" placeholder="Any medical conditions, allergies, or special needs"><?php echo htmlspecialchars($child['medical_info'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Emergency Contacts: <DELETE> Removed this whole section as it's not in the updates -->
                </div>

            </div>

            <!-- Form Actions -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> <?php echo $child_id ? 'Update Child Profile' : 'Add Child'; ?>
                                </button>
                                <a href="children.php" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                                <!-- <DELETE> Removed Delete Child button as it's not in the updates -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>


// Form submission validation
document.querySelector('form').addEventListener('submit', function(e) {
    const requiredFields = this.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        // Check if the field value is empty or only whitespace
        if (!field.value.trim()) {
            field.classList.add('is-invalid'); // Add error class for visual feedback
            isValid = false;
        } else {
            field.classList.remove('is-invalid'); // Remove error class if field is valid
        }
    });
    
    if (!isValid) {
        e.preventDefault(); // Prevent form submission if any field is invalid
        showAlert('Please fill in all required fields', 'danger'); // Show a general error message
    }
});

// Helper function to display temporary alerts
function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
    `;
    
    // Insert the alert just below the main container for better visibility
    document.querySelector('.container').insertBefore(alertDiv, document.querySelector('.main-content'));
    
    // Automatically remove the alert after a few seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000); // 5 seconds
}
</script>

<?php require_once 'includes/footer.php'; ?>
</body>
</html>
