<?php
/**
 * KBMC Asset Management - Delete Device
 */
require_once 'includes/functions.php';
requireITStaff();

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT asset_tag FROM devices WHERE id = ?");
$stmt->execute([$id]);
$device = $stmt->fetch();

if ($device) {
    try {
        $pdo->prepare("DELETE FROM device_assignments WHERE device_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM device_inspections WHERE device_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM device_repairs WHERE device_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM devices WHERE id = ?")->execute([$id]);

        logAudit($_SESSION['user_id'], 'Delete', 'devices', $id, $device['asset_tag'], null);
        setFlashMessage('success', 'Device ' . $device['asset_tag'] . ' has been deleted.');
    } catch (PDOException $e) {
        setFlashMessage('error', 'Error deleting device: ' . $e->getMessage());
    }
} else {
    setFlashMessage('error', 'Device not found.');
}

header('Location: devices.php');
exit();
