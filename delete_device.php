<?php
/**
 * KBMC Asset Management - Delete Device
 * Marks device as disposed and records who disposed it
 */
require_once 'includes/functions.php';
requireITStaff();

// Ensure disposal tracking columns exist
ensureDeviceSchema();

$id = $_GET['id'] ?? 0;
$currentUserId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT asset_tag, status FROM devices WHERE id = ?");
$stmt->execute([$id]);
$device = $stmt->fetch();

if ($device) {
    try {
        // Get user info who is disposing
        $userStmt = $pdo->prepare("SELECT full_name, email, role FROM users WHERE id = ?");
        $userStmt->execute([$currentUserId]);
        $currentUser = $userStmt->fetch();
        
        $currentUserName = $currentUser ? $currentUser['full_name'] : 'Unknown User';
        $currentUserEmail = $currentUser ? $currentUser['email'] : 'N/A';
        $currentUserRole = $currentUser ? $currentUser['role'] : 'Unknown';
        
        // Update device status to 'disposed' and record who disposed it
        $updateStmt = $pdo->prepare("
            UPDATE devices 
            SET status = 'disposed', 
                disposed_by = ?, 
                disposed_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$currentUserId, $id]);
        
        // Enhanced audit log with complete user information
        $auditDetails = "Asset Tag: {$device['asset_tag']}. " .
                       "Disposed By: {$currentUserName} ({$currentUserRole}). " .
                       "Email: {$currentUserEmail}. " .
                       "Disposal Date: " . date('Y-m-d H:i:s');
        logAudit($currentUserId, 'Dispose', 'devices', $id, $auditDetails);
        
        // Notify IT staff about device disposal
        $notificationTitle = "Device Disposed: " . $device['asset_tag'];
        $notificationMessage = "Device " . $device['asset_tag'] . " has been marked as disposed by " . $currentUserName . ".";
        notifyITStaff('device_disposed', $notificationTitle, $notificationMessage, $id);
        
        setFlashMessage('success', 'Device ' . $device['asset_tag'] . ' has been marked as disposed.');
    } catch (PDOException $e) {
        setFlashMessage('error', 'Error disposing device: ' . $e->getMessage());
    }
} else {
    setFlashMessage('error', 'Device not found.');
}

header('Location: devices.php');
exit();
