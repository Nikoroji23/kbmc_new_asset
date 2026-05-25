<?php
/**
 * KBMC Asset Management - Admin Dashboard
 * Exclusive dashboard for Administrators with security controls
 */
$pageTitle = 'Admin Dashboard';
require_once 'includes/header.php';
requireAdmin();

// Check if user is security admin
$isSecurityAdmin = isSecurityAdmin($_SESSION['user_id']);

// Get admin-specific statistics
$totalDevices = getTotalDeviceCount();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$itStaffCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'it_staff' AND status = 'active'")->fetchColumn();
$adminCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn();
$employeeCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'employee' AND status = 'active'")->fetchColumn();
$pendingApprovals = $pdo->query("SELECT COUNT(*) FROM user_approval_requests WHERE status = 'pending'")->fetchColumn();

// Get pending user approvals
$approvalRequests = getPendingUserApprovals();

// Get recent audit logs
$stmt = $pdo->query("SELECT al.*, u.full_name FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 10");
$recentLogs = $stmt->fetchAll();

// Get account recovery requests
$stmt = $pdo->query("SELECT ar.*, u.full_name, u.email FROM account_recovery_requests ar JOIN users u ON ar.user_id = u.id WHERE ar.status = 'pending' ORDER BY ar.requested_at DESC LIMIT 5");
$recoveryRequests = $stmt->fetchAll();
?>

<!-- Admin Dashboard Header -->
<div style="margin-bottom: 20px; padding: 15px 20px; background: linear-gradient(135deg, #D9232E 0%, #B91C24 100%); border-radius: 8px; color: white;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="margin: 0; font-size: 24px;"><i class="fas fa-crown"></i> Administrator Dashboard</h2>
            <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 13px;">System Management & Security Control</p>
        </div>
        <div style="text-align: right; font-size: 12px; opacity: 0.9;">
            <div><strong><?php echo date('l, F d, Y'); ?></strong></div>
            <div>User: <?php echo sanitize($_SESSION['full_name']); ?></div>
            <?php if ($isSecurityAdmin): ?>
            <div style="margin-top: 5px; background: rgba(255,255,255,0.2); padding: 3px 8px; border-radius: 3px; display: inline-block;">
                <i class="fas fa-key"></i> Security Admin
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Admin Metrics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <h3><?php echo $totalUsers; ?></h3>
            <span>Active Users</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-user-tie"></i></div>
        <div class="stat-info">
            <h3><?php echo $adminCount; ?></h3>
            <span>Administrators</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-user-cog"></i></div>
        <div class="stat-info">
            <h3><?php echo $itStaffCount; ?></h3>
            <span>IT Staff</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-user-friends"></i></div>
        <div class="stat-info">
            <h3><?php echo $employeeCount; ?></h3>
            <span>Employees</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-laptop"></i></div>
        <div class="stat-info">
            <h3><?php echo $totalDevices; ?></h3>
            <span>Total Devices</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning"><i class="fas fa-hourglass-end"></i></div>
        <div class="stat-info">
            <h3><?php echo $pendingApprovals; ?></h3>
            <span>Pending Approvals</span>
        </div>
    </div>
</div>

<!-- Admin Quick Actions -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-bolt"></i> Admin Controls</h3>
    </div>
    <div class="card-body" style="display: flex; gap: 12px; flex-wrap: wrap;">
        <a href="users.php" class="btn btn-primary" style="flex: 1; min-width: 150px;">
            <i class="fas fa-users-cog"></i> Manage Users
        </a>
        <a href="admin_accounts.php" class="btn btn-warning" style="flex: 1; min-width: 150px;">
            <i class="fas fa-user-check"></i> Account Records
        </a>
        <a href="audit_logs.php" class="btn btn-info" style="flex: 1; min-width: 150px;">
            <i class="fas fa-history"></i> Audit Logs
        </a>
        <a href="reports.php" class="btn btn-success" style="flex: 1; min-width: 150px;">
            <i class="fas fa-file-download"></i> Reports
        </a>
        <?php if ($isSecurityAdmin): ?>
        <a href="security_control.php" class="btn btn-danger" style="flex: 1; min-width: 150px;">
            <i class="fas fa-shield-alt"></i> Security Control
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Pending User Approvals (Security Alert) -->
<?php if ($isSecurityAdmin && $pendingApprovals > 0): ?>
<div class="card" style="margin-top: 20px; border-left: 4px solid #E74C3C;">
    <div class="card-header" style="background: #FDEDEC;">
        <h3 style="color: #E74C3C;"><i class="fas fa-exclamation-circle"></i> Pending IT/Admin User Approvals (<?php echo $pendingApprovals; ?>)</h3>
        <a href="security_control.php#approvals" class="btn btn-sm btn-danger">Review All</a>
    </div>
    <div class="card-body">
        <?php if (empty($approvalRequests)): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle" style="color: #27AE60;"></i>
            <h4>All approvals processed</h4>
            <p>No pending user approvals.</p>
        </div>
        <?php else: ?>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Requested By</th>
                        <th>New User</th>
                        <th>Requested Role</th>
                        <th>Reason</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($approvalRequests, 0, 5) as $req): ?>
                    <tr>
                        <td><?php echo sanitize($req['requested_by_name'] ?? 'Admin'); ?></td>
                        <td><strong><?php echo sanitize($req['full_name']); ?></strong><br><small><?php echo sanitize($req['email']); ?></small></td>
                        <td>
                            <span style="padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; background: <?php echo $req['requested_role'] == 'admin' ? '#D9232E30' : '#3498DB30'; ?>; color: <?php echo $req['requested_role'] == 'admin' ? '#D9232E' : '#3498DB'; ?>;">
                                <?php echo $req['requested_role'] == 'admin' ? 'Administrator' : 'IT Staff'; ?>
                            </span>
                        </td>
                        <td><small><?php echo sanitize(substr($req['reason'] ?? '', 0, 30)); ?></small></td>
                        <td><?php echo formatDate($req['created_at']); ?></td>
                        <td>
                            <form method="POST" action="security_control.php" style="display:inline;">
                                <?php echo csrfInputField(); ?>
                                <input type="hidden" name="action" value="quick_approve">
                                <input type="hidden" name="approval_id" value="<?php echo $req['id']; ?>">
                                <button type="submit" class="action-btn assign" title="Approve" onclick="return confirm('Approve this user creation?')">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                            <form method="POST" action="security_control.php" style="display:inline;">
                                <?php echo csrfInputField(); ?>
                                <input type="hidden" name="action" value="quick_reject">
                                <input type="hidden" name="approval_id" value="<?php echo $req['id']; ?>">
                                <button type="submit" class="action-btn delete" title="Reject" onclick="return confirm('Reject this user creation?')">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Recent Activity Log -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-history"></i> Recent System Activity</h3>
        <a href="audit_logs.php" class="btn btn-sm btn-outline">View All</a>
    </div>
    <div class="card-body">
        <?php if (empty($recentLogs)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h4>No activity yet</h4>
        </div>
        <?php else: ?>
        <?php foreach ($recentLogs as $log): ?>
        <div class="activity-item">
            <div class="activity-icon" style="background: var(--kbmc-red-light); color: var(--kbmc-red);">
                <i class="fas fa-<?php echo strpos($log['action'], 'Create') !== false ? 'plus' : (strpos($log['action'], 'Update') !== false ? 'edit' : (strpos($log['action'], 'Delete') !== false ? 'trash' : 'sign-in-alt')); ?>"></i>
            </div>
            <div class="activity-content">
                <div class="activity-title"><?php echo sanitize($log['action']); ?> - <?php echo sanitize($log['table_name'] ?? 'System'); ?></div>
                <div class="activity-time">By <?php echo sanitize($log['full_name'] ?? 'System'); ?> on <?php echo formatDate($log['created_at'], 'M d, Y h:i A'); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Account Recovery Requests -->
<?php if (!empty($recoveryRequests)): ?>
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-user-shield"></i> Pending Account Recovery Requests</h3>
        <a href="users.php#recovery" class="btn btn-sm btn-outline">View All</a>
    </div>
    <div class="card-body">
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Reason</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recoveryRequests as $req): ?>
                    <tr>
                        <td><strong><?php echo sanitize($req['full_name']); ?></strong></td>
                        <td><?php echo sanitize($req['email']); ?></td>
                        <td><?php echo sanitize($req['department'] ?? 'N/A'); ?></td>
                        <td><small><?php echo sanitize(substr($req['request_reason'] ?? '', 0, 40)); ?></small></td>
                        <td><?php echo formatDate($req['requested_at']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
