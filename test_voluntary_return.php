<?php
/**
 * Test page to manually trigger voluntary return for Karen Abunda's device
 */

session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

echo "<h1>🧪 Voluntary Return Test</h1>";
echo "<hr>";

// Find Karen Abunda (the employee)
$stmt = $pdo->prepare("SELECT id, full_name, employee_id, role FROM users WHERE full_name LIKE '%KAREN%ABUNDA%' AND role = 'employee'");
$stmt->execute();
$karen = $stmt->fetch();

if (!$karen) {
    echo "<p style='color:red;'>❌ Karen Abunda (employee) not found</p>";
    die();
}

echo "<p>✓ Found employee: {$karen['full_name']} (ID: {$karen['id']}, Emp ID: {$karen['employee_id']})</p>";

// Find the device KBM-IT-001876
$stmt = $pdo->prepare("SELECT id, asset_tag FROM devices WHERE asset_tag = 'KBM-IT-001876'");
$stmt->execute();
$device = $stmt->fetch();

if (!$device) {
    echo "<p style='color:red;'>❌ Device KBM-IT-001876 not found</p>";
    die();
}

echo "<p>✓ Found device: {$device['asset_tag']} (ID: {$device['id']})</p>";

// Find the assignment
$stmt = $pdo->prepare("SELECT id, status FROM device_assignments WHERE employee_id = ? AND device_id = ? ORDER BY assigned_date DESC LIMIT 1");
$stmt->execute([$karen['id'], $device['id']]);
$assignment = $stmt->fetch();

if (!$assignment) {
    echo "<p style='color:red;'>❌ No assignment found</p>";
    die();
}

echo "<p>✓ Found assignment: ID={$assignment['id']}, status={$assignment['status']}</p>";

echo "<hr>";
echo "<h2>Test Scenario</h2>";

echo "<p>To test the voluntary return, you need to:</p>";
echo "<ol>";
echo "<li>Log in as Karen Abunda (ID: {$karen['id']})</li>";
echo "<li>Visit: <code>return_device.php?id=" . $assignment['id'] . "&mode=voluntary</code></li>";
echo "<li>This should create a 'user_clearance_required' notification</li>";
echo "</ol>";

echo "<hr>";
echo "<h2>Or click this button to simulate the request:</h2>";

// Create a form that will manually trigger the notification creation
?>

<form method="POST">
    <input type="hidden" name="simulate" value="1">
    <button type="submit" style="padding: 10px 20px; background: #3498db; color: white; border: none; cursor: pointer; border-radius: 4px;">
        🔔 Simulate Voluntary Return
    </button>
</form>

<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simulate'])) {
    echo "<div style='background: #f0f0f0; padding: 15px; margin-top: 20px; border-left: 4px solid #27ae60; border-radius: 4px;'>";
    echo "<h3>Simulating voluntary return...</h3>";
    
    // Manually call the notification function
    $title = 'Voluntary Return Requested';
    $message = "Employee {$karen['full_name']} requested voluntary return for device {$device['asset_tag']}. Please review user clearance.";
    
    echo "<p>Calling: notifyITStaff('user_clearance_required', '{$title}', '{$message}', {$assignment['id']})</p>";
    
    // Call the function
    notifyITStaff('user_clearance_required', $title, $message, $assignment['id']);
    
    echo "<p style='color: #27ae60; font-weight: bold;'>✓ Notification created!</p>";
    
    // Check if it was created
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE type = 'user_clearance_required' AND related_id = ?");
    $stmt->execute([$assignment['id']]);
    $count = $stmt->fetchColumn();
    
    echo "<p>Notifications with related_id={$assignment['id']}: {$count}</p>";
    
    // Show who it was sent to
    $stmt = $pdo->prepare("
        SELECT u.full_name, COUNT(*) as count 
        FROM notifications n
        JOIN users u ON n.user_id = u.id
        WHERE n.type = 'user_clearance_required' AND n.related_id = ?
        GROUP BY n.user_id
    ");
    $stmt->execute([$assignment['id']]);
    $recipients = $stmt->fetchAll();
    
    if ($recipients) {
        echo "<p>Sent to:</p>";
        echo "<ul>";
        foreach ($recipients as $r) {
            echo "<li>{$r['full_name']} ({$r['count']} notification)</li>";
        }
        echo "</ul>";
    }
    
    echo "</div>";
}

?>

<hr>
<p><a href="javascript:location.reload()">🔄 Refresh</a> | <a href="debug_all_notifications.php">📊 View All Notifications</a></p>
