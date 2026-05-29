<?php
require_once __DIR__ . '/../includes/functions.php';

// Ensure we run as CLI without session issues
if (php_sapi_name() !== 'cli') {
    echo "Run from CLI\n";
}

$res = markMaintenanceCompleted(4, 4, '2026-06-01', 'Test run from CLI');
echo "markMaintenanceCompleted returned: ";
var_export($res);
echo "\n";

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt = $pdo->prepare("SELECT id, device_id, scheduled_date, next_due_date, last_performed_date, notes FROM maintenance_schedules WHERE id = ?");
$stmt->execute([4]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($row);
