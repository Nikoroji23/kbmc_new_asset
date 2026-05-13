<?php
/**
 * KBMC Asset Management - Deployments
 */
$pageTitle = 'Deployments';
require_once 'includes/header.php';
requireITStaff();

$action = $_GET['action'] ?? 'list';

// Handle device return
if (isset($_GET['action']) && $_GET['action'] == 'return' && isset($_GET['id'])) {
    $assignmentId = $_GET['id'];
    $stmt = $pdo->prepare("SELECT da.*, d.asset_tag FROM device_assignments da JOIN devices d ON da.device_id = d.id WHERE da.id = ?");
    $stmt->execute([$assignmentId]);
    $assignment = $stmt->fetch();

    if ($assignment) {
        $pdo->prepare("UPDATE device_assignments SET status = 'returned', returned_date = CURDATE() WHERE id = ?")->execute([$assignmentId]);
        $pdo->prepare("UPDATE devices SET status = 'in_stock', location = 'IT Stock Room' WHERE id = ?")->execute([$assignment['device_id']]);

        // Notify employee
        addNotification($assignment['employee_id'], 'device_returned', 'Device Returned', "Your assigned device {$assignment['asset_tag']} has been returned.", $assignment['device_id']);
        // Notify admin
        $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
        foreach ($admins as $admin) {
            addNotification($admin['id'], 'device_returned', 'Device Returned', "Device {$assignment['asset_tag']} has been returned to stock.", $assignment['device_id']);
        }

        logAudit($_SESSION['user_id'], 'Return', 'device_assignments', $assignmentId);
        setFlashMessage('success', 'Device returned successfully.');
    }
    header('Location: deployments.php');
    exit();
}

// Handle new assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_device'])) {
    $device_id = $_POST['device_id'] ?? '';
    $employee_id = $_POST['employee_id'] ?? '';
    $purpose = trim($_POST['purpose'] ?? '');

    if ($device_id && $employee_id) {
        try {
            // Create assignment
            $stmt = $pdo->prepare("INSERT INTO device_assignments (device_id, employee_id, assigned_by, assigned_date, purpose, accountability_form_signed, status) VALUES (?, ?, ?, CURDATE(), ?, 1, 'active')");
            $stmt->execute([$device_id, $employee_id, $_SESSION['user_id'], $purpose]);

            // Update device status
            $pdo->prepare("UPDATE devices SET status = 'deployed' WHERE id = ?")->execute([$device_id]);

            // Get device info
            $device = $pdo->query("SELECT asset_tag FROM devices WHERE id = $device_id")->fetch();

            // Notify employee
            addNotification($employee_id, 'device_deployed', 'Device Deployed', "A device ({$device['asset_tag']}) has been assigned to you.", $device_id);

            logAudit($_SESSION['user_id'], 'Insert', 'device_assignments', $pdo->lastInsertId());
            setFlashMessage('success', 'Device assigned successfully.');
            header('Location: deployments.php');
            exit();
        } catch (PDOException $e) {
            setFlashMessage('error', 'Error: ' . $e->getMessage());
        }
    } else {
        setFlashMessage('error', 'Please select both device and employee.');
    }
}

$preselectedDevice = $_GET['device'] ?? '';

// Get available devices
$availableDevices = $pdo->query("SELECT id, asset_tag, CONCAT(brand, ' ', model) as name FROM devices WHERE status = 'in_stock' ORDER BY asset_tag")->fetchAll();

// Get active employees
$employees = $pdo->query("SELECT id, full_name, CONCAT(department, ' - ', position) as dept FROM users WHERE status = 'active' AND role = 'employee' ORDER BY full_name")->fetchAll();

// Get all assignments
$stmt = $pdo->query("SELECT da.*, d.asset_tag, d.model, d.brand, u.full_name as employee_name, u.department, ub.full_name as assigned_by_name 
    FROM device_assignments da 
    JOIN devices d ON da.device_id = d.id 
    JOIN users u ON da.employee_id = u.id 
    LEFT JOIN users ub ON da.assigned_by = ub.id 
    ORDER BY da.created_at DESC");
$assignments = $stmt->fetchAll();
?>

<div class="page-header">
    <h1><i class="fas fa-hand-holding"></i> Device Deployments</h1>
    <div style="display: flex; gap: 10px;">
        <button class="btn btn-outline" onclick="exportDeploymentsCSV()"><i class="fas fa-file-csv"></i> Export CSV</button>
        <button class="btn btn-outline" onclick="exportDeploymentsPDF()"><i class="fas fa-file-pdf"></i> Export PDF</button>
        <?php if ($action == 'assign'): ?>
        <a href="deployments.php" class="btn btn-outline">View List</a>
        <?php else: ?>
        <a href="deployments.php?action=assign" class="btn btn-primary"><i class="fas fa-plus"></i> Assign Device</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($action == 'assign'): ?>
<div class="card">
    <div class="card-header"><h3>Assign Device to Employee</h3></div>
    <div class="card-body">
        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label>Select Device <span class="required">*</span></label>
                    <select name="device_id" class="form-control" required>
                        <option value="">Choose a device</option>
                        <?php foreach ($availableDevices as $dev): ?>
                        <option value="<?php echo $dev['id']; ?>" <?php echo $preselectedDevice == $dev['id'] ? 'selected' : ''; ?>><?php echo sanitize($dev['asset_tag'] . ' - ' . $dev['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Select Employee <span class="required">*</span></label>
                    <select name="employee_id" class="form-control" required>
                        <option value="">Choose an employee</option>
                        <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>"><?php echo sanitize($emp['full_name'] . ' (' . $emp['dept'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group full-width">
                    <label>Purpose / Reason</label>
                    <textarea name="purpose" class="form-control" placeholder="Purpose of assignment"></textarea>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <button type="submit" name="assign_device" class="btn btn-primary btn-lg"><i class="fas fa-hand-holding"></i> Assign Device</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header"><h3>All Deployments</h3></div>
    <div class="card-body">
        <div class="data-table-wrapper">
            <table class="data-table" id="deployTable">
                <thead>
                    <tr><th>Asset Tag</th><th>Device</th><th>Employee</th><th>Department</th><th>Assigned Date</th><th>Returned Date</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($assignments)): ?>
                    <tr><td colspan="8" class="empty-state" style="padding: 40px;"><i class="fas fa-hand-holding" style="font-size: 40px; color: #ddd;"></i><h4 style="margin-top: 10px;">No deployments yet</h4></td></tr>
                    <?php else: ?>
                    <?php foreach ($assignments as $a): ?>
                    <tr>
                        <td><strong><?php echo sanitize($a['asset_tag']); ?></strong></td>
                        <td><?php echo sanitize($a['brand'] . ' ' . $a['model']); ?></td>
                        <td><?php echo sanitize($a['employee_name']); ?></td>
                        <td><?php echo sanitize($a['department']); ?></td>
                        <td><?php echo formatDate($a['assigned_date']); ?></td>
                        <td><?php echo $a['returned_date'] ? formatDate($a['returned_date']) : '<span style="color: #999;">-</span>'; ?></td>
                        <td>
                            <span class="status-badge" style="background: <?php echo $a['status'] == 'active' ? '#3498DB20' : '#27AE6020'; ?>; color: <?php echo $a['status'] == 'active' ? '#3498DB' : '#27AE60'; ?>; border: 1px solid <?php echo $a['status'] == 'active' ? '#3498DB' : '#27AE60'; ?>;"><?php echo ucfirst($a['status']); ?></span>
                        </td>
                        <td>
                            <div class="action-btns">
                                <a href="view_device.php?id=<?php echo $a['device_id']; ?>" class="action-btn view" title="View Device"><i class="fas fa-eye"></i></a>
                                <?php if ($a['status'] == 'active'): ?>
                                <a href="deployments.php?action=return&id=<?php echo $a['id']; ?>" class="action-btn delete" title="Return Device" onclick="return confirm('Return this device?')"><i class="fas fa-undo"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function exportDeploymentsCSV() {
    const rows = [];
    document.querySelectorAll('#deployTable tbody tr').forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length > 1) {
            rows.push([cells[0]?.textContent.trim(), cells[1]?.textContent.trim(), cells[2]?.textContent.trim(), cells[3]?.textContent.trim(), cells[4]?.textContent.trim(), cells[5]?.textContent.trim(), cells[6]?.textContent.trim()]);
        }
    });
    exportToCSV('deployments_<?php echo date('Y-m-d'); ?>.csv', ['Asset Tag', 'Device', 'Employee', 'Department', 'Assigned', 'Returned', 'Status'], rows);
}
function exportDeploymentsPDF() {
    const rows = [];
    document.querySelectorAll('#deployTable tbody tr').forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length > 1) {
            rows.push([cells[0]?.textContent.trim(), cells[1]?.textContent.trim(), cells[2]?.textContent.trim(), cells[3]?.textContent.trim(), cells[4]?.textContent.trim(), cells[5]?.textContent.trim(), cells[6]?.textContent.trim()]);
        }
    });
    exportToPDF('Device Deployment Report', ['Asset Tag', 'Device', 'Employee', 'Department', 'Assigned', 'Returned', 'Status'], rows, 'deployments_<?php echo date('Y-m-d'); ?>.pdf');
}
</script>

<?php require_once 'includes/footer.php'; ?>
