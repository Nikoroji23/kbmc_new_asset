<?php
/**
 * KBMC Asset Management - Device Repairs
 */
$pageTitle = 'Device Repairs';
require_once 'includes/header.php';
requireITStaff();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $device_id = $_POST['device_id'] ?? '';
    $issue_description = trim($_POST['issue_description'] ?? '');

    try {
        $stmt = $pdo->prepare("INSERT INTO device_repairs (device_id, reported_by, issue_description, repair_status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$device_id, $_SESSION['user_id'], $issue_description]);

        // Update device status to under repair
        $pdo->prepare("UPDATE devices SET status = 'under_repair' WHERE id = ?")->execute([$device_id]);

        logAudit($_SESSION['user_id'], 'Insert', 'device_repairs', $pdo->lastInsertId());
        setFlashMessage('success', 'Repair request submitted.');
        header('Location: repairs.php');
        exit();
    } catch (PDOException $e) {
        setFlashMessage('error', 'Error: ' . $e->getMessage());
    }
}

// Update repair status
if (isset($_GET['update']) && isset($_GET['id']) && isset($_GET['status'])) {
    $repairId = $_GET['id'];
    $newStatus = $_GET['status'];
    $pdo->prepare("UPDATE device_repairs SET repair_status = ?, completed_date = " . ($newStatus == 'completed' ? 'CURDATE()' : 'NULL') . " WHERE id = ?")->execute([$newStatus, $repairId]);

    // Update device status if completed or not repairable
    if ($newStatus == 'completed') {
        $deviceId = $pdo->query("SELECT device_id FROM device_repairs WHERE id = $repairId")->fetchColumn();
        $pdo->prepare("UPDATE devices SET status = 'in_stock' WHERE id = ?")->execute([$deviceId]);
    } elseif ($newStatus == 'not_repairable') {
        $deviceId = $pdo->query("SELECT device_id FROM device_repairs WHERE id = $repairId")->fetchColumn();
        $pdo->prepare("UPDATE devices SET status = 'retired' WHERE id = ?")->execute([$deviceId]);
    }

    setFlashMessage('success', 'Repair status updated.');
    header('Location: repairs.php');
    exit();
}

$repairs = $pdo->query("SELECT dr.*, d.asset_tag, d.brand, d.model, u.full_name as reporter_name FROM device_repairs dr JOIN devices d ON dr.device_id = d.id JOIN users u ON dr.reported_by = u.id ORDER BY dr.created_at DESC")->fetchAll();
$repairableDevices = $pdo->query("SELECT id, asset_tag, CONCAT(brand, ' ', model) as name FROM devices WHERE status IN ('deployed', 'in_stock') ORDER BY asset_tag")->fetchAll();
?>

<div class="page-header">
    <h1><i class="fas fa-tools"></i> Device Repairs</h1>
</div>

<div class="card">
    <div class="card-header"><h3>Submit Repair Request</h3></div>
    <div class="card-body">
        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label>Select Device <span class="required">*</span></label>
                    <select name="device_id" class="form-control" required>
                        <option value="">Choose a device</option>
                        <?php foreach ($repairableDevices as $rd): ?>
                        <option value="<?php echo $rd['id']; ?>"><?php echo sanitize($rd['asset_tag'] . ' - ' . $rd['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group full-width">
                    <label>Issue Description <span class="required">*</span></label>
                    <textarea name="issue_description" class="form-control" placeholder="Describe the issue..." required></textarea>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Submit Repair</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3>Repair Records</h3></div>
    <div class="card-body">
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead><tr><th>Date</th><th>Asset Tag</th><th>Device</th><th>Issue</th><th>Reported By</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($repairs)): ?>
                    <tr><td colspan="7" class="empty-state" style="padding: 40px;"><h4>No repair records</h4></td></tr>
                    <?php else: ?>
                    <?php foreach ($repairs as $r): ?>
                    <tr>
                        <td><?php echo formatDate($r['created_at']); ?></td>
                        <td><strong><?php echo sanitize($r['asset_tag']); ?></strong></td>
                        <td><?php echo sanitize($r['brand'] . ' ' . $r['model']); ?></td>
                        <td><?php echo sanitize(substr($r['issue_description'], 0, 40)) . (strlen($r['issue_description']) > 40 ? '...' : ''); ?></td>
                        <td><?php echo sanitize($r['reporter_name']); ?></td>
                        <td>
                            <span class="status-badge" style="background: <?php echo $r['repair_status'] == 'completed' ? '#27AE6020' : ($r['repair_status'] == 'not_repairable' ? '#E74C3C20' : '#F39C1220'); ?>; color: <?php echo $r['repair_status'] == 'completed' ? '#27AE60' : ($r['repair_status'] == 'not_repairable' ? '#E74C3C' : '#F39C12'); ?>;"><?php echo ucwords(str_replace('_', ' ', $r['repair_status'])); ?></span>
                        </td>
                        <td>
                            <?php if ($r['repair_status'] == 'pending'): ?>
                            <a href="repairs.php?update=1&id=<?php echo $r['id']; ?>&status=under_repair" class="btn btn-warning btn-sm" onclick="return confirm('Start repair?')">Start Repair</a>
                            <?php elseif ($r['repair_status'] == 'under_repair'): ?>
                            <div class="action-btns">
                                <a href="repairs.php?update=1&id=<?php echo $r['id']; ?>&status=completed" class="action-btn assign" title="Complete" onclick="return confirm('Mark as completed?')"><i class="fas fa-check"></i></a>
                                <a href="repairs.php?update=1&id=<?php echo $r['id']; ?>&status=not_repairable" class="action-btn delete" title="Not Repairable" onclick="return confirm('Mark as not repairable?')"><i class="fas fa-times"></i></a>
                            </div>
                            <?php else: ?>
                            <span style="color: #999; font-size: 12px;">Resolved</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
