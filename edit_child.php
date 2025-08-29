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
    $stmt = $pdo->prepare("SELECT c.*, p.photo_url FROM children c 
                          LEFT JOIN child_photos p ON c.id = p.child_id 
                          WHERE c.id = ?");
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
        'lrn' => '',
        'grade' => '',
        'school' => '',
        'parent_id' => '',
        'lrn' => '',
        'status' => 'active',
        'medical_info' => '',
        'photo_url' => ''
    ];
}

// Get all parents for dropdown
$stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE role = 'parent' ORDER BY first_name, last_name");
$stmt->execute();
$parents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get emergency contacts
$emergency_contacts = [];
if ($child_id) {
    $stmt = $pdo->prepare("SELECT * FROM emergency_contacts WHERE child_id = ? ORDER BY priority ASC");
    $stmt->execute([$child_id]);
    $emergency_contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'date_of_birth', 'lrn', 'parent_id'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields.");
            }
        }
        
        // Check if student ID is unique
        $stmt = $pdo->prepare("SELECT id FROM children WHERE lrn = ? AND id != ?");
        $stmt->execute([$_POST['lrn'], $child_id]);
        if ($stmt->fetch()) {
            throw new Exception("Student ID already exists.");
        }
        
        if ($child_id) {
            // Update existing child
            $stmt = $pdo->prepare("UPDATE children SET 
                                  first_name = ?, last_name = ?, date_of_birth = ?, 
                                  lrn = ?, grade = ?, school = ?, parent_id = ?, 
                                  lrn = ?, status = ?, medical_info = ?, updated_at = NOW()
                                  WHERE id = ?");
            $stmt->execute([
                $_POST['first_name'], $_POST['last_name'], $_POST['date_of_birth'],
                $_POST['lrn'], $_POST['grade'], $_POST['school'], $_POST['parent_id'],
                $_POST['lrn'], $_POST['status'], $_POST['medical_info'], $child_id
            ]);
        } else {
            // Insert new child
            $stmt = $pdo->prepare("INSERT INTO children 
                                  (first_name, last_name, date_of_birth, lrn, grade, school, 
                                   parent_id, lrn, status, medical_info, created_at, updated_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([
                $_POST['first_name'], $_POST['last_name'], $_POST['date_of_birth'],
                $_POST['lrn'], $_POST['grade'], $_POST['school'], $_POST['parent_id'],
                $_POST['lrn'], $_POST['status'], $_POST['medical_info']
            ]);
            $child_id = $pdo->lastInsertId();
        }
        
        // Handle emergency contacts
        if (isset($_POST['contacts'])) {
            // Delete existing contacts
            $stmt = $pdo->prepare("DELETE FROM emergency_contacts WHERE child_id = ?");
            $stmt->execute([$child_id]);
            
            // Insert new contacts
            foreach ($_POST['contacts'] as $index => $contact) {
                if (!empty($contact['name']) && !empty($contact['phone'])) {
                    $stmt = $pdo->prepare("INSERT INTO emergency_contacts 
                                          (child_id, name, relationship, phone, email, priority) 
                                          VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $child_id, $contact['name'], $contact['relationship'],
                        $contact['phone'], $contact['email'], $index + 1
                    ]);
                }
            }
        }
        
        $pdo->commit();
        $success = $child_id ? 'Child profile updated successfully!' : 'Child added successfully!';
        
        // Refresh child data
        $stmt = $pdo->prepare("SELECT c.*, p.photo_url FROM children c 
                              LEFT JOIN child_photos p ON c.id = p.child_id 
                              WHERE c.id = ?");
        $stmt->execute([$child_id]);
        $child = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>
   <?php include 'includes/header.php'; ?>
<link rel="stylesheet" href="assets/css/main.css">

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
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

    <form method="POST" enctype="multipart/form-data" class="edit-form">
        <div class="row">
             Basic Information 
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
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="lrn" class="form-label required">Student ID</label>
                                    <input type="text" id="lrn" name="lrn" 
                                           value="<?php echo htmlspecialchars($child['lrn']); ?>" 
                                           class="form-control" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="grade" class="form-label">Grade</label>
                                    <select id="grade" name="grade" class="form-control">
                                        <option value="">Select Grade</option>
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo $child['grade'] == $i ? 'selected' : ''; ?>>
                                                Grade <?php echo $i; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="school" class="form-label">School</label>
                                    <input type="text" id="school" name="school" 
                                           value="<?php echo htmlspecialchars($child['school']); ?>" 
                                           class="form-control" placeholder="School name">
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
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="parent_id" class="form-label required">Parent/Guardian</label>
                                    <select id="parent_id" name="parent_id" class="form-control" required>
                                        <option value="">Select Parent</option>
                                        <?php foreach ($parents as $parent): ?>
                                            <option value="<?php echo $parent['id']; ?>" 
                                                    <?php echo $child['parent_id'] == $parent['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name'] . ' (' . $parent['email'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="lrn" class="form-label">Device ID (IMEI)</label>
                                    <input type="text" id="lrn" name="lrn" 
                                           value="<?php echo htmlspecialchars($child['lrn']); ?>" 
                                           class="form-control" placeholder="Device IMEI number">
                                    <small class="form-text text-muted">15-digit IMEI number for GPS tracking</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="medical_info" class="form-label">Medical Information</label>
                            <textarea id="medical_info" name="medical_info" rows="3" 
                                      class="form-control" placeholder="Any medical conditions, allergies, or special needs"><?php echo htmlspecialchars($child['medical_info']); ?></textarea>
                        </div>
                    </div>
                </div>

                 Emergency Contacts 
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-phone"></i> Emergency Contacts</h4>
                        <button type="button" onclick="addContact()" class="btn btn-success btn-sm">
                            <i class="fas fa-plus"></i> Add Contact
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="contacts-container">
                            <?php if ($emergency_contacts): ?>
                                <?php foreach ($emergency_contacts as $index => $contact): ?>
                                    <div class="contact-form-group" data-index="<?php echo $index; ?>">
                                        <div class="contact-header">
                                            <h6>Contact <?php echo $index + 1; ?></h6>
                                            <button type="button" onclick="removeContact(this)" class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label">Name</label>
                                                    <input type="text" name="contacts[<?php echo $index; ?>][name]" 
                                                           value="<?php echo htmlspecialchars($contact['name']); ?>" 
                                                           class="form-control" required>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label class="form-label">Relationship</label>
                                                    <select name="contacts[<?php echo $index; ?>][relationship]" class="form-control">
                                                        <option value="parent" <?php echo $contact['relationship'] === 'parent' ? 'selected' : ''; ?>>Parent</option>
                                                        <option value="guardian" <?php echo $contact['relationship'] === 'guardian' ? 'selected' : ''; ?>>Guardian</option>
                                                        <option value="grandparent" <?php echo $contact['relationship'] === 'grandparent' ? 'selected' : ''; ?>>Grandparent</option>
                                                        <option value="sibling" <?php echo $contact['relationship'] === 'sibling' ? 'selected' : ''; ?>>Sibling</option>
                                                        <option value="other" <?php echo $contact['relationship'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label class="form-label">Phone</label>
                                                    <input type="tel" name="contacts[<?php echo $index; ?>][phone]" 
                                                           value="<?php echo htmlspecialchars($contact['phone']); ?>" 
                                                           class="form-control" required>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label class="form-label">Email</label>
                                                    <input type="email" name="contacts[<?php echo $index; ?>][email]" 
                                                           value="<?php echo htmlspecialchars($contact['email']); ?>" 
                                                           class="form-control">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="contact-form-group" data-index="0">
                                    <div class="contact-header">
                                        <h6>Contact 1</h6>
                                        <button type="button" onclick="removeContact(this)" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label">Name</label>
                                                <input type="text" name="contacts[0][name]" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="form-label">Relationship</label>
                                                <select name="contacts[0][relationship]" class="form-control">
                                                    <option value="parent">Parent</option>
                                                    <option value="guardian">Guardian</option>
                                                    <option value="grandparent">Grandparent</option>
                                                    <option value="sibling">Sibling</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label class="form-label">Phone</label>
                                                <input type="tel" name="contacts[0][phone]" class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label class="form-label">Email</label>
                                                <input type="email" name="contacts[0][email]" class="form-control">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

             Photo and Settings Sidebar 
            <div class="col-lg-4">
                 Photo Upload 
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-camera"></i> Profile Photo</h4>
                    </div>
                    <div class="card-body text-center">
                        <div class="photo-upload-container">
                            <?php if ($child['photo_url']): ?>
                                <img src="<?php echo htmlspecialchars($child['photo_url']); ?>" 
                                     alt="Child Photo" class="current-photo" id="photo-preview">
                            <?php else: ?>
                                <div class="photo-placeholder" id="photo-preview">
                                    <i class="fas fa-user"></i>
                                    <p>No photo uploaded</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="photo-upload-actions">
                            <input type="file" id="photo-upload" name="photo" accept="image/*" style="display: none;" onchange="previewPhoto(this)">
                            <button type="button" onclick="document.getElementById('photo-upload').click()" class="btn btn-primary btn-sm">
                                <i class="fas fa-upload"></i> Upload Photo
                            </button>
                            <?php if ($child['photo_url']): ?>
                                <button type="button" onclick="removePhoto()" class="btn btn-danger btn-sm">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                 Device Settings 
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-mobile-alt"></i> Device Settings</h4>
                    </div>
                    <div class="card-body">
                        <div class="device-status">
                            <?php if ($child['lrn']): ?>
                                <div class="status-item">
                                    <span class="status-label">Device Status:</span>
                                    <span class="badge badge-success" id="device-status">Connected</span>
                                </div>
                                <div class="status-item">
                                    <span class="status-label">Last Seen:</span>
                                    <span id="last-seen">Checking...</span>
                                </div>
                                <button type="button" onclick="testDevice()" class="btn btn-info btn-sm w-100 mt-2">
                                    <i class="fas fa-satellite-dish"></i> Test Device Connection
                                </button>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    No device assigned. Enter IMEI number above.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="tracking-settings">
                            <h6>Tracking Settings</h6>
                            <div class="form-group">
                                <label class="form-check">
                                    <input type="checkbox" name="auto_tracking" class="form-check-input" 
                                           <?php echo ($child['auto_tracking'] ?? true) ? 'checked' : ''; ?>>
                                    <span class="form-check-label">Auto Tracking</span>
                                </label>
                            </div>
                            <div class="form-group">
                                <label class="form-check">
                                    <input type="checkbox" name="geofence_alerts" class="form-check-input" 
                                           <?php echo ($child['geofence_alerts'] ?? true) ? 'checked' : ''; ?>>
                                    <span class="form-check-label">Geofence Alerts</span>
                                </label>
                            </div>
                            <div class="form-group">
                                <label class="form-check">
                                    <input type="checkbox" name="battery_alerts" class="form-check-input" 
                                           <?php echo ($child['battery_alerts'] ?? true) ? 'checked' : ''; ?>>
                                    <span class="form-check-label">Low Battery Alerts</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                 Quick Actions 
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-bolt"></i> Quick Actions</h4>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions-grid">
                            <?php if ($child_id): ?>
                                <button type="button" onclick="viewLocation()" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-map-marker-alt"></i> View Current Location
                                </button>
                                <button type="button" onclick="viewHistory()" class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-history"></i> Location History
                                </button>
                                <button type="button" onclick="sendAlert()" class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-bell"></i> Send Alert
                                </button>
                                <button type="button" onclick="generateReport()" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-file-alt"></i> Generate Report
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

         Form Actions 
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
                            <?php if ($child_id): ?>
                                <button type="button" onclick="deleteChild()" class="btn btn-danger btn-lg">
                                    <i class="fas fa-trash"></i> Delete Child
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
let contactIndex = <?php echo count($emergency_contacts); ?>;

function addContact() {
    const container = document.getElementById('contacts-container');
    const contactHtml = `
        <div class="contact-form-group" data-index="${contactIndex}">
            <div class="contact-header">
                <h6>Contact ${contactIndex + 1}</h6>
                <button type="button" onclick="removeContact(this)" class="btn btn-danger btn-sm">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label class="form-label">Name</label>
                        <input type="text" name="contacts[${contactIndex}][name]" class="form-control" required>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="form-label">Relationship</label>
                        <select name="contacts[${contactIndex}][relationship]" class="form-control">
                            <option value="parent">Parent</option>
                            <option value="guardian">Guardian</option>
                            <option value="grandparent">Grandparent</option>
                            <option value="sibling">Sibling</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="contacts[${contactIndex}][phone]" class="form-control" required>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="contacts[${contactIndex}][email]" class="form-control">
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', contactHtml);
    contactIndex++;
}

function removeContact(button) {
    const contactGroup = button.closest('.contact-form-group');
    contactGroup.remove();
    
    // Renumber remaining contacts
    const contacts = document.querySelectorAll('.contact-form-group');
    contacts.forEach((contact, index) => {
        contact.querySelector('h6').textContent = `Contact ${index + 1}`;
    });
}

function previewPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const preview = document.getElementById('photo-preview');
            preview.innerHTML = `<img src="${e.target.result}" alt="Photo Preview" class="current-photo">`;
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

function removePhoto() {
    if (confirm('Are you sure you want to remove the photo?')) {
        document.getElementById('photo-preview').innerHTML = `
            <div class="photo-placeholder">
                <i class="fas fa-user"></i>
                <p>No photo uploaded</p>
            </div>
        `;
        
        // Add hidden input to mark photo for deletion
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'remove_photo';
        hiddenInput.value = '1';
        document.querySelector('form').appendChild(hiddenInput);
    }
}

function testDevice() {
    const deviceId = document.getElementById('lrn').value;
    
    if (!deviceId) {
        alert('Please enter a device ID first');
        return;
    }
    
    const button = event.target;
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
    
    fetch(`api/test_device.php?lrn=${deviceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('device-status').className = 'badge badge-success';
                document.getElementById('device-status').textContent = 'Connected';
                showAlert('Device test successful', 'success');
            } else {
                document.getElementById('device-status').className = 'badge badge-danger';
                document.getElementById('device-status').textContent = 'Disconnected';
                showAlert('Device test failed: ' + data.error, 'danger');
            }
        })
        .catch(error => {
            showAlert('Network error: ' + error.message, 'danger');
        })
        .finally(() => {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-satellite-dish"></i> Test Device Connection';
        });
}

function viewLocation() {
    window.open(`child_profile.php?id=<?php echo $child_id; ?>`, '_blank');
}

function viewHistory() {
    window.open(`location_history.php?child_id=<?php echo $child_id; ?>`, '_blank');
}

function sendAlert() {
    const message = prompt('Enter alert message:');
    if (message) {
        fetch('api/send_alert.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                child_id: <?php echo $child_id; ?>,
                message: message,
                type: 'manual'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Alert sent successfully', 'success');
            } else {
                showAlert('Failed to send alert', 'danger');
            }
        });
    }
}

function generateReport() {
    window.open(`api/generate_child_report.php?child_id=<?php echo $child_id; ?>`, '_blank');
}

function deleteChild() {
    if (confirm('Are you sure you want to delete this child profile? This action cannot be undone.')) {
        if (confirm('This will permanently delete all location history and alerts. Continue?')) {
            fetch(`api/delete_child.php?id=<?php echo $child_id; ?>`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Child profile deleted successfully', 'success');
                    setTimeout(() => {
                        window.location.href = 'children.php';
                    }, 2000);
                } else {
                    showAlert('Failed to delete child profile', 'danger');
                }
            });
        }
    }
}

function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
    `;
    
    document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector('.row'));
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const requiredFields = this.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    if (!isValid) {
        e.preventDefault();
        showAlert('Please fill in all required fields', 'danger');
    }
});

// Check device status on page load
document.addEventListener('DOMContentLoaded', function() {
    const deviceId = '<?php echo $child['lrn']; ?>';
    if (deviceId) {
        checkDeviceStatus(deviceId);
    }
});

function checkDeviceStatus(deviceId) {
    fetch(`api/check_device_status.php?lrn=${deviceId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('device-status').className = `badge badge-${data.status === 'online' ? 'success' : 'warning'}`;
                document.getElementById('device-status').textContent = data.status === 'online' ? 'Connected' : 'Offline';
                document.getElementById('last-seen').textContent = data.last_seen || 'Never';
            }
        });
}
</script>

<?php
require_once 'includes/footer.php';
?>
