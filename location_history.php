<?php
require_once 'config/config.php';
require_once 'includes/header.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$child_id = isset($_GET['child_id']) ? (int)$_GET['child_id'] : 0;
$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Date filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Verify access permissions
if ($user_role === 'parent') {
    // Parents can only view their own children
    $stmt = $pdo->prepare("SELECT * FROM children WHERE id = ? AND parent_id = ?");
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

// Get location history with pagination
$stmt = $pdo->prepare("SELECT * FROM location_tracking 
                      WHERE child_id = ? 
                      AND DATE(timestamp) BETWEEN ? AND ?
                      ORDER BY timestamp DESC 
                      LIMIT ? OFFSET ?");
$stmt->execute([$child_id, $start_date, $end_date, $limit, $offset]);
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) FROM location_tracking 
                      WHERE child_id = ? 
                      AND DATE(timestamp) BETWEEN ? AND ?");
$stmt->execute([$child_id, $start_date, $end_date]);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Get statistics
$stmt = $pdo->prepare("SELECT 
                        COUNT(*) as total_locations,
                        MIN(timestamp) as first_location,
                        MAX(timestamp) as last_location,
                        AVG(accuracy) as avg_accuracy
                      FROM location_tracking 
                      WHERE child_id = ? 
                      AND DATE(timestamp) BETWEEN ? AND ?");
$stmt->execute([$child_id, $start_date, $end_date]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
   <?php include 'includes/header.php'; ?>
<link rel="stylesheet" href="assets/css/main.css">

<div class="container-fluid">
     Page Header 
    <div class="row">
        <div class="col-12">
            <div class="page-header">
                <div class="page-title">
                    <h1><i class="fas fa-history"></i> Location History</h1>
                    <p>Tracking history for <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></p>
                </div>
                <div class="page-actions">
                    <a href="child_profile.php?id=<?php echo $child['id']; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Profile
                    </a>
                    <button onclick="exportData()" class="btn btn-primary">
                        <i class="fas fa-download"></i> Export Data
                    </button>
                </div>
            </div>
        </div>
    </div>

     Statistics Cards 
    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <div class="stats-number"><?php echo number_format($stats['total_locations']); ?></div>
                <div class="stats-label">Total Locations</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stats-number">
                    <?php echo $stats['first_location'] ? timeAgo($stats['first_location']) : 'N/A'; ?>
                </div>
                <div class="stats-label">First Location</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="fas fa-crosshairs"></i>
                </div>
                <div class="stats-number">
                    <?php echo $stats['avg_accuracy'] ? number_format($stats['avg_accuracy'], 1) . 'm' : 'N/A'; ?>
                </div>
                <div class="stats-label">Avg Accuracy</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stats-card">
                <div class="stats-icon">
                    <i class="fas fa-calendar"></i>
                </div>
                <div class="stats-number">
                    <?php echo (strtotime($end_date) - strtotime($start_date)) / 86400 + 1; ?>
                </div>
                <div class="stats-label">Days Selected</div>
            </div>
        </div>
    </div>

     Filters 
    <div class="row">
        <div class="col-12">
            <div class="card filter-card">
                <div class="card-body">
                    <form method="GET" class="filter-form">
                        <input type="hidden" name="child_id" value="<?php echo $child_id; ?>">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="start_date" class="form-label">Start Date</label>
                                    <input type="date" id="start_date" name="start_date" 
                                           value="<?php echo $start_date; ?>" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="end_date" class="form-label">End Date</label>
                                    <input type="date" id="end_date" name="end_date" 
                                           value="<?php echo $end_date; ?>" class="form-control">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="form-label">Quick Filters</label>
                                    <div class="quick-filters">
                                        <button type="button" onclick="setDateRange('today')" class="btn btn-outline-primary btn-sm">Today</button>
                                        <button type="button" onclick="setDateRange('week')" class="btn btn-outline-primary btn-sm">This Week</button>
                                        <button type="button" onclick="setDateRange('month')" class="btn btn-outline-primary btn-sm">This Month</button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-filter"></i> Apply Filters
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

     Map and Timeline 
    <div class="row">
        <div class="col-lg-8">
            <div class="card map-card">
                <div class="card-header">
                    <h4><i class="fas fa-map"></i> Location Map</h4>
                    <div class="map-controls">
                        <button onclick="toggleHeatmap()" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-fire"></i> Heatmap
                        </button>
                        <button onclick="showPath()" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-route"></i> Show Path
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="history-map" style="height: 500px; border-radius: 8px;"></div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card timeline-card">
                <div class="card-header">
                    <h4><i class="fas fa-timeline"></i> Timeline</h4>
                </div>
                <div class="card-body">
                    <div class="timeline-container">
                        <?php if ($locations): ?>
                            <?php foreach (array_slice($locations, 0, 10) as $index => $location): ?>
                                <div class="timeline-item" onclick="focusLocation(<?php echo $location['latitude']; ?>, <?php echo $location['longitude']; ?>)">
                                    <div class="timeline-marker">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="timeline-time">
                                            <?php echo date('g:i A', strtotime($location['timestamp'])); ?>
                                        </div>
                                        <div class="timeline-location">
                                            <?php echo number_format($location['latitude'], 4); ?>,
                                            <?php echo number_format($location['longitude'], 4); ?>
                                        </div>
                                        <div class="timeline-accuracy">
                                            Accuracy: <?php echo $location['accuracy']; ?>m
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-timeline">
                                <i class="fas fa-clock"></i>
                                <p>No location data for selected period</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

     Location History Table 
    <div class="row">
        <div class="col-12">
            <div class="card table-card">
                <div class="card-header">
                    <h4><i class="fas fa-table"></i> Detailed History</h4>
                    <div class="table-actions">
                        <button onclick="selectAll()" class="btn btn-outline-secondary btn-sm">Select All</button>
                        <button onclick="exportSelected()" class="btn btn-outline-primary btn-sm">Export Selected</button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($locations): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th><input type="checkbox" id="select-all"></th>
                                        <th>Date & Time</th>
                                        <th>Coordinates</th>
                                        <th>Accuracy</th>
                                        <th>Battery</th>
                                        <th>Speed</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($locations as $location): ?>
                                        <tr>
                                            <td><input type="checkbox" class="location-checkbox" value="<?php echo $location['id']; ?>"></td>
                                            <td>
                                                <div class="datetime-display">
                                                    <div class="date"><?php echo date('M j, Y', strtotime($location['timestamp'])); ?></div>
                                                    <div class="time"><?php echo date('g:i:s A', strtotime($location['timestamp'])); ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="coordinates">
                                                    <div class="lat">Lat: <?php echo number_format($location['latitude'], 6); ?></div>
                                                    <div class="lng">Lng: <?php echo number_format($location['longitude'], 6); ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="accuracy-badge <?php echo getAccuracyClass($location['accuracy']); ?>">
                                                    <?php echo $location['accuracy']; ?>m
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($location['battery_level']): ?>
                                                    <div class="battery-display">
                                                        <div class="battery-mini">
                                                            <div class="battery-level" style="width: <?php echo $location['battery_level']; ?>%"></div>
                                                        </div>
                                                        <?php echo $location['battery_level']; ?>%
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (isset($location['speed'])): ?>
                                                    <?php echo number_format($location['speed'], 1); ?> km/h
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button onclick="showLocationOnMap(<?php echo $location['latitude']; ?>, <?php echo $location['longitude']; ?>)" 
                                                            class="btn btn-primary btn-sm" title="Show on Map">
                                                        <i class="fas fa-map"></i>
                                                    </button>
                                                    <button onclick="getLocationDetails(<?php echo $location['id']; ?>)" 
                                                            class="btn btn-info btn-sm" title="Details">
                                                        <i class="fas fa-info"></i>
                                                    </button>
                                                    <button onclick="shareLocation(<?php echo $location['latitude']; ?>, <?php echo $location['longitude']; ?>)" 
                                                            class="btn btn-secondary btn-sm" title="Share">
                                                        <i class="fas fa-share"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                         Pagination 
                        <?php if ($total_pages > 1): ?>
                            <nav class="pagination-nav">
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?child_id=<?php echo $child_id; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&page=<?php echo $page-1; ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?child_id=<?php echo $child_id; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&page=<?php echo $i; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?child_id=<?php echo $child_id; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&page=<?php echo $page+1; ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-map-marker-slash"></i>
                            <h4>No Location Data Found</h4>
                            <p>No location data available for the selected date range.</p>
                            <button onclick="requestLocationUpdate()" class="btn btn-primary">
                                <i class="fas fa-sync"></i> Request Location Update
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

 Location Details Modal 
<div class="modal fade" id="locationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Location Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="location-details">
                 Details will be loaded here 
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
let historyMap;
let markers = [];
let heatmap;
let pathPolyline;

function initHistoryMap() {
    const mapCenter = <?php echo $locations ? '{lat: ' . $locations[0]['latitude'] . ', lng: ' . $locations[0]['longitude'] . '}' : '{lat: 40.7128, lng: -74.0060}'; ?>;
    
    historyMap = new google.maps.Map(document.getElementById('history-map'), {
        zoom: 13,
        center: mapCenter,
        styles: [
            {
                featureType: 'poi',
                elementType: 'labels',
                stylers: [{ visibility: 'off' }]
            }
        ]
    });
    
    // Add all location markers
    <?php if ($locations): ?>
        const locations = <?php echo json_encode($locations); ?>;
        
        locations.forEach((location, index) => {
            const marker = new google.maps.Marker({
                position: { lat: parseFloat(location.latitude), lng: parseFloat(location.longitude) },
                map: historyMap,
                title: `Location ${index + 1}`,
                icon: {
                    url: index === 0 ? '/placeholder.svg?height=30&width=30' : '/placeholder.svg?height=20&width=20',
                    scaledSize: new google.maps.Size(index === 0 ? 30 : 20, index === 0 ? 30 : 20)
                }
            });
            
            const infoWindow = new google.maps.InfoWindow({
                content: `
                    <div class="map-info-window">
                        <h6>Location Update</h6>
                        <p><strong>Time:</strong> ${new Date(location.timestamp).toLocaleString()}</p>
                        <p><strong>Accuracy:</strong> ${location.accuracy}m</p>
                        ${location.battery_level ? `<p><strong>Battery:</strong> ${location.battery_level}%</p>` : ''}
                    </div>
                `
            });
            
            marker.addListener('click', () => {
                infoWindow.open(historyMap, marker);
            });
            
            markers.push(marker);
        });
        
        // Fit map to show all markers
        if (markers.length > 1) {
            const bounds = new google.maps.LatLngBounds();
            markers.forEach(marker => bounds.extend(marker.getPosition()));
            historyMap.fitBounds(bounds);
        }
    <?php endif; ?>
}

function showLocationOnMap(lat, lng) {
    if (historyMap) {
        const position = { lat: parseFloat(lat), lng: parseFloat(lng) };
        historyMap.setCenter(position);
        historyMap.setZoom(16);
    }
}

function focusLocation(lat, lng) {
    showLocationOnMap(lat, lng);
}

function toggleHeatmap() {
    if (!heatmap) {
        const heatmapData = markers.map(marker => marker.getPosition());
        
        heatmap = new google.maps.visualization.HeatmapLayer({
            data: heatmapData,
            map: historyMap
        });
    } else {
        heatmap.setMap(heatmap.getMap() ? null : historyMap);
    }
}

function showPath() {
    if (pathPolyline) {
        pathPolyline.setMap(pathPolyline.getMap() ? null : historyMap);
        return;
    }
    
    const path = markers.map(marker => marker.getPosition()).reverse();
    
    pathPolyline = new google.maps.Polyline({
        path: path,
        geodesic: true,
        strokeColor: '#667eea',
        strokeOpacity: 1.0,
        strokeWeight: 3
    });
    
    pathPolyline.setMap(historyMap);
}

function setDateRange(range) {
    const today = new Date();
    let startDate, endDate;
    
    switch (range) {
        case 'today':
            startDate = endDate = today.toISOString().split('T')[0];
            break;
        case 'week':
            startDate = new Date(today.setDate(today.getDate() - 7)).toISOString().split('T')[0];
            endDate = new Date().toISOString().split('T')[0];
            break;
        case 'month':
            startDate = new Date(today.setDate(today.getDate() - 30)).toISOString().split('T')[0];
            endDate = new Date().toISOString().split('T')[0];
            break;
    }
    
    document.getElementById('start_date').value = startDate;
    document.getElementById('end_date').value = endDate;
}

function getLocationDetails(locationId) {
    fetch(`api/get_location_details.php?id=${locationId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('location-details').innerHTML = `
                    <div class="location-details-grid">
                        <div class="detail-item">
                            <strong>Timestamp:</strong> ${new Date(data.location.timestamp).toLocaleString()}
                        </div>
                        <div class="detail-item">
                            <strong>Coordinates:</strong> ${data.location.latitude}, ${data.location.longitude}
                        </div>
                        <div class="detail-item">
                            <strong>Accuracy:</strong> ${data.location.accuracy}m
                        </div>
                        <div class="detail-item">
                            <strong>Altitude:</strong> ${data.location.altitude || 'N/A'}m
                        </div>
                        <div class="detail-item">
                            <strong>Speed:</strong> ${data.location.speed || 'N/A'} km/h
                        </div>
                        <div class="detail-item">
                            <strong>Battery:</strong> ${data.location.battery_level || 'N/A'}%
                        </div>
                        <div class="detail-item">
                            <strong>Address:</strong> <span id="reverse-geocode">Loading...</span>
                        </div>
                    </div>
                `;
                
                // Show modal
                new bootstrap.Modal(document.getElementById('locationModal')).show();
                
                // Reverse geocode
                reverseGeocode(data.location.latitude, data.location.longitude);
            }
        });
}

function reverseGeocode(lat, lng) {
    const geocoder = new google.maps.Geocoder();
    const latlng = { lat: parseFloat(lat), lng: parseFloat(lng) };
    
    geocoder.geocode({ location: latlng }, (results, status) => {
        if (status === 'OK' && results[0]) {
            document.getElementById('reverse-geocode').textContent = results[0].formatted_address;
        } else {
            document.getElementById('reverse-geocode').textContent = 'Address not found';
        }
    });
}

function exportData() {
    const childId = <?php echo $child_id; ?>;
    const startDate = '<?php echo $start_date; ?>';
    const endDate = '<?php echo $end_date; ?>';
    
    window.open(`api/export_location_data.php?child_id=${childId}&start_date=${startDate}&end_date=${endDate}`, '_blank');
}

function selectAll() {
    const checkboxes = document.querySelectorAll('.location-checkbox');
    const selectAllCheckbox = document.getElementById('select-all');
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
}

function exportSelected() {
    const selected = Array.from(document.querySelectorAll('.location-checkbox:checked'))
                         .map(cb => cb.value);
    
    if (selected.length === 0) {
        alert('Please select locations to export');
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'api/export_selected_locations.php';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'location_ids';
    input.value = JSON.stringify(selected);
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

function requestLocationUpdate() {
    const childId = <?php echo $child_id; ?>;
    
    fetch(`api/request_location_update.php?child_id=${childId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Location update requested successfully', 'success');
            } else {
                showAlert('Failed to request location update', 'danger');
            }
        });
}

function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
    `;
    
    document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector('.row'));
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Initialize map when page loads
document.addEventListener('DOMContentLoaded', function() {
    if (typeof google !== 'undefined') {
        initHistoryMap();
    }
    
    // Handle select all checkbox
    document.getElementById('select-all').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.location-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });
});
</script>

<script async defer src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&libraries=visualization&callback=initHistoryMap"></script>

<?php
require_once 'includes/footer.php';
?>
