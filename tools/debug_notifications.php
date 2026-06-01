<?php
require_once 'includes/config.php';

// Get recent notifications with their related_ids
$stmt = $pdo->prepare("
    SELECT id, type, title, related_id, message, created_at 
    FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 20
");
$stmt->execute([$_SESSION['user_id'] ?? 1]);
$notifications = $stmt->fetchAll();

echo "<pre>";
echo "Notifications Debug:\n";
echo "===================\n\n";

foreach ($notifications as $notif) {
    echo "ID: {$notif['id']}\n";
    echo "Type: {$notif['type']}\n";
    echo "Title: {$notif['title']}\n";
    echo "Related ID: " . ($notif['related_id'] ?? 'NULL') . "\n";
    echo "Created: {$notif['created_at']}\n";
    echo "---\n";
}

echo "</pre>";
?>
