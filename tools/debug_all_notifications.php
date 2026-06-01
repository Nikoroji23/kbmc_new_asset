<?php
/**
 * Debug script - Check ALL recent notifications to see what's being saved
 */

session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Simulate IT staff role
$_SESSION['role'] = 'admin';

echo "<h1>🔍 All Recent Notifications (Last 20)</h1>";
echo "<hr>";

// Get ALL recent notifications
$stmt = $pdo->prepare("
    SELECT id, type, related_id, title, message, created_at, user_id
    FROM notifications 
    ORDER BY created_at DESC 
    LIMIT 20
");
$stmt->execute();
$allNotifs = $stmt->fetchAll();

if (empty($allNotifs)) {
    echo "<p style='color:red; font-size:16px;'>❌ NO NOTIFICATIONS FOUND AT ALL IN DATABASE</p>";
} else {
    echo "<p>Total: " . count($allNotifs) . " notifications</p>";
    echo "<table border='1' style='border-collapse:collapse;width:100%;margin:20px 0;'>";
    echo "<tr style='background:#333;color:white;'><th>ID</th><th>Type</th><th>Related ID</th><th>Title</th><th>Message</th><th>Created</th></tr>";
    foreach ($allNotifs as $n) {
        $typeColor = $n['type'] === 'user_clearance_required' ? '#27ae60' : '#3498db';
        echo "<tr style='background:" . ($n['type'] === 'user_clearance_required' ? '#d5f4e6' : '#fff') . ";'>";
        echo "<td>{$n['id']}</td>";
        echo "<td style='color:{$typeColor};font-weight:bold;'>{$n['type']}</td>";
        echo "<td>{$n['related_id']}</td>";
        echo "<td><small>{$n['title']}</small></td>";
        echo "<td><small>{$n['message']}</small></td>";
        echo "<td><small>{$n['created_at']}</small></td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<h2>Checking Karen Abunda specifically...</h2>";

// Get Karen's ID - use the one from assignment (ID 88)
$karenId = 88;

echo "<p>Karen's user ID: $karenId</p>";

// Check what notifications Karen has received
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
$stmt->execute([$karenId]);
$karenNotifCount = $stmt->fetchColumn();
echo "<p>Notifications sent to Karen: $karenNotifCount</p>";

// Check recent notifications for Karen
$stmt = $pdo->prepare("SELECT type, title, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$karenId]);
$karenNotifs = $stmt->fetchAll();

if ($karenNotifs) {
    echo "<p>Recent notifications for Karen:</p>";
    echo "<ul>";
    foreach ($karenNotifs as $n) {
        echo "<li>[{$n['type']}] {$n['title']} - {$n['created_at']}</li>";
    }
    echo "</ul>";
}

echo "<hr>";
echo "<h2>Checking IT Staff notifications...</h2>";

// Get IT staff
$stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE role IN ('admin', 'it_staff') AND status = 'active'");
$stmt->execute();
$itStaff = $stmt->fetchAll();

foreach ($itStaff as $staff) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$staff['id']]);
    $latestNotif = $pdo->prepare("
        SELECT type, title, created_at 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $latestNotif->execute([$staff['id']]);
    $recent = $latestNotif->fetch();
    
    if ($recent) {
        echo "<p><strong>{$staff['full_name']}</strong> - Latest: [{$recent['type']}] {$recent['title']} ({$recent['created_at']})</p>";
    } else {
        echo "<p><strong>{$staff['full_name']}</strong> - No notifications</p>";
    }
}

echo "<hr>";
echo "<h2>Device Assignment for KBM-IT-001876</h2>";

$stmt = $pdo->prepare("
    SELECT da.id, da.employee_id, da.device_id, da.status, u.full_name 
    FROM device_assignments da
    JOIN users u ON da.employee_id = u.id
    WHERE da.device_id = 447
    ORDER BY da.assigned_date DESC
    LIMIT 5
");
$stmt->execute();
$assignments = $stmt->fetchAll();

echo "<table border='1' style='border-collapse:collapse;width:100%;margin:20px 0;'>";
echo "<tr><th>Assignment ID</th><th>Employee</th><th>Status</th><th>Assigned Date</th></tr>";
foreach ($assignments as $a) {
    echo "<tr>";
    echo "<td><strong style='color:red;'>{$a['id']}</strong></td>";
    echo "<td>{$a['full_name']}</td>";
    echo "<td>{$a['status']}</td>";
    echo "<td>{$a['assigned_date']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<p style='color:#666;font-size:12px;'>🔍 Looking for: Type='user_clearance_required' OR Type='voluntary_return_requested' notifications with Device/Assignment ID 446 or 447</p>";

$stmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE (type IN ('user_clearance_required', 'voluntary_return_requested') 
           OR related_id IN (446, 447))
    ORDER BY created_at DESC
");
$stmt->execute();
$searchResults = $stmt->fetchAll();

if (empty($searchResults)) {
    echo "<p style='color:red;'>❌ Found 0 matching notifications</p>";
} else {
    echo "<p style='color:green;'>✓ Found " . count($searchResults) . " matching notifications:</p>";
    foreach ($searchResults as $r) {
        echo "<div style='background:#e8f4f8;padding:10px;margin:10px 0;border-left:4px solid #3498db;'>";
        echo "<p><strong>ID {$r['id']} | Type: {$r['type']}</strong></p>";
        echo "<p>To User ID: {$r['user_id']} | Related ID: {$r['related_id']}</p>";
        echo "<p>Title: {$r['title']}</p>";
        echo "<p>Created: {$r['created_at']}</p>";
        echo "</div>";
    }
}

?>
