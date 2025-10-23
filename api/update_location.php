<?php
require_once '../config/config.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['lrn', 'latitude', 'longitude'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit();
    }
}

$lrn = sanitizeInput($input['lrn']);
$latitude = (float)$input['latitude'];
$longitude = (float)$input['longitude'];
$accuracy = isset($input['accuracy']) ? (float)$input['accuracy'] : null;

try {
    $stmt = $pdo->prepare("SELECT id FROM children WHERE lrn = ? AND status = 'active'");
    $stmt->execute([$lrn]);
    $child = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$child) {
        http_response_code(404);
        error_log("[GEOFENCE] Child not found with LRN: $lrn");
        echo json_encode(['error' => 'Device not found']);
        exit();
    }
    
    $child_id = $child['id'];
    error_log("[GEOFENCE] Processing location for child_id: $child_id, LRN: $lrn, Coords: $latitude, $longitude");
    
    // Get the LAST location status BEFORE inserting the new one
    $stmt = $pdo->prepare("SELECT inside_geofence FROM location_tracking WHERE child_id = ? ORDER BY timestamp DESC LIMIT 1");
    $stmt->execute([$child_id]);
    $last_location = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log("[GEOFENCE] Last location status: " . ($last_location ? ($last_location['inside_geofence'] ? 'INSIDE' : 'OUTSIDE') : 'NO PREVIOUS LOCATION'));
    
    // Get active geofence
    $stmt = $pdo->query("SELECT * FROM geofences WHERE status = 'active' ORDER BY id LIMIT 1");
    $geofence = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$geofence) {
        error_log("[GEOFENCE] WARNING: No active geofence found in database!");
        $inside_geofence = true; // Default to inside if no geofence exists
    } else {
        // Calculate distance from geofence center
        $distance = calculateDistance($latitude, $longitude, $geofence['center_lat'], $geofence['center_lng']);
        $inside_geofence = $distance <= $geofence['radius'];
        error_log("[GEOFENCE] Geofence check - Distance: {$distance}m, Radius: {$geofence['radius']}m, Inside: " . ($inside_geofence ? 'YES' : 'NO'));
    }
    
    // Insert location record FIRST (before alert checking)
    $stmt = $pdo->prepare("INSERT INTO location_tracking (child_id, latitude, longitude, accuracy, inside_geofence) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$child_id, $latitude, $longitude, $accuracy, $inside_geofence]);
    error_log("[GEOFENCE] Location record inserted");
    
    // NOW check if we need to send an alert (child just left the safe zone)
    if ($last_location && $last_location['inside_geofence'] && !$inside_geofence) {
        error_log("[GEOFENCE] ALERT TRIGGERED - Child left safe zone!");
        
        // Check if alert already exists for today or if case is resolved
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT a.id FROM alerts a
            LEFT JOIN missing_cases mc ON a.case_id = mc.id
            WHERE a.child_id = ? 
            AND a.alert_type = 'geofence_exit'
            AND DATE(a.created_at) = ?
            AND (mc.status IS NULL OR mc.status != 'resolved')
        ");
        $stmt->execute([$child_id, $today]);
        $existing_alert = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_alert) {
            error_log("[GEOFENCE] Alert already exists for today. Skipping duplicate alert.");
            echo json_encode([
                'success' => true,
                'message' => 'Location updated successfully (alert already sent today)',
                'inside_geofence' => $inside_geofence,
                'alert_skipped' => true
            ]);
        } else {
            // Child just left the safe zone - create alert
            $stmt = $pdo->prepare("SELECT first_name, last_name FROM children WHERE id = ?");
            $stmt->execute([$child_id]);
            $child_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $alert_message = "GEOFENCE ALERT: " . $child_info['first_name'] . " " . $child_info['last_name'] . " has left the safe zone.";
            
            // Get current time to determine alert recipients
            $current_time = new DateTime('now');
            $current_hour = (int)$current_time->format('H');
            $current_minute = (int)$current_time->format('i');
            $current_time_minutes = $current_hour * 60 + $current_minute;
            
            $seven_am_minutes = 7 * 60;      // 7:00 AM in minutes
            $five_pm_minutes = 17 * 60;      // 5:00 PM in minutes
            
            // Check if current time is within school hours (7 AM - 5 PM)
            $is_school_hours = $current_time_minutes >= $seven_am_minutes && $current_time_minutes < $five_pm_minutes;
            
            error_log("[GEOFENCE] Current time: " . $current_time->format('H:i:s') . ", School hours: " . ($is_school_hours ? 'YES' : 'NO'));
            
            // Get parents (always notified)
            $stmt = $pdo->prepare("SELECT u.id, u.phone FROM users u 
                                  JOIN parent_child pc ON u.id = pc.parent_id 
                                  WHERE pc.child_id = ? AND u.status = 'active'");
            $stmt->execute([$child_id]);
            $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("[GEOFENCE] Found " . count($parents) . " parent(s)");
            
            // Get teachers (only during school hours)
            $teachers = [];
            if ($is_school_hours) {
                $stmt = $pdo->prepare("SELECT u.id, u.phone FROM users u 
                                      JOIN teacher_child tc ON u.id = tc.teacher_id 
                                      WHERE tc.child_id = ? AND u.status = 'active'");
                $stmt->execute([$child_id]);
                $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("[GEOFENCE] Found " . count($teachers) . " teacher(s)");
            }
            
            // Get admin (only during school hours)
            $admin = [];
            if ($is_school_hours) {
                $stmt = $pdo->query("SELECT id, phone FROM users WHERE role = 'admin' AND status = 'active'");
                $admin = $stmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("[GEOFENCE] Found " . count($admin) . " admin(s)");
            }
            
            // Combine all recipients based on time
            $recipients = array_merge($parents, $teachers, $admin);
            $recipient_ids = array_column($recipients, 'id');
            
            error_log("[GEOFENCE] Total recipients: " . count($recipient_ids) . " - IDs: " . implode(',', $recipient_ids));
            
            // Insert alert
            $stmt = $pdo->prepare("INSERT INTO alerts (child_id, alert_type, message, severity, sent_to, status) VALUES (?, 'geofence_exit', ?, 'warning', ?, 'sent')");
            $result = $stmt->execute([$child_id, $alert_message, json_encode($recipient_ids)]);
            error_log("[GEOFENCE] Alert inserted: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            // Send SMS alerts to all recipients
            $sms_count = 0;
            foreach ($recipients as $recipient) {
                if (!empty($recipient['phone'])) {
                    $stmt = $pdo->prepare("INSERT INTO sms_logs (phone_number, message, status) VALUES (?, ?, 'pending')");
                    $stmt->execute([$recipient['phone'], $alert_message]);
                    $sms_count++;
                }
            }
            error_log("[GEOFENCE] SMS queued for " . $sms_count . " recipient(s)");
            
            echo json_encode([
                'success' => true,
                'message' => 'Location updated successfully and alert sent',
                'inside_geofence' => $inside_geofence,
                'alert_sent' => true,
                'recipients_count' => count($recipient_ids)
            ]);
        }
    } else {
        // No alert needed
        error_log("[GEOFENCE] No alert needed - Status: " . ($inside_geofence ? 'INSIDE' : 'OUTSIDE'));
        echo json_encode([
            'success' => true,
            'message' => 'Location updated successfully',
            'inside_geofence' => $inside_geofence
        ]);
    }
    
} catch (PDOException $e) {
    error_log("[GEOFENCE] Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}

// Function to calculate distance between two points
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371000; // Earth radius in meters
    
    $lat1_rad = deg2rad($lat1);
    $lon1_rad = deg2rad($lon1);
    $lat2_rad = deg2rad($lat2);
    $lon2_rad = deg2rad($lon2);
    
    $delta_lat = $lat2_rad - $lat1_rad;
    $delta_lon = $lon2_rad - $lon1_rad;
    
    $a = sin($delta_lat / 2) * sin($delta_lat / 2) + cos($lat1_rad) * cos($lat2_rad) * sin($delta_lon / 2) * sin($delta_lon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earth_radius * $c;
}
?>