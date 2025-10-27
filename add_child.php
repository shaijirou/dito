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
                    $error = 'Student LRN already exists.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO children (first_name, last_name, date_of_birth, grade, lrn, age, gender, emergency_contact, medical_info, parent_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    if ($stmt->execute([$first_name, $last_name, $date_of_birth, $grade, $lrn, $age, $gender, $emergency_contact, $medical_info, $parent_name])) {
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
    <style>
          <style>
        /* === Modern Form Styling === */
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 1.5rem;
        }

        .main-content {
            background: #ffffff;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .main-content h1 {
            font-size: 1.75rem;
            color: #222;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 0.4rem;
            color: #333;
        }

        .form-control {
            padding: 0.6rem 0.8rem;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: #007bff;
            outline: none;
        }

        .btn {
            padding: 0.7rem 1.4rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #545b62;
        }

        .alert {
            padding: 0.9rem 1.2rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.95rem;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .card-header {
            border-bottom: 1px solid #ddd;
            margin-bottom: 1.5rem;
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #222;
        }

        .gap-2 {
            display: flex;
            gap: 0.6rem;
        }

        .d-flex {
            display: flex;
        }

        .justify-between {
            justify-content: space-between;
        }

        .align-center {
            align-items: center;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
    </style>
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
                    <h2 class="--primary-50">Child Information</h2>
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
