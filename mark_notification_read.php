<?php
/**
 * AJAX handler to mark a single notification as read
 */
session_start();
require_once '../includes/db.php'; // adjust if your db.php path is different

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo 'Unauthorized';
    exit;
}

// Check if notification ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo 'Missing notification ID';
    exit;
}

$notifId = (int)$_GET['id'];
$userId = (int)$_SESSION['user_id'];

// Update only if the notification belongs to this user
$stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
$stmt->execute([$notifId, $userId]);

echo 'OK';