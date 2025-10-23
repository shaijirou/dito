<?php
require_once 'config/config.php';
requireLogin();

// Only admin can add children
if ($_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $lrn = sanitizeInput($_POST['lrn']);
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $date_of_birth = sanitizeInput($_POST['date_of_birth']);
    $grade = sanitizeInput($_POST['grade']);
    $age = sanitizeInput($_POST['age']);
    $gender = sanitizeInput($_POST['gender']);
    $emergency_contact = sanitizeInput($_POST['emergency_contact']);
    $medical_info = sanitizeInput($_POST['medical_info']);
    $parent_name = sanitizeInput($_POST['parent_name'] ?? '');
    
    // Handle photo upload
    $photo_path = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $upload_dir = 'uploads/children/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions) && $_FILES['photo']['size'] <= MAX_FILE_SIZE) {
            $photo_name = uniqid() . '.' . $file_extension;
            $photo_path = $upload_dir . $photo_name;
            
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
                $error = 'Failed to upload photo.';
                $photo_path = '';
            }
        } else {
            $error = 'Invalid photo format or size too large.';
        }
    }
    
    if (empty($error)) {
        // Validation
        if (empty($lrn) || empty($first_name) || empty($last_name) || empty($date_of_birth) || empty($grade)) {
            $error = 'Please fill in all required fields.';
        } else {
            try {
                // Check if student ID already exists
                $stmt = $pdo->prepare("SELECT id FROM children WHERE lrn = ?");
                $stmt->execute([$lrn]);
                
                if ($stmt->fetch()) {
                    $error = 'Student ID already exists.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO children (first_name, last_name, date_of_birth, grade, photo, lrn, age, gender, emergency_contact, medical_info, parent_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    if ($stmt->execute([$first_name, $last_name, $date_of_birth, $grade, $photo_path, $lrn, $age, $gender, $emergency_contact, $medical_info, $parent_name])) {
                        $child_id = $pdo->lastInsertId();
                        
                        require_once 'api/auto_assign_relationships.php';
                        $assignment_result = autoAssignRelationships($child_id, $pdo);
                        
                        $success = 'Child added successfully!';
                        if ($assignment_result['parent_assignments'] > 0) {
                            $success .= ' Parent automatically assigned.';
                        }
                        if ($assignment_result['teacher_assignments'] > 0) {
                            $success .= ' Teacher automatically assigned.';
                        }
                        
                        // Clear form
                        $_POST = [];
                    } else {
                        $error = 'Failed to add child. Please try again.';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Database error. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Child - Child Tracking System</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="main-content">
            <div class="d-flex justify-between align-center mb-3">
                <h1>Add New Child</h1>
                <a href="children.php" class="btn btn-secondary">Back to Children</a>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Child Information</h2>
                </div>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="lrn" class="form-label">Learner Reference Number *</label>
                            <input type="text" id="lrn" name="lrn" class="form-control" value="<?php echo isset($_POST['lrn']) ? htmlspecialchars($_POST['lrn']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="grade" class="form-label">Grade *</label>
                            <select id="grade" name="grade" class="form-control" required>
                                <option value="1st Grade" <?php echo (isset($_POST['grade']) && $_POST['grade'] === '1st Grade') ? 'selected' : ''; ?>>1st Grade</option>
                                <option value="2nd Grade" <?php echo (isset($_POST['grade']) && $_POST['grade'] === '2nd Grade') ? 'selected' : ''; ?>>2nd Grade</option>
                                <option value="3rd Grade" <?php echo (isset($_POST['grade']) && $_POST['grade'] === '3rd Grade') ? 'selected' : ''; ?>>3rd Grade</option>
                                <option value="4th Grade" <?php echo (isset($_POST['grade']) && $_POST['grade'] === '4th Grade') ? 'selected' : ''; ?>>4th Grade</option>
                                <option value="5th Grade" <?php echo (isset($_POST['grade']) && $_POST['grade'] === '5th Grade') ? 'selected' : ''; ?>>5th Grade</option>
                                <option value="6th Grade" <?php echo (isset($_POST['grade']) && $_POST['grade'] === '6th Grade') ? 'selected' : ''; ?>>6th Grade</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_birth" class="form-label">Date of Birth *</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="emergency_contact" class="form-label">Emergency Contact</label>
                            <input type="tel" id="emergency_contact" name="emergency_contact" class="form-control" value="<?php echo isset($_POST['emergency_contact']) ? htmlspecialchars($_POST['emergency_contact']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="age" class="form-label">Age</label>
                            <input type="text" id="age" name="age" class="form-control" value="<?php echo isset($_POST['age']) ? htmlspecialchars($_POST['age']) : ''; ?>" placeholder="Age">
                        </div>

                        <div class="form-group">
                            <label for="gender" class="form-label">Gender</label>
                            <input type="text" id="gender" name="gender" class="form-control" value="<?php echo isset($_POST['gender']) ? htmlspecialchars($_POST['gender']) : ''; ?>" placeholder="Gender">
                        </div>
                    </div>

                    <!-- Added parent_name field for automatic parent assignment -->
                    <div class="form-group">
                        <label for="parent_name" class="form-label">Parent Name (for auto-assignment)</label>
                        <input type="text" id="parent_name" name="parent_name" class="form-control" value="<?php echo isset($_POST['parent_name']) ? htmlspecialchars($_POST['parent_name']) : ''; ?>" placeholder="Enter parent's full name to auto-assign">
                        <small>Leave blank to skip auto-assignment. Must match a parent's full name in the system.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="photo" class="form-label">Photo</label>
                        <input type="file" id="photo" name="photo" class="form-control" accept="image/*">
                        <small>Max file size: 5MB. Supported formats: JPG, PNG, GIF</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="medical_info" class="form-label">Medical Information</label>
                        <textarea id="medical_info" name="medical_info" class="form-control" rows="3" placeholder="Any medical conditions, allergies, or special needs..."><?php echo isset($_POST['medical_info']) ? htmlspecialchars($_POST['medical_info']) : ''; ?></textarea>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Add Child</button>
                        <a href="children.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
