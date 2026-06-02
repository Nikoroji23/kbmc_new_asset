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
        SELECT da.*, d.asset_tag, dt.type_name FROM device_assignments da
        JOIN devices d ON da.device_id = d.id
        JOIN device_types dt ON d.device_type_id = dt.id
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

            // If it's an image, attempt to create a small thumbnail for inline email
            $incidentThumbData = null;
            $incidentThumbMime = null;
            $finfoType = mime_content_type($filepath);
            if (strpos($finfoType, 'image/') === 0 && function_exists('getimagesize')) {
                $thumbDir = $uploadDir . 'thumbs/';
                if (!is_dir($thumbDir)) {
                    mkdir($thumbDir, 0755, true);
                }
                $thumbPath = $thumbDir . 'thumb_' . $filename;
                list($origW, $origH) = getimagesize($filepath);
                $maxW = 300; $maxH = 200;
                $ratio = min($maxW / $origW, $maxH / $origH, 1);
                $newW = (int)($origW * $ratio);
                $newH = (int)($origH * $ratio);

                $srcImg = null;
                switch ($finfoType) {
                    case 'image/jpeg': $srcImg = imagecreatefromjpeg($filepath); break;
                    case 'image/png': $srcImg = imagecreatefrompng($filepath); break;
                    case 'image/gif': $srcImg = imagecreatefromgif($filepath); break;
                }

                if ($srcImg) {
                    $thumbImg = imagecreatetruecolor($newW, $newH);
                    // Preserve transparency for PNG/GIF
                    if ($finfoType === 'image/png' || $finfoType === 'image/gif') {
                        imagecolortransparent($thumbImg, imagecolorallocatealpha($thumbImg, 0, 0, 0, 127));
                        imagealphablending($thumbImg, false);
                        imagesavealpha($thumbImg, true);
                    }
                    imagecopyresampled($thumbImg, $srcImg, 0,0,0,0, $newW, $newH, $origW, $origH);
                    // Save as JPEG to reduce size
                    imagejpeg($thumbImg, $thumbPath, 75);
                    imagedestroy($thumbImg);
                    imagedestroy($srcImg);

                    // Embed thumbnail as base64 for inline email
                    $thumbRaw = file_get_contents($thumbPath);
                    if ($thumbRaw !== false) {
                        $incidentThumbData = base64_encode($thumbRaw);
                        $incidentThumbMime = 'image/jpeg';
                    }
                }
            }
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
    $itStaff = filterUniqueEmails($pdo->query("SELECT id, email FROM users WHERE role IN ('admin', 'it_staff')")->fetchAll());
    foreach ($itStaff as $staff) {
        // Build link to device and optional attachment
        $base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
        $deviceLink = $base . '/view_device.php?id=' . $deviceId;
        $attachmentLink = '';
        if (!empty($incidentFile)) {
            $attachmentLink = "\n<p><strong>Attachment:</strong> <a href=\"" . $base . '/' . $incidentFile . "\" target=\"_blank\">View evidence</a></p>";
        }

        $notifBody = emailTemplate(
            'Device Repair Request',
            "<p>A device repair has been reported by " . $_SESSION['full_name'] . ".</p>
            <p><strong>Device:</strong> " . htmlspecialchars($assignment['asset_tag']) . " (" . htmlspecialchars($assignment['type_name']) . ")</p>
            <p><strong>Issue:</strong> " . htmlspecialchars($issueDescription) . "</p>
            <p><strong>Severity:</strong> " . strtoupper($severity) . "</p>" . $attachmentLink,
            'View Details',
            $deviceLink
        );

        queueEmailNotification($staff['id'], $staff['email'], 'repair_pending', 'Device Repair Request - ' . $assignment['asset_tag'] . ' (' . $assignment['type_name'] . ')', $notifBody, $deviceId, $repairId);
        addSystemNotificationOnlyIfNotExists($staff['id'], 'repair_needed', 'New Repair Request', $_SESSION['full_name'] . ' reported: ' . $issueDescription, $repairId);
    }

    // Attempt to send queued emails immediately (if email configured)
    if (function_exists('sendPendingEmailNotifications')) {
        sendPendingEmailNotifications();
    }
    
    echo json_encode(['success' => true, 'message' => 'Issue reported successfully. IT team will review it.']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
