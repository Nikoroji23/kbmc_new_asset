<?php
/**
 * KBMC Asset Management - Mark Notification as Read
 * AJAX endpoint for marking notifications as read
 */
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;

if ($id) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Notification not found or already read']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
}