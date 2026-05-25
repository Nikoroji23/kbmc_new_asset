<?php
/**
 * KBMC Asset Management - IT Dashboard
 * Exclusive dashboard for IT Staff members
 */
$pageTitle = 'IT Dashboard';
require_once 'includes/header.php';
requireITStaff();

// Get IT-specific statistics
$totalDevices = getTotalDeviceCount();
$inStock = getDeviceCountByStatus('in_stock');
$deployed = getDeviceCountByStatus('deployed');
$underRepair = getDeviceCountByStatus('under_repair');
$retired = getDeviceCountByStatus('retired') + getDeviceCountByStatus('disposed');
$pendingRepairCount = count(getPendingRepairs());
$pendingInspectionCount = $pdo->query("SELECT COUNT(*) FROM devices WHERE status = 'pending_inspection'")->fetchColumn();
$activeAssignments = getActiveAssignmentCount();
$pendingReqCount = $pdo->query("SELECT COUNT(*) FROM device_requests WHERE status = 'pending'")->fetchColumn();

// Get recent repairs
$stmt = $pdo->query("SELECT dr.*, u.full_name, d.asset_tag FROM device_repairs dr JOIN users u ON dr.reported_by = u.id JOIN devices d ON dr.device_id = d.id WHERE dr.status = 'pending' ORDER BY dr.created_at DESC LIMIT 5");
$pendingRepairs = $stmt->fetchAll();

// Get recent inspections
$stmt = $pdo->query("SELECT di.*, d.asset_tag, u.full_name FROM device_inspections di JOIN devices d ON di.device_id = d.id JOIN users u ON di.inspected_by = u.id ORDER BY di.created_at DESC LIMIT 5");
$recentInspections = $stmt->fetchAll();

// Get pending device requests
$stmt = $pdo->query("SELECT dr.*, u.full_name FROM device_requests dr JOIN users u ON dr.requested_by = u.id WHERE dr.status = 'pending' ORDER BY dr.created_at DESC LIMIT 5");
$pendingRequests = $stmt->fetchAll();

// Get recent deployments
$stmt = $pdo->query("SELECT da.*, d.asset_tag, u.full_name as employee_name FROM device_assignments da JOIN devices d ON da.device_id = d.id JOIN users u ON da.employee_id = u.id ORDER BY da.assigned_date DESC LIMIT 5");
$recentDeployments = $stmt->fetchAll();
?>

<!-- IT Dashboard Header -->
<div style="margin-bottom: 20px; padding: 15px 20px; background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%); border-radius: 8px; color: white;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="margin: 0; font-size: 24px;"><i class="fas fa-cogs"></i> IT Staff Dashboard</h2>
            <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 13px;">Device Management & Technical Support</p>
        </div>
        <div style="text-align: right; font-size: 12px; opacity: 0.9;">
            <div><strong><?php echo date('l, F d, Y'); ?></strong></div>
            <div>User: <?php echo sanitize($_SESSION['full_name']); ?></div>
        </div>
    </div>
</div>

<!-- IT-Specific Metrics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-tools"></i></div>
        <div class="stat-info">
            <h3><?php echo $pendingRepairCount; ?></h3>
            <span>Pending Repairs</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-clipboard-check"></i></div>
        <div class="stat-info">
            <h3><?php echo $pendingInspectionCount; ?></h3>
            <span>Pending Inspections</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-hand-holding"></i></div>
        <div class="stat-info">
            <h3><?php echo $activeAssignments; ?></h3>
            <span>Active Assignments</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-bell"></i></div>
        <div class="stat-info">
            <h3><?php echo $pendingReqCount; ?></h3>
            <span>Pending Requests</span>
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
        <div class="stat-icon gray"><i class="fas fa-box"></i></div>
        <div class="stat-info">
            <h3><?php echo $inStock; ?></h3>
            <span>In Stock</span>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-lightning-bolt"></i> Quick Actions</h3>
    </div>
    <div class="card-body" style="display: flex; gap: 12px; flex-wrap: wrap;">
        <a href="add_device.php" class="btn btn-primary" style="flex: 1; min-width: 150px;">
            <i class="fas fa-plus"></i> Add New Device
        </a>
        <a href="inspections.php" class="btn btn-warning" style="flex: 1; min-width: 150px;">
            <i class="fas fa-clipboard-check"></i> Inspect Device
        </a>
        <a href="repairs.php" class="btn btn-danger" style="flex: 1; min-width: 150px;">
            <i class="fas fa-tools"></i> Manage Repairs
        </a>
        <a href="deployments.php" class="btn btn-success" style="flex: 1; min-width: 150px;">
            <i class="fas fa-hand-holding"></i> Deploy Device
        </a>
        <a href="maintenance_reminders.php" class="btn btn-info" style="flex: 1; min-width: 150px;">
            <i class="fas fa-calendar-check"></i> Maintenance
        </a>
        <a href="requests.php" class="btn btn-outline" style="flex: 1; min-width: 150px;">
            <i class="fas fa-tasks"></i> Device Requests
        </a>
    </div>
</div>

<!-- Pending Repairs & Inspections -->
<div class="grid-2" style="margin-top: 20px;">
    <!-- Pending Repairs -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-tools"></i> Pending Repairs (<?php echo count($pendingRepairs); ?>)</h3>
            <a href="repairs.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="card-body">
            <?php if (empty($pendingRepairs)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle" style="color: #27AE60;"></i>
                <h4>All caught up!</h4>
                <p>No pending repairs at the moment.</p>
            </div>
            <?php else: ?>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Device</th>
                            <th>Issue</th>
                            <th>Reported By</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingRepairs as $repair): ?>
                        <tr>
                            <td><strong><?php echo sanitize($repair['asset_tag']); ?></strong></td>
                            <td><small><?php echo sanitize(substr($repair['issue_description'], 0, 40)) . (strlen($repair['issue_description']) > 40 ? '...' : ''); ?></small></td>
                            <td><?php echo sanitize($repair['full_name']); ?></td>
                            <td><?php echo formatDate($repair['created_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Inspections -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-clipboard-check"></i> Recent Inspections</h3>
            <a href="inspections.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="card-body">
            <?php if (empty($recentInspections)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h4>No inspections yet</h4>
                <p>Completed inspections will appear here.</p>
            </div>
            <?php else: ?>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Device</th>
                            <th>Result</th>
                            <th>Condition</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentInspections as $insp): ?>
                        <tr>
                            <td><strong><?php echo sanitize($insp['asset_tag']); ?></strong></td>
                            <td>
                                <span style="padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; background: <?php echo $insp['result'] == 'passed' ? '#27AE6030' : '#E74C3C30'; ?>; color: <?php echo $insp['result'] == 'passed' ? '#27AE60' : '#E74C3C'; ?>;">
                                    <?php echo ucfirst($insp['result']); ?>
                                </span>
                            </td>
                            <td><?php echo ucfirst($insp['physical_condition']); ?></td>
                            <td><?php echo formatDate($insp['inspection_date']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Pending Requests & Deployments -->
<div class="grid-2" style="margin-top: 20px;">
    <!-- Pending Device Requests -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-tasks"></i> Pending Requests (<?php echo $pendingReqCount; ?>)</h3>
            <a href="requests.php?status=pending" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="card-body">
            <?php if (empty($pendingRequests)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle" style="color: #27AE60;"></i>
                <h4>All requests processed</h4>
                <p>No pending device requests.</p>
            </div>
            <?php else: ?>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Requested By</th>
                            <th>Device Type</th>
                            <th>Quantity</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingRequests as $req): ?>
                        <tr>
                            <td><?php echo sanitize($req['full_name']); ?></td>
                            <td><?php echo sanitize($req['device_type'] ?? 'Unspecified'); ?></td>
                            <td><?php echo $req['quantity'] ?? 1; ?></td>
                            <td><?php echo formatDate($req['created_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Deployments -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-hand-holding"></i> Recent Deployments</h3>
            <a href="deployments.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <div class="card-body">
            <?php if (empty($recentDeployments)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h4>No deployments yet</h4>
                <p>Device deployments will appear here.</p>
            </div>
            <?php else: ?>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Device</th>
                            <th>Assigned To</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentDeployments as $dep): ?>
                        <tr>
                            <td><strong><?php echo sanitize($dep['asset_tag']); ?></strong></td>
                            <td><?php echo sanitize($dep['employee_name']); ?></td>
                            <td>
                                <span style="padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; background: <?php echo $dep['status'] == 'active' ? '#27AE6030' : '#F39C1230'; ?>; color: <?php echo $dep['status'] == 'active' ? '#27AE60' : '#F39C12'; ?>;">
                                    <?php echo ucfirst(str_replace('_', ' ', $dep['status'])); ?>
                                </span>
                            </td>
                            <td><?php echo formatDate($dep['assigned_date']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Device Status Overview -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-chart-bar"></i> Device Status Overview</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
            <div style="padding: 15px; background: #27AE6015; border-radius: 8px; text-align: center; border-left: 4px solid #27AE60;">
                <div style="font-size: 24px; font-weight: 700; color: #27AE60;"><?php echo $inStock; ?></div>
                <div style="font-size: 12px; color: #555; margin-top: 5px;">In Stock</div>
            </div>
            <div style="padding: 15px; background: #3498DB15; border-radius: 8px; text-align: center; border-left: 4px solid #3498DB;">
                <div style="font-size: 24px; font-weight: 700; color: #3498DB;"><?php echo $deployed; ?></div>
                <div style="font-size: 12px; color: #555; margin-top: 5px;">Deployed</div>
            </div>
            <div style="padding: 15px; background: #F39C1215; border-radius: 8px; text-align: center; border-left: 4px solid #F39C12;">
                <div style="font-size: 24px; font-weight: 700; color: #F39C12;"><?php echo $underRepair; ?></div>
                <div style="font-size: 12px; color: #555; margin-top: 5px;">Under Repair</div>
            </div>
            <div style="padding: 15px; background: #7F8C8D15; border-radius: 8px; text-align: center; border-left: 4px solid #7F8C8D;">
                <div style="font-size: 24px; font-weight: 700; color: #7F8C8D;"><?php echo $retired; ?></div>
                <div style="font-size: 12px; color: #555; margin-top: 5px;">Retired/Disposed</div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
