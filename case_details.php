<?php
require_once 'config/config.php';
requireLogin();

$error = '';
$case = null;
$child = null;
$reporter = null;
$resolver = null;
$case_updates = [];

// Get case number from URL
$case_number = isset($_GET['case']) ? sanitizeInput($_GET['case']) : '';

if ($case_number) {
    try {
        // Get case details
        $stmt = $pdo->prepare("SELECT mc.*, 
                              c.first_name, c.last_name, c.lrn, c.photo, c.grade, c.date_of_birth,
                              u1.full_name as reported_by_name, u1.email as reporter_email, u1.phone as reporter_phone,
                              u2.full_name as resolved_by_name
                              FROM missing_cases mc 
                              JOIN children c ON mc.child_id = c.id 
                              JOIN users u1 ON mc.reported_by = u1.id
                              LEFT JOIN users u2 ON mc.resolved_by = u2.id
                              WHERE mc.case_number = ?");
        $stmt->execute([$case_number]);
        $case = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$case) {
            $error = 'Case not found.';
        } else {
            // Check permissions
            if ($_SESSION['role'] === 'parent') {
                $stmt = $pdo->prepare("SELECT 1 FROM parent_child WHERE parent_id = ? AND child_id = ?");
                $stmt->execute([$_SESSION['user_id'], $case['child_id']]);
                if (!$stmt->fetch()) {
                    $error = 'You do not have permission to view this case.';
                    $case = null;
                }
            }
            
            if ($case) {
                // Get case updates/timeline
                $stmt = $pdo->prepare("SELECT * FROM case_updates WHERE case_id = ? ORDER BY created_at DESC");
                $stmt->execute([$case['id']]);
                $case_updates = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get child's current location
                $stmt = $pdo->prepare("SELECT * FROM location_tracking WHERE child_id = ? ORDER BY timestamp DESC LIMIT 1");
                $stmt->execute([$case['child_id']]);
                $current_location = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    } catch (PDOException $e) {
        $error = 'Failed to load case details.';
    }
} else {
    $error = 'Invalid case number.';
}

// Handle case update submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_update']) && $case) {
    $update_text = sanitizeInput($_POST['update_text']);
    
    if (!empty($update_text)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO case_updates (case_id, updated_by, update_text, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$case['id'], $_SESSION['user_id'], $update_text]);

            
            header("Location: case_details.php?case=" . urlencode($case_number));
            exit();
        } catch (PDOException $e) {
            $error = 'Failed to add update.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case Details - Child Tracking System</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <style>

/* Modal Overlay */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    display: none; /* Hidden by default */
    justify-content: center;
    align-items: center;
    z-index: 1000;
    animation: fadeIn 0.3s ease-in-out;
}

/* Modal Container */
.modal-content {
    background: #fff;
    border-radius: 10px;
    width: 95%;
    max-width: 600px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    overflow: hidden;
    animation: slideDown 0.3s ease-in-out;
}

/* Modal Header */
.modal-header {
    background-color: #28a745;
    color: #fff;
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
}

.modal-body {
    padding: 1rem 1.5rem;
}

.modal-footer {
    padding: 1rem 1.5rem;
    text-align: right;
    background: #f8f9fa;
    border-top: 1px solid #dee2e6;
}

/* Close Button */
.btn-close {
    background: transparent;
    border: none;
    font-size: 1.3rem;
    color: #fff;
    cursor: pointer;
}

/* Button Styles */
.modal-footer .btn {
    margin-left: 0.5rem;
    padding: 0.6rem 1.2rem;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    font-weight: 500;
}

.btn-success {
    background-color: #28a745;
    color: white;
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideDown {
    from { transform: translateY(-10px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}
</style>

</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="main-content">
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                </div>
                <a href="cases.php" class="btn btn-primary">Back to Cases</a>
            <?php elseif ($case): ?>
                <div class="page-header">
                    <div class="page-title">
                        <h1><i class="fas fa-folder-open"></i> Case Details: <?php echo htmlspecialchars($case['case_number']); ?></h1>
                        <p>Missing child case information and timeline</p>
                    </div>
                    <div class="page-actions">
                        <a href="track_child.php?id=<?php echo $case['child_id']; ?>" class="btn btn-success">
                            <i class="fas fa-map-marker-alt"></i> Track Child
                        </a>
                        <a href="cases.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Cases
                        </a>
                    </div>
                </div>
                
                 <!-- Case Status Banner  -->
                <div class="alert alert-<?php echo $case['status'] === 'resolved' ? 'success' : ($case['priority'] === 'critical' ? 'danger' : 'warning'); ?> case-status-banner">
                    <div class="case-status-content">
                        <div>
                            <h3>
                                <i class="fas fa-<?php echo $case['status'] === 'resolved' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                                Case Status: <?php echo ucfirst($case['status']); ?>
                            </h3>
                            <p>Priority: <strong><?php echo ucfirst($case['priority']); ?></strong></p>
                        </div>
                        <?php if ($case['status'] === 'active'): ?>
                            <div class="case-duration">
                                <strong>Duration:</strong>
                                <?php
                                $duration = time() - strtotime($case['created_at']);
                                $hours = floor($duration / 3600);
                                $minutes = floor(($duration % 3600) / 60);
                                echo $hours . 'h ' . $minutes . 'm';
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row">
                     <!-- Left Column  -->
                    <div class="col-lg-8">
                         <!-- Child Information Card  -->
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title"><i class="fas fa-child"></i> Child Information</h2>
                            </div>
                            <div class="card-body">
                                <div class="child-profile-compact">
                                    <div class="child-photo-compact">
                                        <?php if ($case['photo']): ?>
                                            <img src="<?php echo htmlspecialchars($case['photo']); ?>" alt="Child Photo">
                                        <?php else: ?>
                                            <div class="photo-placeholder">
                                                <i class="fas fa-user"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="child-info-compact">
                                        <h3><?php echo htmlspecialchars($case['first_name'] . ' ' . $case['last_name']); ?></h3>
                                        <div class="info-grid">
                                            <div class="info-item">
                                                <span class="info-label">Student ID</span>
                                                <span class="info-value"><?php echo htmlspecialchars($case['lrn']); ?></span>
                                            </div>
                                            <div class="info-item">
                                                <span class="info-label">Grade</span>
                                                <span class="info-value"><?php echo htmlspecialchars($case['grade']); ?></span>
                                            </div>
                                            <div class="info-item">
                                                <span class="info-label">Age</span>
                                                <span class="info-value"><?php echo calculateAge($case['date_of_birth']); ?> years</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                         <!-- Case Details Card  -->
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title"><i class="fas fa-info-circle"></i> Case Details</h2>
                            </div>
                            <div class="card-body">
                                <div class="case-details-grid">
                                    <div class="detail-item">
                                        <span class="detail-label">Case Number</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($case['case_number']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Reported Date</span>
                                        <span class="detail-value"><?php echo date('M j, Y g:i A', strtotime($case['created_at'])); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Reported By</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($case['reported_by_name']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Last Seen Time</span>
                                        <span class="detail-value">
                                            <?php echo $case['last_seen_time'] ? date('M j, Y g:i A', strtotime($case['last_seen_time'])) : 'Not specified'; ?>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Last Seen Location</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($case['last_seen_location'] ?: 'Not specified'); ?></span>
                                    </div>
                                    <?php if ($case['status'] === 'resolved'): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Resolved Date</span>
                                            <span class="detail-value"><?php echo date('M j, Y g:i A', strtotime($case['resolved_at'])); ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label">Resolved By</span>
                                            <span class="detail-value"><?php echo htmlspecialchars($case['resolved_by_name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="detail-section">
                                    <h4>Description of Circumstances</h4>
                                    <p><?php echo nl2br(htmlspecialchars($case['description'])); ?></p>
                                </div>
                                
                                <?php if ($case['resolution_notes']): ?>
                                    <div class="detail-section">
                                        <h4>Resolution Notes</h4>
                                        <p><?php echo nl2br(htmlspecialchars($case['resolution_notes'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                         <!-- Current Location Map  -->
                        <?php if (isset($current_location)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title"><i class="fas fa-map-marker-alt"></i> Current Location</h2>
                            </div>
                            <div class="card-body">
                                <div id="map" class="map-container"></div>
                                <div class="location-info">
                                    <p><strong>Last Update:</strong> <?php echo date('M j, Y g:i A', strtotime($current_location['timestamp'])); ?></p>
                                    <p><strong>Coordinates:</strong> <?php echo $current_location['latitude']; ?>, <?php echo $current_location['longitude']; ?></p>
                                    <p><strong>Accuracy:</strong> <?php echo $current_location['accuracy']; ?>m</p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                     <!-- Right Column  -->
                    <div class="col-lg-4">
                         <!-- Quick Actions  -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-bolt"></i> Quick Actions</h3>
                            </div>
                            <div class="card-body">
                                <div class="quick-actions-list">
                                    <a href="track_child.php?id=<?php echo $case['child_id']; ?>" class="btn btn-success btn-block">
                                        <i class="fas fa-map-marker-alt"></i> Track Child Location
                                    </a>
                                    <a href="child_profile.php?id=<?php echo $case['child_id']; ?>" class="btn btn-primary btn-block">
                                        <i class="fas fa-user"></i> View Child Profile
                                    </a>
                                    <?php if (($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'teacher') && $case['status'] === 'active'): ?>
                                        <button onclick="showResolveModal()" class="btn btn-warning btn-block">
                                            <i class="fas fa-check"></i> Resolve Case
                                        </button>
                                    <?php endif; ?>
                                    <a href="tel:<?php echo htmlspecialchars($case['reporter_phone']); ?>" class="btn btn-info btn-block">
                                        <i class="fas fa-phone"></i> Call Reporter
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                         <!-- Reporter Information  -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-user-tie"></i> Reporter Information</h3>
                            </div>
                            <div class="card-body">
                                <div class="reporter-info">
                                    <p><strong>Name:</strong> <?php echo htmlspecialchars($case['reported_by_name']); ?></p>
                                    <p><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($case['reporter_email']); ?>"><?php echo htmlspecialchars($case['reporter_email']); ?></a></p>
                                    <p><strong>Phone:</strong> <a href="tel:<?php echo htmlspecialchars($case['reporter_phone']); ?>"><?php echo htmlspecialchars($case['reporter_phone']); ?></a></p>
                                </div>
                            </div>
                        </div>
                        
                         <!-- Case Timeline  -->
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><i class="fas fa-history"></i> Case Timeline</h3>
                            </div>
                            <div class="card-body">
                                <div class="timeline">
                                    <div class="timeline-item">
                                        <div class="timeline-marker"></div>
                                        <div class="timeline-content">
                                            <strong>Case Reported</strong>
                                            <p><?php echo date('M j, Y g:i A', strtotime($case['created_at'])); ?></p>
                                            <small>by <?php echo htmlspecialchars($case['reported_by_name']); ?></small>
                                        </div>
                                    </div>
                                    
                                    <?php foreach ($case_updates as $update): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-marker"></div>
                                            <div class="timeline-content">
                                                <p><?php echo nl2br(htmlspecialchars($update['update_text'])); ?></p>
                                                <small><?php echo date('M j, Y g:i A', strtotime($update['created_at'])); ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if ($case['status'] === 'resolved'): ?>
                                        <div class="timeline-item timeline-item-success">
                                            <div class="timeline-marker"></div>
                                            <div class="timeline-content">
                                                <strong>Case Resolved</strong>
                                                <p><?php echo date('M j, Y g:i A', strtotime($case['resolved_at'])); ?></p>
                                                <small>by <?php echo htmlspecialchars($case['resolved_by_name']); ?></small>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'teacher') && $case['status'] === 'active'): ?>
                                    <form method="POST" class="mt-3">
                                        <input type="hidden" name="add_update" value="1">
                                        <div class="form-group">
                                            <textarea name="update_text" class="form-control" rows="3" placeholder="Add case update..." required></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus"></i> Add Update
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
     <!-- Resolve Case Modal  -->
    <?php if (($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'teacher') && $case && $case['status'] === 'active'): ?>
    <!-- Resolve Case Modal -->

        <div id="resolveModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3><i class="fas fa-check-circle"></i> Resolve Case</h3>
                    <button type="button" class="btn-close" onclick="hideResolveModal()">&times;</button>
                </div>

                <form method="POST" action="cases.php">
                    <input type="hidden" name="case_id" value="<?php echo $case['id']; ?>">
                    <input type="hidden" name="update_case" value="1">

                    <div class="modal-body">
                        <div class="form-group">
                            <label for="status" class="form-label">Status</label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="resolved">Resolved - Child Found Safe</option>
                                <option value="cancelled">Cancelled - False Alarm</option>
                            </select>
                        </div>

                        <div class="form-group mt-3">
                            <label for="resolution_notes" class="form-label">Resolution Notes</label>
                            <textarea id="resolution_notes" name="resolution_notes" class="form-control" rows="4" placeholder="Describe how the case was resolved..." required></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> Resolve Case
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="hideResolveModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>


    <?php endif; ?>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        <?php if (isset($current_location)): ?>
        // Initialize map
        const map = L.map('map').setView([<?php echo $current_location['latitude']; ?>, <?php echo $current_location['longitude']; ?>], 15);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        const marker = L.marker([<?php echo $current_location['latitude']; ?>, <?php echo $current_location['longitude']; ?>]).addTo(map);
        marker.bindPopup('<strong><?php echo htmlspecialchars($case['first_name'] . ' ' . $case['last_name']); ?></strong><br>Last seen: <?php echo date('M j, g:i A', strtotime($current_location['timestamp'])); ?>').openPopup();
        <?php endif; ?>
        
        function showResolveModal() {
            document.getElementById('resolveModal').style.display = 'flex';
        }
        
        function hideResolveModal() {
            document.getElementById('resolveModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('resolveModal');
            if (event.target === modal) {
                hideResolveModal();
            }
        }
    </script>
</body>
</html>

<?php
function calculateAge($birthdate) {
    $today = new DateTime();
    $birth = new DateTime($birthdate);
    return $today->diff($birth)->y;
}
?>
