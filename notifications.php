<?php
/**
 * KBMC Asset Management - All Notifications
 */
$pageTitle = 'Notifications';
require_once 'includes/functions.php';
requireITStaffOnly();
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

// Icon map per notification type
function getNotificationIcon(string $type): string {
    return match($type) {
        'device_deployed'            => 'laptop',
        'device_returned'            => 'undo',
        'low_stock'                  => 'exclamation-triangle',
        'repair_needed'              => 'tools',
        'request_approved'           => 'check-circle',
        'request_rejected'           => 'times-circle',
        'warranty_expiring'          => 'clock',
        'user_clearance_required'    => 'file-signature',
        'user_clearance_completed'   => 'user-check',
        'voluntary_return_requested' => 'hand-holding',
        'lifespan_monitor'           => 'eye',
        'lifespan_replace_soon'      => 'hourglass-half',
        'lifespan_overdue'           => 'exclamation-triangle',
        'lifespan_replaced'          => 'archive',
        'lifespan_extended'          => 'plus-circle',
        default                      => 'info-circle',
    };
}

// Color per notification type
function getNotificationColor(string $type): string {
    return match(true) {
        $type === 'lifespan_overdue'             => '#E74C3C',
        $type === 'lifespan_replace_soon'        => '#E67E22',
        $type === 'lifespan_monitor'             => '#F39C12',
        $type === 'lifespan_extended'            => '#3498DB',
        $type === 'lifespan_replaced'            => '#7F8C8D',
        $type === 'request_approved'             => '#27AE60',
        $type === 'request_rejected'             => '#E74C3C',
        $type === 'repair_needed'                => '#E67E22',
        $type === 'warranty_expiring'            => '#F39C12',
        str_starts_with($type, 'user_clearance') => '#8E44AD',
        default                                  => '#C0392B',
    };
}
?>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
    <h1><i class="fas fa-bell"></i> Notifications</h1>
    <div style="display:flex;gap:10px;align-items:center;">
        <?php
        $unreadCount = count(array_filter($notifications, fn($n) => !$n['is_read']));
        if ($unreadCount > 0):
        ?>
        <span style="font-size:13px;color:#666;"><?= $unreadCount ?> unread</span>
        <a href="notifications.php?mark_all_read=1" class="btn btn-outline">
            <i class="fas fa-check-double"></i> Mark All as Read
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding:0;">
        <?php if (empty($notifications)): ?>
        <div class="empty-state" style="padding:60px;text-align:center;">
            <i class="fas fa-bell-slash" style="font-size:40px;color:#ddd;display:block;margin-bottom:12px;"></i>
            <h4 style="color:#aaa;font-weight:500;">No notifications yet</h4>
        </div>
        <?php else: ?>
        <?php foreach ($notifications as $notif):
            $type      = $notif['type'] ?? 'unknown';
            $notifUrl  = getNotificationUrl($notif);   // from includes/functions.php
            $icon      = getNotificationIcon($type);
            $color     = getNotificationColor($type);
            $isUnread  = !$notif['is_read'];
        ?>
        <div class="notif-row <?= $isUnread ? 'notif-unread' : '' ?>"
             data-id="<?= $notif['id'] ?>"
             data-url="<?= htmlspecialchars($notifUrl) ?>"
             onclick="handleNotificationClick(this)"
             role="button"
             tabindex="0"
             onkeydown="if(event.key==='Enter') handleNotificationClick(this)">

            <!-- Icon -->
            <div class="notif-row-icon" style="background:<?= $color ?>18;color:<?= $color ?>;">
                <i class="fas fa-<?= $icon ?>"></i>
            </div>

            <!-- Content -->
            <div class="notif-row-content">
                <div class="notif-row-title"><?= htmlspecialchars($notif['title']) ?></div>
                <div class="notif-row-message"><?= htmlspecialchars($notif['message']) ?></div>
                <div class="notif-row-meta">
                    <span><i class="fas fa-clock"></i> <?= date('M d, Y h:i A', strtotime($notif['created_at'])) ?></span>
                    <span style="color:<?= $isUnread ? 'var(--kbmc-red,#C0392B)' : '#aaa' ?>;font-weight:<?= $isUnread ? '600' : '400' ?>;">
                        <?= $isUnread ? '● Unread' : 'Read' ?>
                    </span>
                    <a href="<?= htmlspecialchars($notifUrl) ?>" class="notif-row-link" style="color:<?= $color ?>; text-decoration:none; font-weight:600;" onclick="event.stopPropagation(); handleNotificationClick(this.closest('.notif-row')); return false;">
                        <i class="fas fa-arrow-right"></i> Click to view
                    </a>
                </div>
            </div>

            <!-- Unread dot -->
            <?php if ($isUnread): ?>
            <div class="notif-row-dot" style="background:<?= $color ?>;"></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
.notif-row {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 16px 20px;
    border-bottom: 1px solid #f3f4f6;
    cursor: pointer;
    transition: background 0.15s;
    position: relative;
}
.notif-row:last-child  { border-bottom: none; }
.notif-row:hover       { background: #fafafa; }
.notif-row.notif-unread { background: #fffbfb; }
.notif-row.notif-unread:hover { background: #fff5f5; }

.notif-row-icon {
    width: 42px; height: 42px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 16px;
}
.notif-row-content  { flex: 1; min-width: 0; }
.notif-row-title    { font-weight: 700; font-size: 14px; color: #1e293b; margin-bottom: 3px; }
.notif-row-message  { font-size: 13px; color: #4b5563; margin-bottom: 5px; line-height: 1.45; }
.notif-row-meta     { display: flex; gap: 14px; flex-wrap: wrap; font-size: 11px; color: #9ca3af; }
.notif-row-meta span, .notif-row-meta a { display: flex; align-items: center; gap: 4px; }
.notif-row-link { transition: color 0.2s ease; }
.notif-row-link:hover { text-decoration: underline; }
.notif-row-dot      { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; margin-top: 6px; }
.notif-row:focus    { outline: 2px solid #3b82f6; outline-offset: -2px; }
</style>

<script>
function handleNotificationClick(element) {
    var url     = element.getAttribute('data-url');
    var notifId = element.getAttribute('data-id');

    // Optimistic UI update
    element.classList.remove('notif-unread');
    var dot = element.querySelector('.notif-row-dot');
    if (dot) dot.remove();
    updateNavBadge(-1);

    // Mark as read on server, then navigate
    fetch('ajax/mark_notification_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ id: notifId })
    })
    .catch(function() { /* silent — still navigate */ })
    .finally(function() {
        if (url && url !== 'dashboard.php') {
            window.location.href = url;
        }
    });
}

function updateNavBadge(delta) {
    var badge = document.getElementById('notifBadge') || document.querySelector('.notif-badge');
    if (!badge) return;
    var next = (parseInt(badge.textContent, 10) || 0) + delta;
    if (next <= 0) badge.style.display = 'none';
    else { badge.textContent = next; badge.style.display = ''; }
}
</script>

<?php require_once 'includes/footer.php'; ?>