<?php
require_once 'config/config.php';
require_once 'includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$child_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Verify access permissions
if ($user_role === 'parent') {
    // Parents can only view their own children through parent_child relationship
    $stmt = $pdo->prepare("SELECT c.* FROM children c 
                          INNER JOIN parent_child pc ON c.id = pc.child_id 
                          WHERE c.id = ? AND pc.parent_id = ?");
    $stmt->execute([$child_id, $user_id]);
} else {
    // Teachers and admins can view all children
    $stmt = $pdo->prepare("SELECT * FROM children WHERE id = ?");
    $stmt->execute([$child_id]);
}

$child = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$child) {
    header('Location: children.php');
    exit();
}

// Get latest location
$stmt = $pdo->prepare("SELECT * FROM location_tracking 
                      WHERE child_id = ? 
                      ORDER BY timestamp DESC LIMIT 1");
$stmt->execute([$child_id]);
$latest_location = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent location history (last 24 hours)
$stmt = $pdo->prepare("SELECT * FROM location_tracking 
                      WHERE child_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                      ORDER BY timestamp DESC LIMIT 10");
$stmt->execute([$child_id]);
$recent_locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get emergency contact from children table
$emergency_contact = $child['emergency_contact'] ?? null;

// Get alerts for this child (last 7 days)
$stmt = $pdo->prepare("SELECT * FROM alerts 
                      WHERE child_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                      ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$child_id]);
$recent_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Child Profile - Child Tracking System</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <!-- Added Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" 
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" 
          crossorigin=""/>
</head>

<body>
    

<link rel="stylesheet" href="assets/css/main.css">

<div class="container">
    <div class="main-content">
        <!-- Modern profile header card -->
        <div class="card profile-card">
            <div class="profile-header">
                <div class="profile-avatar">
                    <?php if (!empty($child['photo'])): ?>
                        <img src="<?php echo htmlspecialchars($child['photo']); ?>" 
                            alt="<?php echo htmlspecialchars($child['first_name']); ?>" 
                            class="avatar-image">
                    <?php else: ?>
                        <div class="avatar-placeholder">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                    <div class="status-badge status-<?php echo $child['status']; ?>"></div>
                </div>

                <div class="profile-info">
                    <h1 class="profile-name">
                        <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                    </h1>

                    <div class="profile-meta">
                        <span class="badge badge-primary">LRN: <?php echo htmlspecialchars($child['lrn'] ?? 'Not assigned'); ?></span>
                        <span class="badge badge-info">Age: <?php echo calculateAge($child['date_of_birth']); ?></span>
                        <span class="badge badge-<?php echo $child['status'] === 'active' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($child['status']); ?>
                        </span>
                    </div>

                    <div class="profile-actions">
                        <a href="location_history.php?child_id=<?php echo $child['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-map-marker-alt"></i> Location History
                        </a>

                        <?php if ($user_role === 'admin'): ?>
                            <a href="edit_child.php?id=<?php echo $child['id']; ?>" class="btn btn-secondary">
                                <i class="fas fa-edit"></i> Edit Profile
                            </a>
                        <?php endif; ?>

                        <button onclick="refreshLocation()" class="btn btn-info">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>


        <!-- Current Location Card -->
        <div class="col-lg-12 col-md-12">
            <div class="card location-card">
                <div class="card-header">
                    <h3><i class="fas fa-map-marker-alt"></i> Current Location</h3>
                    <div class="location-status">
                        <?php if ($latest_location): ?>
                            <span class="badge badge-success">Last seen: <?php echo timeAgo($latest_location['timestamp']); ?></span>
                        <?php else: ?>
                            <span class="badge badge-warning">No location data</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($latest_location): ?>
                        <div class="location-grid">
                            <div class="location-item">
                                <div class="location-label">Coordinates</div>
                                <div class="location-value">
                                    <?php echo number_format($latest_location['latitude'], 6); ?>, 
                                    <?php echo number_format($latest_location['longitude'], 6); ?>
                                </div>
                            </div>
                            <div class="location-item">
                                <div class="location-label">Accuracy</div>
                                <div class="location-value">
                                    <?php echo $latest_location['accuracy']; ?>m
                                    <span class="accuracy-indicator <?php echo getAccuracyClass($latest_location['accuracy']); ?>"></span>
                                </div>
                            </div>
                            <div class="location-item">
                                <div class="location-label">Last Update</div>
                                <div class="location-value"><?php echo date('M j, Y g:i A', strtotime($latest_location['timestamp'])); ?></div>
                            </div>
                        </div>
                        
                        <!-- Map Container -->
                        <div class="map-container" id="map-container">
                            <div id="map" style="height: 600px; border-radius: 8px; margin: 20px 0;"></div>
                        </div>
                        
                        <div class="location-actions">
                            <button onclick="getDirections()" class="btn btn-primary">
                                <i class="fas fa-directions"></i> Get Directions
                            </button>
                            <button onclick="shareLocation()" class="btn btn-secondary">
                                <i class="fas fa-share"></i> Share Location
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="no-location">
                            <i class="fas fa-map-marker-slash"></i>
                            <h4>No Location Data Available</h4>
                            <p>This child's device hasn't reported location data yet.</p>
                            <button onclick="requestLocationUpdate()" class="btn btn-primary">
                                <i class="fas fa-sync"></i> Request Location Update
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Child Information Sidebar -->
        <div class="col-lg-4 col-md-12">
            <!-- Basic Information -->
            <div class="card info-card">
                <div class="card-header">
                    <h4><i class="fas fa-user"></i> Basic Information</h4>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Full Name</span>
                            <span class="info-value"><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Date of Birth</span>
                            <span class="info-value"><?php echo date('M j, Y', strtotime($child['date_of_birth'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Grade</span>
                            <span class="info-value"><?php echo htmlspecialchars($child['grade'] ?? 'Not specified'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Gender</span>
                            <span class="info-value"><?php echo htmlspecialchars($child['gender'] ?? 'Not specified'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">LRN</span>
                            <span class="info-value device-id"><?php echo htmlspecialchars($child['lrn'] ?? 'Not assigned'); ?></span>
                        </div>
                        <?php if (!empty($child['medical_info'])): ?>
                        <div class="info-item">
                            <span class="info-label">Medical Info</span>
                            <span class="info-value"><?php echo htmlspecialchars($child['medical_info']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Emergency Contacts -->
            <div class="card contacts-card">
                <div class="card-header">
                    <h4><i class="fas fa-phone"></i> Emergency Contact</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($emergency_contact)): ?>
                        <div class="contact-item">
                            <div class="contact-info">
                                <div class="contact-name">Emergency Contact</div>
                                <div class="contact-phone"><?php echo htmlspecialchars($emergency_contact); ?></div>
                            </div>
                            <div class="contact-actions">
                                <a href="tel:<?php echo htmlspecialchars($emergency_contact); ?>" class="btn btn-success btn-sm">
                                    <i class="fas fa-phone"></i> Call
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No emergency contact configured.</p>
                        <?php if ($user_role === 'admin'): ?>
                            <a href="edit_child.php?id=<?php echo $child['id']; ?>" class="btn btn-primary btn-sm">
                                Add Contact
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Alerts -->
            <div class="card alerts-card">
                <div class="card-header">
                    <h4><i class="fas fa-exclamation-triangle"></i> Recent Alerts</h4>
                </div>
                <div class="card-body">
                    <?php if ($recent_alerts): ?>
                        <div class="alerts-list">
                            <?php foreach ($recent_alerts as $alert): ?>
                                <div class="alert-item alert-<?php echo $alert['alert_type'] ?? 'info'; ?>">
                                    <div class="alert-icon">
                                        <i class="fas fa-<?php echo getAlertIcon($alert['alert_type'] ?? 'info'); ?>"></i>
                                    </div>
                                    <div class="alert-content">
                                        <div class="alert-message"><?php echo htmlspecialchars($alert['message'] ?? 'No message'); ?></div>
                                        <div class="alert-time"><?php echo timeAgo($alert['created_at'] ?? date('Y-m-d H:i:s')); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <a href="alerts.php?child_id=<?php echo $child['id']; ?>" class="btn btn-outline-primary btn-sm">
                            View All Alerts
                        </a>
                    <?php else: ?>
                        <p class="text-muted">No recent alerts.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Location History -->
    <div class="row">
        <div class="col-12">
            <div class="card history-card">
                <div class="card-header">
                    <h4><i class="fas fa-history"></i> Recent Location History</h4>
                    <a href="location_history.php?child_id=<?php echo $child['id']; ?>" class="btn btn-primary btn-sm">
                        View Full History
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($recent_locations): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Location</th>
                                        <th>Accuracy</th>
                                        <th>Geofence Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_locations as $location): ?>
                                        <tr>
                                            <td><?php echo date('M j, g:i A', strtotime($location['timestamp'])); ?></td>
                                            <td>
                                                <div class="location-coords">
                                                    <?php echo number_format($location['latitude'], 4); ?>,
                                                    <?php echo number_format($location['longitude'], 4); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="accuracy-badge <?php echo getAccuracyClass($location['accuracy']); ?>">
                                                    <?php echo $location['accuracy']; ?>m
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-<?php echo $location['inside_geofence'] ? 'success' : 'warning'; ?>">
                                                    <?php echo $location['inside_geofence'] ? 'Inside' : 'Outside'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button onclick="showOnMap(<?php echo $location['latitude']; ?>, <?php echo $location['longitude']; ?>)" 
                                                        class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-map"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-map-marker-slash"></i>
                            <h5>No Location History</h5>
                            <p>No location data available for the last 24 hours.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let map;
let marker;

function initMap() {
    <?php if ($latest_location): ?>
        const childLat = <?php echo $latest_location['latitude']; ?>;
        const childLng = <?php echo $latest_location['longitude']; ?>;
        
        // Initialize Leaflet map
        map = L.map('map').setView([childLat, childLng], 15);
        
        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);
        
        // Create custom icon for child marker
        const childIcon = L.divIcon({
            className: 'child-marker',
            html: '<div class="marker-pin"><i class="fas fa-child"></i></div>',
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        });
        
        // Add marker for child's location
        marker = L.marker([childLat, childLng], { icon: childIcon }).addTo(map);
        
        // Add popup with child information
        const popupContent = `
            <div class="map-info-window">
                <h5><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></h5>
                <p><strong>Last Update:</strong> <?php echo date('M j, Y g:i A', strtotime($latest_location['timestamp'])); ?></p>
                <p><strong>Accuracy:</strong> <?php echo $latest_location['accuracy']; ?>m</p>
                <p><strong>Coordinates:</strong> ${childLat.toFixed(6)}, ${childLng.toFixed(6)}</p>
            </div>
        `;
        
        marker.bindPopup(popupContent);
        
        // Add accuracy circle
        const accuracyCircle = L.circle([childLat, childLng], {
            color: '#007bff',
            fillColor: '#007bff',
            fillOpacity: 0.1,
            radius: <?php echo $latest_location['accuracy']; ?>
        }).addTo(map);
        
    <?php else: ?>
        // Show default map if no location (Philippines center)
        map = L.map('map').setView([12.8797, 121.7740], 6);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        
        // Add message overlay
        const noLocationDiv = L.divIcon({
            className: 'no-location-marker',
            html: '<div class="no-location-message">No location data available</div>',
            iconSize: [200, 50]
        });
        
        L.marker([12.8797, 121.7740], { icon: noLocationDiv }).addTo(map);
    <?php endif; ?>
}

function refreshLocation() {
    const childId = <?php echo $child['id']; ?>;
    
    fetch(`api/request_location_update.php?child_id=${childId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Location update requested successfully', 'success');
                setTimeout(() => {
                    location.reload();
                }, 3000);
            } else {
                showAlert('Failed to request location update: ' + data.error, 'danger');
            }
        })
        .catch(error => {
            showAlert('Network error: ' + error.message, 'danger');
        });
}

function showOnMap(lat, lng) {
    if (map) {
        const position = [parseFloat(lat), parseFloat(lng)];
        map.setView(position, 16);
        
        if (marker) {
            marker.setLatLng(position);
            marker.openPopup();
        }
    }
}

function getDirections() {
    <?php if ($latest_location): ?>
        const destination = `<?php echo $latest_location['latitude']; ?>,<?php echo $latest_location['longitude']; ?>`;
        const url = `https://www.google.com/maps/dir/?api=1&destination=${destination}`;
        window.open(url, '_blank');
    <?php endif; ?>
}

function shareLocation() {
    <?php if ($latest_location): ?>
        const locationText = `<?php echo htmlspecialchars($child['first_name']); ?>'s location: https://maps.google.com/?q=<?php echo $latest_location['latitude']; ?>,<?php echo $latest_location['longitude']; ?>`;
        
        if (navigator.share) {
            navigator.share({
                title: 'Child Location',
                text: locationText
            });
        } else {
            navigator.clipboard.writeText(locationText).then(() => {
                showAlert('Location copied to clipboard', 'success');
            });
        }
    <?php endif; ?>
}

function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
    `;
    
    document.querySelector('.container').insertBefore(alertDiv, document.querySelector('.main-content'));
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

document.addEventListener('DOMContentLoaded', function() {
    initMap();
});
</script>

<!-- Replaced Google Maps script with Leaflet -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" 
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" 
        crossorigin=""></script>

<?php
// Helper functions
function calculateAge($birthdate) {
    $today = new DateTime();
    $birth = new DateTime($birthdate);
    return $today->diff($birth)->y;
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    return floor($time/86400) . ' days ago';
}

function getAccuracyClass($accuracy) {
    if ($accuracy <= 5) return 'excellent';
    if ($accuracy <= 15) return 'good';
    if ($accuracy <= 50) return 'fair';
    return 'poor';
}

function getAlertIcon($type) {
    switch ($type) {
        case 'geofence_exit': return 'map-marker-alt';
        case 'low_battery': return 'battery-quarter';
        case 'missing': return 'exclamation-triangle';
        case 'emergency': return 'exclamation-circle';
        default: return 'info-circle';
    }
}

require_once 'includes/footer.php';
?>
</body>
</html>
