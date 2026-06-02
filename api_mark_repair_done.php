<?php
/**
 * API - Mark Repair as Completed
 * Endpoint for IT staff to mark device repairs as complete and notify employee
 */

header('Content-Type: application/json');
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isLoggedIn() || (!hasRole('admin') && !hasRole('it_staff'))) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$json = json_decode(file_get_contents('php://input'), true);
$repairId = (int)($json['repair_id'] ?? 0);
$completionNotes = sanitize($json['completion_notes'] ?? '');

if (!$repairId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing repair_id']);
    exit();
}

try {
    // Call helper function
    $result = markRepairAsCompleted($repairId, $completionNotes);
    
    if ($result['success']) {
        // Update completed_by if column exists
        if (columnExists('device_repairs', 'completed_by')) {
            $pdo->prepare("UPDATE device_repairs SET completed_by = ? WHERE id = ?")->execute([$_SESSION['user_id'], $repairId]);
        }
        
        // Log audit
        logAudit($_SESSION['user_id'], 'Mark Repair Complete', 'device_repairs', $repairId);
        
        // Send pending emails
        sendPendingEmailNotifications();
        
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
