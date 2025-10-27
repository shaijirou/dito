<?php
require_once '../config/config.php';

header('Content-Type: application/json');

// Allow only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Parse JSON input
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
    // Get active child record
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
    error_log("[GEOFENCE] Processing location for child_id: $child_id (LRN: $lrn) - Coords: $latitude, $longitude");

    // Get active geofence
    $stmt = $pdo->query("SELECT * FROM geofences WHERE status = 'active' ORDER BY id LIMIT 1");
    $geofence = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$geofence) {
        error_log("[GEOFENCE] WARNING: No active geofence found!");
        $inside_geofence = true; // Assume safe if no geofence
    } else {
        $distance = calculateDistance($latitude, $longitude, $geofence['center_lat'], $geofence['center_lng']);
        $inside_geofence = $distance <= $geofence['radius'];
        error_log("[GEOFENCE] Geofence distance: {$distance}m / Radius: {$geofence['radius']}m -> Inside: " . ($inside_geofence ? 'YES' : 'NO'));
    }

    // Save new location record
    $stmt = $pdo->prepare("INSERT INTO location_tracking (child_id, latitude, longitude, accuracy, inside_geofence) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$child_id, $latitude, $longitude, $accuracy, $inside_geofence]);
    error_log("[GEOFENCE] Location record inserted");

    // === TIME & DAY FILTER ===
    $current_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $current_hour = (int)$current_time->format('H');
    $current_minute = (int)$current_time->format('i');
    $day_of_week = (int)$current_time->format('N'); // 1=Mon, 7=Sun

    $current_time_minutes = $current_hour * 60 + $current_minute;
    $seven_am_minutes = 7 * 60;
    $five_pm_minutes = 17 * 60;
    $is_weekday = $day_of_week >= 1 && $day_of_week <= 5;
    $is_school_hours = $is_weekday && ($current_time_minutes >= $seven_am_minutes && $current_time_minutes < $five_pm_minutes);

    error_log("[GEOFENCE] Time check: " . $current_time->format('D H:i:s') . " | School hours: " . ($is_school_hours ? 'YES' : 'NO'));

    // Alert if outside geofence and during school hours (with 1-hour cooldown to prevent spam)
    if (!$inside_geofence && $is_school_hours) {
        // Check for recent alert within the last hour
        $one_hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
        $stmt = $pdo->prepare("SELECT id FROM alerts WHERE child_id = ? AND alert_type = 'geofence_exit' AND created_at > ?");
        $stmt->execute([$child_id, $one_hour_ago]);
        $recent_alert = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($recent_alert) {
            error_log("[GEOFENCE] Recent alert exists within last hour. Skipping.");
            echo json_encode([
                'success' => true,
                'message' => 'Location updated (alert skipped due to recent alert)',
                'inside_geofence' => $inside_geofence,
                'alert_skipped' => true
            ]);
            exit();
        }

        $stmt = $pdo->prepare("SELECT first_name, last_name FROM children WHERE id = ?");
        $stmt->execute([$child_id]);
        $child_info = $stmt->fetch(PDO::FETCH_ASSOC);
        $alert_message = "GEOFENCE ALERT: {$child_info['first_name']} {$child_info['last_name']} has left the school safe zone.";

        $recipients = [];
        $stmt = $pdo->prepare("SELECT u.id, u.phone, u.full_name FROM users u 
            JOIN parent_child pc ON u.id = pc.parent_id 
            WHERE pc.child_id = ? AND u.status = 'active'");
        $stmt->execute([$child_id]);
        $parents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $recipients = array_merge($recipients, $parents);

        $stmt = $pdo->prepare("SELECT u.id, u.phone, u.full_name FROM users u 
            JOIN teacher_child tc ON u.id = tc.teacher_id 
            WHERE tc.child_id = ? AND u.status = 'active'");
        $stmt->execute([$child_id]);
        $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $recipients = array_merge($recipients, $teachers);

        // Get admins
        $stmt = $pdo->query("SELECT id, phone, full_name FROM users WHERE role = 'admin' AND status = 'active'");
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $recipients = array_merge($recipients, $admins);

        $recipient_ids = array_column($recipients, 'id');
        error_log("[GEOFENCE] Total recipients: " . count($recipient_ids));

        // Insert alert
        $stmt = $pdo->prepare("INSERT INTO alerts (child_id, alert_type, message, severity, sent_to, status) VALUES (?, 'geofence_exit', ?, 'warning', ?, 'sent')");
        $stmt->execute([$child_id, $alert_message, json_encode($recipient_ids)]);
        error_log("[GEOFENCE] Alert record created successfully");

        $sms_count = 0;
        foreach ($recipients as $r) {
            if (!empty($r['phone'])) {
                if (sendSMSViaSemaphore($r['phone'], $alert_message)) {
                    $sms_count++;
                }
            }
        }

        error_log("[GEOFENCE] SMS sent to {$sms_count} recipient(s) via Semaphore");

        echo json_encode([
            'success' => true,
            'message' => 'Location updated, alert sent',
            'inside_geofence' => $inside_geofence,
            'alert_sent' => true,
            'recipients_count' => count($recipient_ids),
            'sms_sent_count' => $sms_count
        ]);
    } else {
        // No alert needed
        $reason = !$inside_geofence ? 'outside geofence but not school hours' : 'inside geofence';
        error_log("[GEOFENCE] No alert - $reason");
        echo json_encode([
            'success' => true,
            'message' => 'Location updated successfully',
            'inside_geofence' => $inside_geofence
        ]);
    }

} catch (PDOException $e) {
    error_log("[GEOFENCE] DB Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}

// ===== Helper Function =====
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371000; // meters
    $lat1_rad = deg2rad($lat1);
    $lon1_rad = deg2rad($lon1);
    $lat2_rad = deg2rad($lat2);
    $lon2_rad = deg2rad($lon2);
    $dlat = $lat2_rad - $lat1_rad;
    $dlon = $lon2_rad - $lon1_rad;
    $a = sin($dlat / 2) ** 2 + cos($lat1_rad) * cos($lat2_rad) * sin($dlon / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earth_radius * $c;
}
?>
