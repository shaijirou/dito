<?php
/**
 * Auto-assign relationships for newly added children
 * 
 * This script automatically:
 * 1. Assigns children to parents if child's parent_name matches user's full_name
 * 2. Assigns children to teachers if child's grade matches user's class_name
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Automatically assign a child to matching parents and teachers
 * 
 * @param int $child_id The ID of the newly added child
 * @param PDO $pdo Database connection
 * @return array Result with status and messages
 */
function autoAssignRelationships($child_id, $pdo) {
    $result = [
        'success' => true,
        'parent_assignments' => 0,
        'teacher_assignments' => 0,
        'errors' => []
    ];
    
    try {
        // Get child information
        $stmt = $pdo->prepare("SELECT parent_name, grade FROM children WHERE id = ? AND status = 'active'");
        $stmt->execute([$child_id]);
        $child = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$child) {
            $result['success'] = false;
            $result['errors'][] = 'Child not found or inactive';
            return $result;
        }
        
        if (!empty($child['parent_name'])) {
            try {
                // Find parent users with matching full_name
                $stmt = $pdo->prepare("
                    SELECT id FROM users 
                    WHERE role = 'parent' 
                    AND status = 'active' 
                    AND full_name = ? 
                    LIMIT 1
                ");
                $stmt->execute([$child['parent_name']]);
                $parent = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($parent) {
                    // Check if relationship already exists
                    $stmt = $pdo->prepare("
                        SELECT id FROM parent_child 
                        WHERE parent_id = ? AND child_id = ?
                    ");
                    $stmt->execute([$parent['id'], $child_id]);
                    
                    if (!$stmt->fetch()) {
                        // Create the relationship
                        $stmt = $pdo->prepare("
                            INSERT INTO parent_child (parent_id, child_id, relationship, created_at) 
                            VALUES (?, ?, 'mother', NOW())
                        ");
                        if ($stmt->execute([$parent['id'], $child_id])) {
                            $result['parent_assignments']++;
                        }
                    }
                }
            } catch (PDOException $e) {
                $result['errors'][] = 'Error assigning parent: ' . $e->getMessage();
            }
        }
        
        if (!empty($child['grade'])) {
            try {
                // Find teacher users with matching class_name
                $stmt = $pdo->prepare("
                    SELECT id, class_name FROM users 
                    WHERE role = 'teacher' 
                    AND status = 'active' 
                    AND class_name = ? 
                    LIMIT 1
                ");
                $stmt->execute([$child['grade']]);
                $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($teacher) {
                    // Check if relationship already exists
                    $stmt = $pdo->prepare("
                        SELECT id FROM teacher_child 
                        WHERE teacher_id = ? AND child_id = ?
                    ");
                    $stmt->execute([$teacher['id'], $child_id]);
                    
                    if (!$stmt->fetch()) {
                        // Create the relationship
                        $stmt = $pdo->prepare("
                            INSERT INTO teacher_child (teacher_id, child_id, class_name, assigned_at) 
                            VALUES (?, ?, ?, NOW())
                        ");
                        if ($stmt->execute([$teacher['id'], $child_id, $teacher['class_name']])) {
                            $result['teacher_assignments']++;
                        }
                    }
                }
            } catch (PDOException $e) {
                $result['errors'][] = 'Error assigning teacher: ' . $e->getMessage();
            }
        }
        
    } catch (PDOException $e) {
        $result['success'] = false;
        $result['errors'][] = 'Database error: ' . $e->getMessage();
    }
    
    return $result;
}

// If called via API endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['child_id'])) {
    $child_id = (int)$_POST['child_id'];
    $result = autoAssignRelationships($child_id, $pdo);
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
}
?>
