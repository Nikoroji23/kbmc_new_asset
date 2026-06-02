<?php
/**
 * KBMC Notification System - Verification Test
 * Tests all notification functionality
 */

require_once __DIR__ . '/includes/functions.php';

$results = [];

// Test 1: Check notifications table structure
$results['table_check'] = [
    'test' => 'Notifications Table Schema',
    'status' => 'CHECKING'
];

try {
    $stmt = $pdo->query("DESCRIBE notifications");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    $required = ['id', 'user_id', 'type', 'title', 'message', 'is_read', 'related_id', 'created_at'];
    $missing = array_diff($required, $columns);
    
    if (empty($missing)) {
        $results['table_check']['status'] = '✅ PASS';
        $results['table_check']['details'] = 'All required columns present: ' . implode(', ', $columns);
        $results['table_check']['has_read_at'] = in_array('read_at', $columns) ? 'ERROR: read_at column exists (should not)' : 'CORRECT: read_at does not exist';
    } else {
        $results['table_check']['status'] = '❌ FAIL';
        $results['table_check']['details'] = 'Missing columns: ' . implode(', ', $missing);
    }
} catch (Exception $e) {
    $results['table_check']['status'] = '❌ ERROR';
    $results['table_check']['details'] = $e->getMessage();
}

// Test 2: Test mark notification as read query
$results['mark_read_query'] = [
    'test' => 'Mark Notification Read Query',
    'status' => 'CHECKING'
];

try {
    // Create test notification first
    $stmt = $pdo->prepare(
        "INSERT INTO notifications (user_id, type, title, message, is_read, created_at) 
         VALUES (?, ?, ?, ?, 0, NOW())"
    );
    
    if (!isset($_SESSION['user_id'])) {
        // Get first admin user for testing
        $adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $admin = $adminStmt->fetch();
        $testUserId = $admin ? $admin['id'] : 1;
    } else {
        $testUserId = $_SESSION['user_id'];
    }
    
    $stmt->execute([$testUserId, 'test_notification', 'Test Title', 'Test Message']);
    $testNotifId = $pdo->lastInsertId();
    
    // Now test the update query
    $updateStmt = $pdo->prepare(
        "UPDATE notifications SET is_read = 1 
         WHERE id = ? AND user_id = ? LIMIT 1"
    );
    $updateStmt->execute([$testNotifId, $testUserId]);
    
    if ($updateStmt->rowCount() > 0) {
        $results['mark_read_query']['status'] = '✅ PASS';
        $results['mark_read_query']['details'] = 'Successfully marked notification as read';
        
        // Verify the update
        $verifyStmt = $pdo->prepare("SELECT is_read FROM notifications WHERE id = ?");
        $verifyStmt->execute([$testNotifId]);
        $notif = $verifyStmt->fetch();
        
        if ($notif && $notif['is_read'] == 1) {
            $results['mark_read_query']['verification'] = '✅ Verified: is_read = 1';
        } else {
            $results['mark_read_query']['verification'] = '❌ Verification failed';
        }
        
        // Clean up test notification
        $pdo->prepare("DELETE FROM notifications WHERE id = ?")->execute([$testNotifId]);
    } else {
        $results['mark_read_query']['status'] = '❌ FAIL';
        $results['mark_read_query']['details'] = 'No rows updated';
    }
} catch (Exception $e) {
    $results['mark_read_query']['status'] = '❌ ERROR';
    $results['mark_read_query']['details'] = $e->getMessage();
}

// Test 3: Check notification URL function
$results['url_routing'] = [
    'test' => 'Notification URL Routing',
    'status' => 'CHECKING'
];

try {
    $_SESSION['role'] = 'it_staff';
    
    $testCases = [
        ['type' => 'repair_needed', 'related_id' => 0, 'expected' => 'maintenance_repairs.php'],
        ['type' => 'repair_pending', 'related_id' => 0, 'expected' => 'maintenance_repairs.php'],
        ['type' => 'maintenance_assigned', 'related_id' => 0, 'expected' => 'maintenance_repairs.php'],
        ['type' => 'maintenance_due', 'related_id' => 0, 'expected' => 'maintenance_repairs.php'],
        ['type' => 'device_deployed', 'related_id' => 0, 'expected' => 'deployments.php'],
    ];
    
    $passed = 0;
    $failed = [];
    
    foreach ($testCases as $case) {
        $url = getNotificationUrl($case);
        if (strpos($url, $case['expected']) !== false) {
            $passed++;
        } else {
            $failed[] = $case['type'] . " returned '$url' (expected '*" . $case['expected'] . "*')";
        }
    }
    
    if (empty($failed)) {
        $results['url_routing']['status'] = '✅ PASS';
        $results['url_routing']['details'] = "All $passed notification types routed correctly";
    } else {
        $results['url_routing']['status'] = '⚠️ PARTIAL';
        $results['url_routing']['failed'] = $failed;
    }
} catch (Exception $e) {
    $results['url_routing']['status'] = '❌ ERROR';
    $results['url_routing']['details'] = $e->getMessage();
}

// Test 4: Check email configuration
$results['email_config'] = [
    'test' => 'Email Configuration',
    'status' => 'CHECKING'
];

try {
    if (isEmailConfigured()) {
        $results['email_config']['status'] = '✅ PASS';
        $results['email_config']['details'] = 'Email system is configured';
        $results['email_config']['note'] = 'PHPMailer is ready to send emails';
    } else {
        $results['email_config']['status'] = '⚠️ WARNING';
        $results['email_config']['details'] = 'Email system not configured';
        $results['email_config']['note'] = 'Check includes/PHPMailer/email_config.php';
    }
} catch (Exception $e) {
    $results['email_config']['status'] = '❌ ERROR';
    $results['email_config']['details'] = $e->getMessage();
}

// Test 5: Check for read_at column references in code
$results['code_check'] = [
    'test' => 'Code References Check',
    'status' => 'CHECKING'
];

try {
    $files_to_check = [
        'mark_notification_read.php',
        'includes/functions.php',
        'includes/header.php'
    ];
    
    $bad_references = [];
    
    foreach ($files_to_check as $file) {
        $path = __DIR__ . '/' . $file;
        if (file_exists($path)) {
            $content = file_get_contents($path);
            $lines = file($path);
            
            foreach ($lines as $lineNum => $line) {
                if (preg_match('/read_at\s*=|\.read_at|UPDATE.*read_at/i', $line)) {
                    $bad_references[] = "$file (line " . ($lineNum + 1) . "): " . trim($line);
                }
            }
        }
    }
    
    if (empty($bad_references)) {
        $results['code_check']['status'] = '✅ PASS';
        $results['code_check']['details'] = 'No read_at column references found in code';
    } else {
        $results['code_check']['status'] = '❌ FOUND';
        $results['code_check']['references'] = $bad_references;
    }
} catch (Exception $e) {
    $results['code_check']['status'] = '❌ ERROR';
    $results['code_check']['details'] = $e->getMessage();
}

// Output results
header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
