<?php
/**
 * KBMC Asset Management - All Notifications
 * ALL notifications redirect to Device Requests page when clicked
 */
$pageTitle = 'Notifications';
require_once 'includes/header.php';

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$_SESSION['user_id']]);
    setFlashMessage('success', 'All notifications marked as read.');
    header('Location: notifications.php');
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll();
?>

<div class="page-header">
    <h1><i class="fas fa-bell"></i> Notifications</h1>
    <a href="notifications.php?mark_all_read=1" class="btn btn-outline">Mark All as Read</a>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($notifications)): ?>
        <div class="empty-state"><i class="fas fa-bell-slash" style="font-size: 40px;"></i><h4>No notifications</h4></div>
        <?php else: ?>
        <?php foreach ($notifications as $notif): ?>
        <div class="activity-item notif-clickable" 
             style="padding: 15px; border-bottom: 1px solid #f5f5f5; background: <?php echo $notif['is_read'] ? 'transparent' : '#FFF5F5'; ?>; border-radius: var(--radius); margin-bottom: 5px; cursor: pointer; transition: all 0.2s;"
             data-id="<?php echo $notif['id']; ?>"
             data-url="requests.php"
             onclick="handleNotificationClick(this)">
            <div class="activity-icon" style="background: var(--kbmc-red-light); color: var(--kbmc-red);">
                <i class="fas fa-<?php echo match($notif['type']) { 'device_deployed' => 'laptop', 'device_returned' => 'undo', 'low_stock' => 'exclamation-triangle', 'repair_needed' => 'tools', 'request_approved' => 'check-circle', 'request_rejected' => 'times-circle', 'warranty_expiring' => 'clock', default => 'info-circle' }; ?>"></i>
            </div>
            <div class="activity-content" style="flex: 1;">
                <div class="activity-title" style="font-weight: 600;"><?php echo sanitize($notif['title']); ?></div>
                <div class="activity-time" style="margin-top: 3px;"><?php echo sanitize($notif['message']); ?></div>
                <div style="font-size: 11px; color: #999; margin-top: 5px;">
                    <?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?> &bull; 
                    <?php echo $notif['is_read'] ? 'Read' : '<strong style="color: var(--kbmc-red);">Unread</strong>'; ?>
                    &bull; <span style="color: var(--kbmc-red);"><i class="fas fa-external-link-alt"></i> Click to view requests</span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>    