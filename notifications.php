<?php
/**
 * KBMC Asset Management - All Notifications
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
        <?php foreach ($notifications as $notif):
            $notifUrl = getNotificationUrl($notif);
        ?>
        <div class="activity-item notif-clickable" 
             style="padding: 15px; border-bottom: 1px solid #f5f5f5; background: <?php echo $notif['is_read'] ? 'transparent' : '#FFF5F5'; ?>; border-radius: var(--radius); margin-bottom: 5px; cursor: pointer; transition: all 0.2s ease;"
             data-id="<?php echo $notif['id']; ?>"
             data-url="<?php echo htmlspecialchars($notifUrl); ?>"
             data-type="<?php echo htmlspecialchars($notif['type'] ?? 'unknown'); ?>"
             onclick="handleNotificationClick(this)"
             role="button"
             tabindex="0"
             title="Click to view">
            <div class="activity-icon" style="background: var(--kbmc-red-light); color: var(--kbmc-red);">
                <i class="fas fa-<?php echo match($notif['type']) { 'device_deployed' => 'laptop', 'device_returned' => 'undo', 'low_stock' => 'exclamation-triangle', 'repair_needed' => 'tools', 'request_approved' => 'check-circle', 'request_rejected' => 'times-circle', 'warranty_expiring' => 'clock', 'user_clearance_required' => 'file-signature', 'user_clearance_completed' => 'user-check', 'voluntary_return_requested' => 'hand-holding', 'lifespan_monitor' => 'eye', 'lifespan_replace_soon' => 'hourglass-half', 'lifespan_overdue' => 'exclamation-triangle', 'lifespan_replaced' => 'archive', 'lifespan_extended' => 'plus-circle', default => 'info-circle' }; ?>"></i>
            </div>
            <div class="activity-content" style="flex: 1;">
                <div class="activity-title" style="font-weight: 600;"><?php echo htmlspecialchars($notif['title']); ?></div>
                <div class="activity-time" style="margin-top: 3px;"><?php echo htmlspecialchars($notif['message']); ?></div>
                <div style="font-size: 11px; color: #999; margin-top: 5px;">
                    <?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?> &bull; 
                    <?php echo $notif['is_read'] ? 'Read' : '<strong style="color: var(--kbmc-red);">Unread</strong>'; ?>
                    &bull; <span style="color: var(--kbmc-red);"><i class="fas fa-external-link-alt"></i> Click to go to the relevant page</span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function handleNotificationClick(element) {
    const url = element.getAttribute('data-url');
    const notifId = element.getAttribute('data-id');
    
    if (!url || url === 'dashboard.php') {
        markAsRead(notifId);
        return;
    }
    
    markAsRead(notifId, function() {
        window.location.href = url;
    });
}

function markAsRead(notifId, callback) {
    fetch('ajax/mark_notification_read.php?id=' + encodeURIComponent(notifId), {
        method: 'GET',
        credentials: 'same-origin'
    })
    .then(() => {
        const el = document.querySelector('[data-id="' + notifId + '"]');
        if (el) el.style.background = 'transparent';
        if (callback) callback();
    })
    .catch(() => {
        if (callback) callback();
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>