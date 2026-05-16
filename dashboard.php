<?php
/**
 * KBMC Asset Management - Dashboard
 */
$pageTitle = 'Dashboard';
require_once 'includes/header.php';

// Get statistics
$totalDevices = getTotalDeviceCount();
$inStock = getDeviceCountByStatus('in_stock');
$deployed = getDeviceCountByStatus('deployed');
$underRepair = getDeviceCountByStatus('under_repair');
$retired = getDeviceCountByStatus('retired') + getDeviceCountByStatus('disposed');
$activeAssignments = getActiveAssignmentCount();

// Recent devices
$stmt = $pdo->query("SELECT d.*, dt.type_name FROM devices d JOIN device_types dt ON d.device_type_id = dt.id ORDER BY d.created_at DESC LIMIT 5");
$recentDevices = $stmt->fetchAll();

// Recent assignments
$stmt = $pdo->query("SELECT da.*, d.asset_tag, d.model, u.full_name as employee_name FROM device_assignments da JOIN devices d ON da.device_id = d.id JOIN users u ON da.employee_id = u.id ORDER BY da.created_at DESC LIMIT 5");
$recentAssignments = $stmt->fetchAll();

// Status distribution for chart
$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM devices GROUP BY status");
$statusData = $stmt->fetchAll();

// Device type distribution
$stmt = $pdo->query("SELECT dt.type_name, COUNT(d.id) as count FROM device_types dt LEFT JOIN devices d ON dt.id = d.device_type_id GROUP BY dt.id");
$typeData = $stmt->fetchAll();

// Recent audit logs
$stmt = $pdo->query("SELECT al.*, u.full_name FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 8");
$recentLogs = $stmt->fetchAll();

// Pending requests
$pendingReqCount = $pdo->query("SELECT COUNT(*) FROM device_requests WHERE status = 'pending'")->fetchColumn();

// Pending recovery requests (admin only)
$pendingRecoveryCount = 0;
$recentRecoveryRequests = [];
if (hasRole('admin')) {
    $pendingRecoveryCount = $pdo->query("SELECT COUNT(*) FROM account_recovery_requests WHERE status = 'pending'")->fetchColumn();
    $stmt = $pdo->query("SELECT ar.*, u.full_name, u.email, u.department FROM account_recovery_requests ar JOIN users u ON ar.user_id = u.id WHERE ar.status = 'pending' ORDER BY ar.requested_at DESC LIMIT 5");
    $recentRecoveryRequests = $stmt->fetchAll();
}
?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-laptop"></i></div>
        <div class="stat-info">
            <h3><?php echo $totalDevices; ?></h3>
            <span>Total Devices</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-box"></i></div>
        <div class="stat-info">
            <h3><?php echo $inStock; ?></h3>
            <span>In Stock</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-hand-holding"></i></div>
        <div class="stat-info">
            <h3><?php echo $deployed; ?></h3>
            <span>Deployed</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-tools"></i></div>
        <div class="stat-info">
            <h3><?php echo $underRepair; ?></h3>
            <span>Under Repair</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <h3><?php echo $activeAssignments; ?></h3>
            <span>Active Assignments</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gray"><i class="fas fa-trash-alt"></i></div>
        <div class="stat-info">
            <h3><?php echo $retired; ?></h3>
            <span>Retired / Disposed</span>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie"></i> Device Status Distribution</h3>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> Devices by Type</h3>
        </div>
        <div class="card-body">
            <div class="chart-container">
                <canvas id="typeChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity & Devices -->
<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-clock"></i> Recent Devices Added</h3>
            <a href="devices.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="card-body">
            <?php if (empty($recentDevices)): ?>
            <div class="empty-state">
                <i class="fas fa-laptop"></i>
                <h4>No devices yet</h4>
                <p>Devices added will appear here.</p>
            </div>
            <?php else: ?>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Asset Tag</th>
                            <th>Type</th>
                            <th>Model</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentDevices as $dev): ?>
                        <tr>
                            <td><strong><?php echo sanitize($dev['asset_tag']); ?></strong></td>
                            <td><?php echo sanitize($dev['type_name']); ?></td>
                            <td><?php echo sanitize($dev['brand'] . ' ' . $dev['model']); ?></td>
                            <td><?php echo getStatusBadge($dev['status']); ?></td>
                            <td><?php echo formatDate($dev['created_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-hand-holding"></i> Recent Deployments</h3>
            <a href="deployments.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="card-body">
            <?php if (empty($recentAssignments)): ?>
            <div class="empty-state">
                <i class="fas fa-hand-holding"></i>
                <h4>No deployments yet</h4>
                <p>Device assignments will appear here.</p>
            </div>
            <?php else: ?>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Device</th>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentAssignments as $asgn): ?>
                        <tr>
                            <td><strong><?php echo sanitize($asgn['asset_tag']); ?></strong><br><small><?php echo sanitize($asgn['model']); ?></small></td>
                            <td><?php echo sanitize($asgn['employee_name']); ?></td>
                            <td><?php echo formatDate($asgn['assigned_date']); ?></td>
                            <td>
                                <span class="status-badge" style="background: <?php echo $asgn['status'] == 'active' ? '#27AE6020' : '#F39C1220'; ?>; color: <?php echo $asgn['status'] == 'active' ? '#27AE60' : '#F39C12'; ?>; border: 1px solid <?php echo $asgn['status'] == 'active' ? '#27AE60' : '#F39C12'; ?>;">
                                    <?php echo ucfirst($asgn['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
    </div>
    <div class="card-body" style="display: flex; gap: 15px; flex-wrap: wrap;">
        <?php if (hasRole('admin') || hasRole('it_staff')): ?>
        <a href="add_device.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Device
        </a>
        <a href="inspections.php" class="btn btn-secondary">
            <i class="fas fa-clipboard-check"></i> Inspect Device
        </a>
        <a href="deployments.php?action=assign" class="btn btn-success">
            <i class="fas fa-hand-holding"></i> Assign Device
        </a>
        <?php endif; ?>
        <a href="requests.php?action=new" class="btn btn-warning">
            <i class="fas fa-hand-paper"></i> Request Device
        </a>
        <?php if (hasRole('admin') || hasRole('it_staff')): ?>
        <a href="reports.php" class="btn btn-outline">
            <i class="fas fa-download"></i> Generate Report
        </a>
        <?php endif; ?>
        <?php if ($pendingReqCount > 0 && (hasRole('admin') || hasRole('it_staff'))): ?>
        <a href="requests.php" class="btn btn-danger">
            <i class="fas fa-bell"></i> <?php echo $pendingReqCount; ?> Pending Request<?php echo $pendingReqCount > 1 ? 's' : ''; ?>
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (hasRole('admin') && $pendingRecoveryCount > 0): ?>
<!-- Recovery Requests Alert Card -->
<div class="card" style="border-left: 4px solid #E74C3C;">
    <div class="card-header" style="background: #FDEDEC;">
        <h3 style="color: #E74C3C;"><i class="fas fa-user-shield"></i> Account Recovery Requests</h3>
        <a href="users.php#recovery" class="btn btn-sm btn-danger">View All</a>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Reason</th>
                        <th>Requested</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentRecoveryRequests as $req): ?>
                    <tr>
                        <td><strong><?php echo sanitize($req['full_name']); ?></strong></td>
                        <td><?php echo sanitize($req['email']); ?></td>
                        <td><?php echo sanitize($req['department'] ?: 'N/A'); ?></td>
                        <td><?php echo sanitize(substr($req['request_reason'], 0, 40)) . (strlen($req['request_reason']) > 40 ? '...' : ''); ?></td>
                        <td><?php echo formatDate($req['requested_at']); ?></td>
                        <td>
                            <div class="action-btns">
                                <a href="users.php?recovery_action=approve&recovery_id=<?php echo $req['id']; ?>" 
                                   class="action-btn assign" 
                                   title="Approve"
                                   onclick="return confirm('Approve recovery for <?php echo sanitize($req['full_name']); ?>?')">
                                    <i class="fas fa-check"></i>
                                </a>
                                <a href="users.php?recovery_action=reject&recovery_id=<?php echo $req['id']; ?>" 
                                   class="action-btn delete" 
                                   title="Reject"
                                   onclick="return confirm('Reject recovery for <?php echo sanitize($req['full_name']); ?>?')">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent Activity Log -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-history"></i> Recent Activity</h3>
        <a href="audit_logs.php" class="btn btn-sm btn-outline">View All Logs</a>
    </div>
    <div class="card-body">
        <?php if (empty($recentLogs)): ?>
        <div class="empty-state">
            <i class="fas fa-history"></i>
            <h4>No activity yet</h4>
        </div>
        <?php else: ?>
        <?php foreach ($recentLogs as $log): ?>
        <div class="activity-item">
            <div class="activity-icon" style="background: var(--kbmc-red-light); color: var(--kbmc-red);">
                <i class="fas fa-<?php echo $log['action'] == 'Login' ? 'sign-in-alt' : ($log['action'] == 'Insert' ? 'plus' : ($log['action'] == 'Update' ? 'edit' : 'trash')); ?>"></i>
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

<script>
// Status Distribution Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php foreach ($statusData as $s) echo "'" . ucwords(str_replace('_', ' ', $s['status'])) . "',"; ?>],
        datasets: [{
            data: [<?php foreach ($statusData as $s) echo $s['count'] . ","; ?>],
            backgroundColor: [
                <?php foreach ($statusData as $s) echo "'" . ($status_colors[$s['status']] ?? '#6C757D') . "',"; ?>
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom', labels: { padding: 15, font: { size: 11 } } }
        }
    }
});

// Device Type Chart
const typeCtx = document.getElementById('typeChart').getContext('2d');
new Chart(typeCtx, {
    type: 'bar',
    data: {
        labels: [<?php foreach ($typeData as $t) echo "'" . sanitize($t['type_name']) . "',"; ?>],
        datasets: [{
            label: 'Devices',
            data: [<?php foreach ($typeData as $t) echo $t['count'] . ","; ?>],
            backgroundColor: '#D9232E',
            borderRadius: 5,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } },
            x: { grid: { display: false } }
        }
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>