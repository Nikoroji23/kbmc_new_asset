<?php
require_once __DIR__ . '/../includes/functions.php';

$res = markMaintenanceCompleted(4, 4, '2026-06-01', 'Test run from CLI');
echo "markMaintenanceCompleted returned: ";
var_export($res);
echo "\n";

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt = $pdo->prepare("SELECT id, device_id, scheduled_date, next_due_date, last_performed_date, notes FROM maintenance_schedules WHERE id = ?");
$stmt->execute([4]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($row);

echo "\nColumn checks:\n";
foreach (['last_performed_date','next_due_date','completed_at','completed_by','completion_notes'] as $col) {
    echo $col . ': ' . (columnExists('maintenance_schedules', $col) ? 'YES' : 'NO') . "\n";
}
