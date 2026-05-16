<?php
ob_start();
require_once __DIR__ . '/functions.php';
requireLogin();

$user = getUserInfo($_SESSION['user_id']);
$unreadCount = getUnreadNotificationCount($_SESSION['user_id']);
$notifications = getNotifications($_SESSION['user_id'], 5);
$pageTitle = $pageTitle ?? 'KBMC Asset Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($pageTitle); ?> - KBMC Asset Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo-container">
                <img src="assets/images/logo.png" alt="KBMC Logo" class="sidebar-logo-img">
            </div>
            <div class="company-name">
                Kitchen Beauty<br>Marketing Corp.
            </div>
            <button class="sidebar-close" id="sidebarClose">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="sidebar-user">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo sanitize($user['full_name']); ?></div>
                <div class="user-role"><?php echo $role_names[$user['role']] ?? 'User'; ?></div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>

            <?php if (hasRole('admin') || hasRole('it_staff')): ?>
            <div class="nav-section">Device Management</div>
            <a href="devices.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'devices.php' ? 'active' : ''; ?>">
                <i class="fas fa-laptop"></i>
                <span>All Devices</span>
            </a>
            <a href="add_device.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'add_device.php' ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i>
                <span>Add Device</span>
            </a>
            <a href="inspections.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'inspections.php' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-check"></i>
                <span>Inspections</span>
            </a>
            <a href="deployments.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'deployments.php' ? 'active' : ''; ?>">
                <i class="fas fa-hand-holding"></i>
                <span>Deployments</span>
            </a>
            <a href="repairs.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'repairs.php' ? 'active' : ''; ?>">
                <i class="fas fa-tools"></i>
                <span>Repairs</span>
            </a>
            <a href="retired.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'retired.php' ? 'active' : ''; ?>">
                <i class="fas fa-trash-alt"></i>
                <span>Retired / Disposed</span>
            </a>
            <?php endif; ?>

            <a href="requests.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'requests.php' ? 'active' : ''; ?>">
                <i class="fas fa-hand-paper"></i>
                <span>Device Requests</span>
                <?php
                $pendingRequests = $pdo->query("SELECT COUNT(*) FROM device_requests WHERE status = 'pending'")->fetchColumn();
                if ($pendingRequests > 0 && (hasRole('admin') || hasRole('it_staff'))):
                ?>
                <span class="nav-badge"><?php echo $pendingRequests; ?></span>
                <?php endif; ?>
            </a>

            <?php if (hasRole('admin') || hasRole('it_staff')): ?>
            <div class="nav-section">Reports</div>
            <a href="reports.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reports & Analytics</span>
            </a>
            <?php endif; ?>

            <?php if (hasRole('admin')): ?>
            <div class="nav-section">Administration</div>
            <a href="users.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i>
                <span>Manage Users</span>
            </a>
            <a href="audit_logs.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'audit_logs.php' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i>
                <span>Audit Logs</span>
            </a>
            <a href="users.php#recovery" class="nav-item">
                <i class="fas fa-user-shield"></i>
                <span>Recovery Requests</span>
                <?php
                $pendingRecovery = $pdo->query("SELECT COUNT(*) FROM account_recovery_requests WHERE status = 'pending'")->fetchColumn();
                if ($pendingRecovery > 0):
                ?>
                <span class="nav-badge"><?php echo $pendingRecovery; ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>

            <div class="nav-section">Account</div>
            <a href="profile.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-cog"></i>
                <span>My Profile</span>
            </a>
            <a href="logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </aside>

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <!-- Top Header -->
        <header class="top-header">
            <button class="menu-toggle" id="menuToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="header-title"><?php echo sanitize($pageTitle); ?></div>
            <div class="header-actions">
                <div class="notification-dropdown">
                    <button class="notif-btn" id="notifToggle">
                        <i class="fas fa-bell"></i>
                        <?php if ($unreadCount > 0): ?>
                        <span class="notif-badge"><?php echo $unreadCount; ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notif-dropdown" id="notifDropdown">
                        <div class="notif-header">
                            <h4>Notifications</h4>
                            <a href="notifications.php">View All</a>
                        </div>
                        <div class="notif-list">
                            <?php if (empty($notifications)): ?>
                            <div class="notif-empty">No notifications</div>
                            <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                            <div class="notif-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>" 
                                 data-id="<?php echo $notif['id']; ?>" 
                                 data-url="requests.php">
                                <div class="notif-icon">
                                    <i class="fas fa-<?php
                                        echo match($notif['type']) {
                                            'device_deployed' => 'laptop',
                                            'device_returned' => 'undo',
                                            'low_stock' => 'exclamation-triangle',
                                            'repair_needed' => 'tools',
                                            'request_approved' => 'check-circle',
                                            'request_rejected' => 'times-circle',
                                            'warranty_expiring' => 'clock',
                                            default => 'info-circle'
                                        };
                                    ?>"></i>
                                </div>
                                <div class="notif-content">
                                    <div class="notif-title"><?php echo sanitize($notif['title']); ?></div>
                                    <div class="notif-msg"><?php echo sanitize($notif['message']); ?></div>
                                    <div class="notif-time"><?php echo date('M d, h:i A', strtotime($notif['created_at'])); ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="main-content">
            <?php
            $flash = getFlashMessage();
            if ($flash):
            ?>
            <div class="alert alert-<?php echo $flash['type']; ?>" id="flashAlert">
                <i class="fas fa-<?php echo $flash['type'] == 'success' ? 'check-circle' : ($flash['type'] == 'error' ? 'times-circle' : 'info-circle'); ?>"></i>
                <?php echo sanitize($flash['message']); ?>
                <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
            </div>
            <?php endif; ?>