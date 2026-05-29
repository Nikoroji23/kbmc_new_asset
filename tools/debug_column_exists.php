<?php
require_once __DIR__ . '/../includes/config.php';
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$table = 'maintenance_schedules';
$col = 'next_due_date';
$stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
$stmt->execute([$col]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
var_export($rows);
echo "\nfetchColumn:\n";
$pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
$stmt2 = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
$stmt2->execute([$col]);
var_export($stmt2->fetchColumn());

// Also try direct query version
$stmt3 = $pdo->query("SHOW COLUMNS FROM `$table` LIKE 'next_due_date'");
var_export($stmt3->fetchAll(PDO::FETCH_ASSOC));
