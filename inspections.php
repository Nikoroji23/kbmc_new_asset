<?php
/**
 * KBMC Asset Management - Device Inspections
 */
$pageTitle = 'Device Inspections';
require_once 'includes/header.php';
requireITStaff();

$deviceId = $_GET['device'] ?? '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $inspection_device_id = $_POST['device_id'] ?? '';
    $physical_condition = $_POST['physical_condition'] ?? '';
    $functionality_status = $_POST['functionality_status'] ?? '';
    $result = $_POST['result'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $rejection_reason = trim($_POST['rejection_reason'] ?? '');

    try {
        $stmt = $pdo->prepare("INSERT INTO device_inspections (device_id, inspected_by, inspection_date, physical_condition, functionality_status, result, notes, rejection_reason) VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?)");
        $stmt->execute([$inspection_device_id, $_SESSION['user_id'], $physical_condition, $functionality_status, $result, $notes, $rejection_reason]);

        // Update device status
        $newStatus = $result == 'passed' ? 'in_stock' : 'rejected';
        $pdo->prepare("UPDATE devices SET status = ?, condition_notes = ? WHERE id = ?")->execute([$newStatus, $notes, $inspection_device_id]);

        logAudit($_SESSION['user_id'], 'Insert', 'device_inspections', $pdo->lastInsertId());
        setFlashMessage('success', 'Inspection recorded. Device is now ' . strtoupper($newStatus) . '.');
        header('Location: devices.php');
        exit();
    } catch (PDOException $e) {
        setFlashMessage('error', 'Error: ' . $e->getMessage());
    }
}

$inspections = $pdo->query("SELECT di.*, d.asset_tag, d.brand, d.model, u.full_name as inspector_name FROM device_inspections di JOIN devices d ON di.device_id = d.id JOIN users u ON di.inspected_by = u.id ORDER BY di.created_at DESC LIMIT 50")->fetchAll();
$pendingDevices = $pdo->query("SELECT id, asset_tag, CONCAT(brand, ' ', model) as name FROM devices WHERE status = 'pending_inspection' ORDER BY asset_tag")->fetchAll();
?>

<div class="page-header">
    <h1><i class="fas fa-clipboard-check"></i> Device Inspections</h1>
</div>

<div class="card">
    <div class="card-header"><h3>Record New Inspection</h3></div>
    <div class="card-body">
        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label>Select Device <span class="required">*</span></label>
                    <select name="device_id" class="form-control" required>
                        <option value="">Choose device pending inspection</option>
                        <?php foreach ($pendingDevices as $pd): ?>
                        <option value="<?php echo $pd['id']; ?>" <?php echo $deviceId == $pd['id'] ? 'selected' : ''; ?>><?php echo sanitize($pd['asset_tag'] . ' - ' . $pd['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Physical Condition <span class="required">*</span></label>
                    <select name="physical_condition" class="form-control" required>
                        <option value="excellent">Excellent</option>
                        <option value="good" selected>Good</option>
                        <option value="fair">Fair</option>
                        <option value="poor">Poor</option>
                        <option value="damaged">Damaged</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Functionality <span class="required">*</span></label>
                    <select name="functionality_status" class="form-control" required>
                        <option value="fully_functional" selected>Fully Functional</option>
                        <option value="partially_functional">Partially Functional</option>
                        <option value="not_functional">Not Functional</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Result <span class="required">*</span></label>
                    <select name="result" class="form-control" required>
                        <option value="passed" selected>Passed - Accept to Inventory</option>
                        <option value="rejected">Rejected - Return to Vendor</option>
                    </select>
                </div>
                <div class="form-group full-width">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" placeholder="Inspection observations"></textarea>
                </div>
                <div class="form-group full-width">
                    <label>Rejection Reason (if rejected)</label>
                    <textarea name="rejection_reason" class="form-control" placeholder="Why was the device rejected?"></textarea>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Record Inspection</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Recent Inspections</h3></div>
    <div class="card-body">
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead><tr><th>Date</th><th>Asset Tag</th><th>Device</th><th>Condition</th><th>Functionality</th><th>Result</th><th>Inspector</th></tr></thead>
                <tbody>
                    <?php if (empty($inspections)): ?>
                    <tr><td colspan="7" class="empty-state" style="padding: 40px;"><h4>No inspections yet</h4></td></tr>
                    <?php else: ?>
                    <?php foreach ($inspections as $i): ?>
                    <tr>
                        <td><?php echo formatDate($i['inspection_date']); ?></td>
                        <td><strong><?php echo sanitize($i['asset_tag']); ?></strong></td>
                        <td><?php echo sanitize($i['brand'] . ' ' . $i['model']); ?></td>
                        <td><?php echo ucfirst($i['physical_condition']); ?></td>
                        <td><?php echo ucwords(str_replace('_', ' ', $i['functionality_status'])); ?></td>
                        <td><?php echo getStatusBadge($i['result']); ?></td>
                        <td><?php echo sanitize($i['inspector_name']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
