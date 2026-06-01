<?php
/**
 * Debug script to check notification data and URL generation
 */

session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Simple check - no auth required for debug
$_SESSION['role'] = 'admin'; // Simulate IT staff

echo "<h1>🔍 Notification Debug Checker</h1>";
echo "<hr>";

// Find Karen Abunda
echo "<h2>Step 1: Find Karen Abunda's user record</h2>";
$stmt = $pdo->prepare("SELECT id, full_name, employee_id, email FROM users WHERE full_name LIKE '%karen%' OR full_name LIKE '%abunda%'");
$stmt->execute();
$users = $stmt->fetchAll();

if (empty($users)) {
    echo "<p style='color:red'>❌ No user found matching 'Karen' or 'Abunda'</p>";
} else {
    foreach ($users as $user) {
        echo "<p>Found: {$user['full_name']} (ID: {$user['id']}, Emp ID: {$user['employee_id']})</p>";
        $karenId = $user['id'];
    }
}

echo "<hr>";

// Check for KBM-IT-001876 device
echo "<h2>Step 2: Find device KBM-IT-001876</h2>";
$stmt = $pdo->prepare("SELECT id, asset_tag FROM devices WHERE asset_tag = 'KBM-IT-001876'");
$stmt->execute();
$device = $stmt->fetch();

if (!$device) {
    echo "<p style='color:red'>❌ Device KBM-IT-001876 not found</p>";
} else {
    echo "<p>Found: {$device['asset_tag']} (ID: {$device['id']})</p>";
    $deviceId = $device['id'];
}

echo "<hr>";

// Check for active assignment
if (isset($karenId) && isset($deviceId)) {
    echo "<h2>Step 3: Check device assignment</h2>";
    $stmt = $pdo->prepare("SELECT id, employee_id, device_id, status FROM device_assignments WHERE employee_id = ? AND device_id = ? AND status = 'active'");
    $stmt->execute([$karenId, $deviceId]);
    $assignment = $stmt->fetch();
    
    if (!$assignment) {
        echo "<p style='color:red'>❌ No active assignment found for Karen + this device</p>";
        
        // Try to find ANY assignment
        $stmt = $pdo->prepare("SELECT id, employee_id, device_id, status FROM device_assignments WHERE device_id = ? ORDER BY assigned_date DESC LIMIT 1");
        $stmt->execute([$deviceId]);
        $anyAssignment = $stmt->fetch();
        if ($anyAssignment) {
            echo "<p style='color:orange'>Found assignment (status: {$anyAssignment['status']}): ID={$anyAssignment['id']}, emp={$anyAssignment['employee_id']}, dev={$anyAssignment['device_id']}</p>";
            $assignment = $anyAssignment;
        }
    } else {
        echo "<p style='color:green'>✓ Found active assignment: ID={$assignment['id']}, status={$assignment['status']}</p>";
    }
}

echo "<hr>";

// Check recent notifications
echo "<h2>Step 4: Recent 'user_clearance_required' notifications</h2>";
$stmt = $pdo->prepare("SELECT id, type, related_id, title, message, created_at FROM notifications WHERE type = 'user_clearance_required' ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$notifs = $stmt->fetchAll();

if (empty($notifs)) {
    echo "<p style='color:red'>❌ No 'user_clearance_required' notifications found</p>";
} else {
    echo "<table border='1' style='border-collapse:collapse;width:100%;'>";
    echo "<tr><th>ID</th><th>Type</th><th>Related ID</th><th>Title</th><th>Created</th></tr>";
    foreach ($notifs as $n) {
        echo "<tr>";
        echo "<td>{$n['id']}</td>";
        echo "<td>{$n['type']}</td>";
        echo "<td>{$n['related_id']}</td>";
        echo "<td>{$n['title']}</td>";
        echo "<td>{$n['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";

// Test URL generation for each notification
echo "<h2>Step 5: Test URL generation for clearance notifications</h2>";
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE type = 'user_clearance_required' ORDER BY created_at DESC LIMIT 3");
$stmt->execute();
$testNotifs = $stmt->fetchAll();

foreach ($testNotifs as $n) {
    echo "<div style='background:#f0f0f0;padding:10px;margin:10px 0;border-left:4px solid #3498db;'>";
    echo "<p><strong>Notification ID {$n['id']}:</strong> {$n['title']}</p>";
    echo "<p>Related ID: {$n['related_id']}</p>";
    
    // Test the URL generation
    $url = getNotificationUrl($n);
    echo "<p><strong>Generated URL:</strong> <code>$url</code></p>";
    
    // Now manually test assignment resolution
    if ($n['related_id']) {
        echo "<p style='font-size:12px;color:#666;'>";
        $stmt = $pdo->prepare("SELECT employee_id, device_id FROM device_assignments WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$n['related_id']]);
        $test = $stmt->fetch();
        if ($test) {
            echo "✓ Assignment lookup works: emp_id={$test['employee_id']}, dev_id={$test['device_id']}";
        } else {
            echo "❌ Assignment lookup failed for ID {$n['related_id']}";
        }
        echo "</p>";
    }
    echo "</div>";
}

echo "<hr>";

// Check if notifications are actually visible to IT staff
if (isset($karenId)) {
    echo "<h2>Step 6: Notifications visible to IT staff</h2>";
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.role 
        FROM users u 
        WHERE u.role IN ('admin', 'it_staff') AND u.status = 'active'
        ORDER BY u.full_name
    ");
    $stmt->execute();
    $itStaff = $stmt->fetchAll();
    
    echo "<p>IT Staff members:</p>";
    echo "<ul>";
    foreach ($itStaff as $staff) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'user_clearance_required'");
        $stmt->execute([$staff['id']]);
        $count = $stmt->fetchColumn();
        echo "<li>{$staff['full_name']} ({$staff['role']}) - {$count} clearance notifications</li>";
    }
    echo "</ul>";
}

echo "<hr>";
echo "<p><a href='javascript:history.back()'>← Back</a></p>";
?>
