<?php
/**
 * Check notifications table structure
 */
session_start();
require_once 'includes/config.php';

echo "<h1>🔍 Notifications Table Structure</h1>";
echo "<hr>";

// Get table structure
$stmt = $pdo->query("DESCRIBE notifications");
$columns = $stmt->fetchAll();

echo "<table border='1' style='border-collapse:collapse;'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
foreach ($columns as $col) {
    echo "<tr>";
    echo "<td><strong>" . $col['Field'] . "</strong></td>";
    echo "<td>" . $col['Type'] . "</td>";
    echo "<td>" . ($col['Null'] === 'YES' ? 'YES' : 'NO') . "</td>";
    echo "<td>" . ($col['Key'] ?: '-') . "</td>";
    echo "<td>" . ($col['Default'] ?: 'NULL') . "</td>";
    echo "<td>" . ($col['Extra'] ?: '-') . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<h2>Sample Notification Record</h2>";

$stmt = $pdo->query("SELECT * FROM notifications ORDER BY id DESC LIMIT 1");
$latest = $stmt->fetch();

if ($latest) {
    echo "<pre>";
    foreach ($latest as $key => $value) {
        $display = $value === null ? '[NULL]' : (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value);
        echo str_pad($key, 20) . ": " . $display . "\n";
    }
    echo "</pre>";
}

?>
