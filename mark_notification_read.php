<?php
/**
 * KBMC Asset Management — Mark Notification Read (AJAX)
 * File: mark_notification_read.php
 *
 * Accepts POST with JSON body:
 *   { "id": 42 }       → mark single notification as read
 *   { "all": true }    → mark ALL of this user's notifications as read
 */
require_once __DIR__ . '/includes/functions.php';

// Must be logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

try {
    if (!empty($input['all'])) {
        // Mark ALL unread notifications for this user as read
        $stmt = $pdo->prepare(
            "UPDATE notifications SET is_read = 1, read_at = NOW()
             WHERE user_id = ? AND is_read = 0"
        );
        $stmt->execute([$_SESSION['user_id']]);
        echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);

    } elseif (!empty($input['id'])) {
        // Mark a single notification as read — verify it belongs to this user
        $stmt = $pdo->prepare(
            "UPDATE notifications SET is_read = 1, read_at = NOW()
             WHERE id = ? AND user_id = ? LIMIT 1"
        );
        $stmt->execute([(int)$input['id'], $_SESSION['user_id']]);
        echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);

    } else {
        echo json_encode(['success' => false, 'error' => 'No action specified']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}