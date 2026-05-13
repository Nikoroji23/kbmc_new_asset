<?php
/**
 * KBMC Asset Management - Edit Device
 */
$pageTitle = 'Edit Device';
require_once 'includes/header.php';
requireITStaff();

$id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
$stmt->execute([$id]);
$device = $stmt->fetch();

if (!$device) {
    setFlashMessage('error', 'Device not found.');
    header('Location: devices.php');
    exit();
}

$types = $pdo->query("SELECT * FROM device_types ORDER BY type_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $device_type_id = $_POST['device_type_id'] ?? '';
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $serial_number = trim($_POST['serial_number'] ?? '');
    $ip_address = trim($_POST['ip_address'] ?? '');
    $mac_address = trim($_POST['mac_address'] ?? '');
    $specifications = trim($_POST['specifications'] ?? '');
    $purchase_date = $_POST['purchase_date'] ?: null;
    $vendor = trim($_POST['vendor'] ?? '');
    $warranty_expiry = $_POST['warranty_expiry'] ?: null;
    $purchase_price = $_POST['purchase_price'] ?: null;
    $location = trim($_POST['location'] ?? '');
    $condition_notes = trim($_POST['condition_notes'] ?? '');
    $status = $_POST['status'] ?? $device['status'];

    try {
        $oldData = json_encode($device);
        $stmt = $pdo->prepare("UPDATE devices SET device_type_id=?, brand=?, model=?, serial_number=?, ip_address=?, mac_address=?, specifications=?, purchase_date=?, vendor=?, warranty_expiry=?, purchase_price=?, location=?, condition_notes=?, status=? WHERE id=?");
        $stmt->execute([$device_type_id, $brand, $model, $serial_number, $ip_address, $mac_address, $specifications, $purchase_date, $vendor, $warranty_expiry, $purchase_price, $location, $condition_notes, $status, $id]);

        $newData = json_encode(['serial' => $serial_number, 'status' => $status, 'ip' => $ip_address]);
        logAudit($_SESSION['user_id'], 'Update', 'devices', $id, $oldData, $newData);

        setFlashMessage('success', 'Device updated successfully.');
        header('Location: devices.php');
        exit();
    } catch (PDOException $e) {
        setFlashMessage('error', 'Error updating device: ' . $e->getMessage());
    }
}
?>

<div class="page-header">
    <h1><i class="fas fa-edit"></i> Edit Device</h1>
    <a href="devices.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>Edit: <?php echo sanitize($device['asset_tag']); ?></h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label>Asset Tag</label>
                    <input type="text" class="form-control" value="<?php echo sanitize($device['asset_tag']); ?>" disabled>
                </div>
                <div class="form-group">
                    <label>Device Type <span class="required">*</span></label>
                    <select name="device_type_id" class="form-control" required>
                        <?php foreach ($types as $t): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo $device['device_type_id'] == $t['id'] ? 'selected' : ''; ?>><?php echo sanitize($t['type_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Brand</label>
                    <input type="text" name="brand" class="form-control" value="<?php echo sanitize($device['brand']); ?>">
                </div>
                <div class="form-group">
                    <label>Model</label>
                    <input type="text" name="model" class="form-control" value="<?php echo sanitize($device['model']); ?>">
                </div>
                <div class="form-group">
                    <label>Serial Number <span class="required">*</span></label>
                    <input type="text" name="serial_number" class="form-control" value="<?php echo sanitize($device['serial_number']); ?>" required>
                </div>
                <div class="form-group">
                    <label>IP Address</label>
                    <input type="text" name="ip_address" class="form-control" value="<?php echo sanitize($device['ip_address']); ?>">
                </div>
                <div class="form-group">
                    <label>MAC Address</label>
                    <input type="text" name="mac_address" class="form-control" value="<?php echo sanitize($device['mac_address']); ?>">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="in_stock" <?php echo $device['status'] == 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                        <option value="deployed" <?php echo $device['status'] == 'deployed' ? 'selected' : ''; ?>>Deployed</option>
                        <option value="under_repair" <?php echo $device['status'] == 'under_repair' ? 'selected' : ''; ?>>Under Repair</option>
                        <option value="retired" <?php echo $device['status'] == 'retired' ? 'selected' : ''; ?>>Retired</option>
                        <option value="disposed" <?php echo $device['status'] == 'disposed' ? 'selected' : ''; ?>>Disposed</option>
                        <option value="pending_inspection" <?php echo $device['status'] == 'pending_inspection' ? 'selected' : ''; ?>>Pending Inspection</option>
                        <option value="rejected" <?php echo $device['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Purchase Date</label>
                    <input type="date" name="purchase_date" class="form-control" value="<?php echo $device['purchase_date']; ?>">
                </div>
                <div class="form-group">
                    <label>Vendor</label>
                    <input type="text" name="vendor" class="form-control" value="<?php echo sanitize($device['vendor']); ?>">
                </div>
                <div class="form-group">
                    <label>Warranty Expiry</label>
                    <input type="date" name="warranty_expiry" class="form-control" value="<?php echo $device['warranty_expiry']; ?>">
                </div>
                <div class="form-group">
                    <label>Purchase Price (PHP)</label>
                    <input type="number" name="purchase_price" class="form-control" value="<?php echo $device['purchase_price']; ?>" step="0.01">
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" class="form-control" value="<?php echo sanitize($device['location']); ?>">
                </div>
                <div class="form-group full-width">
                    <label>Specifications</label>
                    <textarea name="specifications" class="form-control"><?php echo sanitize($device['specifications']); ?></textarea>
                </div>
                <div class="form-group full-width">
                    <label>Condition Notes</label>
                    <textarea name="condition_notes" class="form-control"><?php echo sanitize($device['condition_notes']); ?></textarea>
                </div>
            </div>
            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Update Device</button>
                <a href="devices.php" class="btn btn-light btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
