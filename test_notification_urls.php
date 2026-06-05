<?php
/**
 * TEST: Verify notification URLs are generated correctly
 */
require_once 'includes/functions.php';
require_once 'includes/header.php';

if (!hasRole('admin') && !hasRole('it_staff')) {
    die('Admin/IT Staff only');
}

// Test notifications
$testNotifications = [
    ['type' => 'device_disposed', 'related_id' => 5, 'title' => 'Device Disposed'],
    ['type' => 'voluntary_return_requested', 'related_id' => 3, 'title' => 'Voluntary Return'],
    ['type' => 'device_deployed', 'related_id' => 7, 'title' => 'Device Deployed'],
    ['type' => 'repair_assigned', 'related_id' => 2, 'title' => 'Repair Assigned'],
    ['type' => 'device_inspection_failed', 'related_id' => 1, 'title' => 'Inspection Failed'],
];

echo "<div style='padding: 20px;'>";
echo "<h2>🔍 Notification URL Test</h2>";
echo "<p>Current Role: <strong>{$_SESSION['role']}</strong></p>";
echo "<table style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f0f0f0; border: 1px solid #ddd;'>";
echo "<th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>Notification Type</th>";
echo "<th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>Related ID</th>";
echo "<th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>Generated URL</th>";
echo "<th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>Expected Page</th>";
echo "</tr>";

foreach ($testNotifications as $notif) {
    $url = getNotificationUrl($notif);
    $type = $notif['type'];
    $id = $notif['related_id'];
    
    echo "<tr style='border: 1px solid #ddd;'>";
    echo "<td style='padding: 10px; border: 1px solid #ddd;'><strong>$type</strong></td>";
    echo "<td style='padding: 10px; border: 1px solid #ddd;'>$id</td>";
    echo "<td style='padding: 10px; border: 1px solid #ddd;'><code>$url</code></td>";
    
    // Expected page mapping
    $expected = '';
    if (str_contains($url, 'view_device')) $expected = '✅ Device Detail Page';
    elseif (str_contains($url, 'deployments')) $expected = '✅ Deployments Page';
    elseif (str_contains($url, 'maintenance')) $expected = '✅ Maintenance Page';
    elseif (str_contains($url, 'devices.php')) $expected = '✅ Devices List';
    elseif ($url === 'notifications.php') $expected = '❌ WRONG - Back to Notifications';
    else $expected = '❓ ' . $url;
    
    echo "<td style='padding: 10px; border: 1px solid #ddd;'>$expected</td>";
    echo "</tr>";
}

echo "</table>";
echo "</div>";
echo "<div style='padding: 20px; background: #fff3cd; margin-top: 20px; border-radius: 5px;'>";
echo "<p><strong>ℹ️ If you see '❌ WRONG' URLs, the function is still returning 'notifications.php' - refresh the page and try again.</strong></p>";
echo "</div>";
?>
