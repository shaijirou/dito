<?php
require_once 'config/config.php';
requireLogin();

$error = '';
$child = null;
$locations = [];

// Get child ID from URL
$child_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($child_id) {
    try {
        // Check if user has permission to view this child
        if ($_SESSION['role'] === 'admin') {
            $stmt = $pdo->prepare("SELECT * FROM children WHERE id = ? AND status = 'active'");
            $stmt->execute([$child_id]);
        } elseif ($_SESSION['role'] === 'teacher') {
            $stmt = $pdo->prepare("SELECT c.* FROM children c 
                                  JOIN teacher_child tc ON c.id = tc.child_id 
                                  WHERE c.id = ? AND tc.teacher_id = ? AND c.status = 'active'");
            $stmt->execute([$child_id, $_SESSION['user_id']]);
        } else {
            // Parent
            $stmt = $pdo->prepare("SELECT c.* FROM children c 
                                  JOIN parent_child pc ON c.id = pc.child_id 
                                  WHERE c.id = ? AND pc.parent_id = ? AND c.status = 'active'");
            $stmt->execute([$child_id, $_SESSION['user_id']]);
        }
        
        $child = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($child) {
            // Get recent location data
            $stmt = $pdo->prepare("SELECT * FROM location_tracking 
                                  WHERE child_id = ? 
                                  ORDER BY timestamp DESC 
                                  LIMIT 50");
            $stmt->execute([$child_id]);
            $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get current location (most recent)
            $current_location = !empty($locations) ? $locations[0] : null;
        } else {
            $error = 'Child not found or you do not have permission to view this child.';
        }
    } catch (PDOException $e) {
        $error = 'Failed to load child data.';
    }
} else {
    $error = 'Invalid child ID.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Child - Child Tracking System</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="main-content">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
            <?php elseif ($child): ?>
                <div class="d-flex justify-between align-center mb-3">
                    <h1>Track Child: <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></h1>
                    <div class="d-flex gap-2">
                        <button onclick="refreshLocation()" class="btn btn-success">Refresh Location</button>
                        <a href="child_profile.php?id=<?php echo $child['id']; ?>" class="btn btn-primary">View Profile</a>
                        <a href="dashboard.php" class="btn btn-secondary">Back</a>
                    </div>
                </div>
                
                <!-- Child Info Card -->
                <div class="card mb-3">
                    <div class="child-profile">
                       <div class="child-photo">
                            <?php if ($child['photo']): ?>
                                <img src="<?php echo htmlspecialchars($child['photo']); ?>" alt="Child Photo">
                            <?php else: ?>
                                <div style="width: 150px; height: 150px; background: #ddd; border-radius: var(--radius-xl); display: flex; align-items: center; justify-content: center; font-size: 14px; color: #666;">
                                    No Photo
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="child-info">
                            <h2><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></h2>
                            <div class="info-grid">
                                <div class="info-item">
                                    <span class="info-label">Student ID</span>
                                    <span class="info-value"><?php echo htmlspecialchars($child['lrn']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Grade</span>
                                    <span class="info-value"><?php echo htmlspecialchars($child['grade']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Device Status</span>
                                    <span class="info-value">
                                        <?php if ($child['lrn']): ?>
                                            <span class="badge badge-success">Connected</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">No Device</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Last Update</span>
                                    <span class="info-value">
                                        <?php if ($current_location): ?>
                                            <?php echo date('M j, Y g:i A', strtotime($current_location['timestamp'])); ?>
                                        <?php else: ?>
                                            No location data
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Current Location Card -->
                <?php if ($current_location): ?>
                <div class="card mb-3">
                    <div class="card-header">
                        <h2 class="card-title">Current Location</h2>
                    </div>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Coordinates</span>
                            <span class="info-value"><?php echo $current_location['latitude']; ?>, <?php echo $current_location['longitude']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Accuracy</span>
                            <span class="info-value"><?php echo $current_location['accuracy']; ?>m</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Safe Zone Status</span>
                            <span class="info-value">
                                <?php if ($current_location['inside_geofence']): ?>
                                    <span class="badge badge-success">Inside Safe Zone</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Outside Safe Zone</span>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Map -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h2 class="card-title">Location Map</h2>
                    </div>
                    <div id="map" class="map-container"></div>
                </div>
                
                <!-- Location History -->
                <?php if (!empty($locations)): ?>
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Location History</h2>
                    </div>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Coordinates</th>
                                <th>Accuracy</th>
                                <th>Safe Zone</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($locations, 0, 20) as $location): ?>
                            <tr>
                                <td><?php echo date('M j, Y g:i A', strtotime($location['timestamp'])); ?></td>
                                <td><?php echo $location['latitude']; ?>, <?php echo $location['longitude']; ?></td>
                                <td><?php echo $location['accuracy']; ?>m</td>
                                <td>
                                    <?php if ($location['inside_geofence']): ?>
                                        <span class="badge badge-success">Inside</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Outside</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        let map;
        let markers = [];
        
        function initMap() {
            <?php if ($current_location): ?>
                // Initialize map with current location
                map = L.map('map').setView([<?php echo $current_location['latitude']; ?>, <?php echo $current_location['longitude']; ?>], 15);
            <?php else: ?>
                // Default location (school coordinates - you should set this to your school's location)
                map = L.map('map').setView([13.8893665, 120.9781897], 16); // Manila coordinates as example
            <?php endif; ?>
            
            // Add tile layer
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            <?php if (!empty($locations)): ?>
                // Add markers for location history
                const locations = <?php echo json_encode($locations); ?>;
                
                locations.forEach(function(location, index) {
                    const lat = parseFloat(location.latitude);
                    const lng = parseFloat(location.longitude);
                    
                    let markerColor = 'blue';
                    if (index === 0) {
                        markerColor = 'red'; // Current location
                    } else if (!location.inside_geofence) {
                        markerColor = 'orange'; // Outside safe zone
                    }
                    
                    const marker = L.marker([lat, lng]).addTo(map);
                    marker.bindPopup(`
                        <strong>${index === 0 ? 'Current Location' : 'Location History'}</strong><br>
                        Time: ${new Date(location.timestamp).toLocaleString()}<br>
                        Accuracy: ${location.accuracy}m<br>
                        Safe Zone: ${location.inside_geofence ? 'Inside' : 'Outside'}
                    `);
                    
                    markers.push(marker);
                });
                
                // Draw path if multiple locations
                if (locations.length > 1) {
                    const pathCoords = locations.map(loc => [parseFloat(loc.latitude), parseFloat(loc.longitude)]);
                    L.polyline(pathCoords, {color: 'blue', weight: 3, opacity: 0.7}).addTo(map);
                }
            <?php endif; ?>
        }
        
        function refreshLocation() {
            // In a real implementation, this would make an AJAX call to get updated location data
            location.reload();
        }
        
        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
        });
    </script>
</body>
</html>
