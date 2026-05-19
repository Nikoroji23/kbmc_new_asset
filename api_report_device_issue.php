<?php
/**
 * API - Report Device Issue
 * Endpoint for users to report problems with their assigned devices
 */

header('Content-Type: application/json');
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$deviceId = (int)($_POST['device_id'] ?? 0);
$issueDescription = sanitize($_POST['issue_description'] ?? '');
$severity = $_POST['severity'] ?? 'medium';

if (!$deviceId || !$issueDescription) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    // Verify device is assigned to this user
    $stmt = $pdo->prepare("
        SELECT da.*, d.asset_tag FROM device_assignments da
        JOIN devices d ON da.device_id = d.id
        WHERE da.device_id = ? AND da.employee_id = ? AND da.status = 'active'
    ");
    $stmt->execute([$deviceId, $_SESSION['user_id']]);
    $assignment = $stmt->fetch();
    
    if (!$assignment) {
        echo json_encode(['success' => false, 'message' => 'Device not assigned to you']);
        exit();
    }
    
    // Create repair report
    $incidentFile = null;
    if (!empty($_FILES['attachment']['tmp_name'])) {
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($_FILES['attachment']['size'] > $maxSize) {
            echo json_encode(['success' => false, 'message' => 'File too large. Max 5MB allowed.']);
            exit();
        }
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        if (!in_array($_FILES['attachment']['type'], $allowedTypes)) {
            echo json_encode(['success' => false, 'message' => 'Only images and PDF files allowed']);
            exit();
        }
        
        $uploadDir = 'assets/uploads/incident_reports/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $filename = 'incident_' . time() . '_' . basename($_FILES['attachment']['name']);
        $filepath = $uploadDir . $filename;
        
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $filepath)) {
            $incidentFile = $filepath;
        }
    }
    
    // Insert repair record
    $stmt = $pdo->prepare("
        INSERT INTO device_repairs 
        (device_id, reported_by, issue_description, incident_report_file, repair_status, severity, started_date)
        VALUES (?, ?, ?, ?, 'pending', ?, NOW())
    ");
    $stmt->execute([$deviceId, $_SESSION['user_id'], $issueDescription, $incidentFile, $severity]);
    $repairId = $pdo->lastInsertId();
    
    // Update device status to under_repair if severity is high/critical
    if (in_array($severity, ['high', 'critical'])) {
        $pdo->prepare("UPDATE devices SET status = 'under_repair' WHERE id = ?")->execute([$deviceId]);
    }
    
    // Create audit log
    logAudit($_SESSION['user_id'], 'Report Device Issue', 'device_repairs', $repairId);
    
    // Send notification to IT staff
    $itStaff = $pdo->query("SELECT id, email FROM users WHERE role IN ('admin', 'it_staff')")->fetchAll();
    foreach ($itStaff as $staff) {
        $notifBody = emailTemplate(
            'Device Repair Request',
            "<p>A device repair has been reported by " . $_SESSION['full_name'] . ".</p>
            <p><strong>Device:</strong> " . htmlspecialchars($assignment['asset_tag']) . "</p>
            <p><strong>Issue:</strong> " . htmlspecialchars($issueDescription) . "</p>
            <p><strong>Severity:</strong> " . strtoupper($severity) . "</p>",
            'View Details',
            (defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'])) . '/view_device.php?id=' . $deviceId
        );
        
        queueEmailNotification($staff['id'], $staff['email'], 'repair_pending', 'Device Repair Request - ' . $assignment['asset_tag'], $notifBody, $deviceId, $repairId);
        addNotification($staff['id'], 'repair_needed', 'Device Repair Needed', $_SESSION['full_name'] . ' reported an issue with device ' . $assignment['asset_tag'], $repairId);
    }
    
    echo json_encode(['success' => true, 'message' => 'Issue reported successfully. IT team will review it.']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
