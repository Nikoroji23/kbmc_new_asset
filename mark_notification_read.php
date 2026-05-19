<?php
/**
 * KBMC Asset Management - Mark Notification as Read
 * AJAX endpoint for marking notifications as read
 */
require_once 'includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;

if ($id) {
    // Only mark as read if the notification belongs to the current user
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);

    // Check if update was successful
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Notification not found or already read']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
}