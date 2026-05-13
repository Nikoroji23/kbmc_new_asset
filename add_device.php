<?php
/**
 * KBMC Asset Management - Add Device
 */
$pageTitle = 'Add New Device';
require_once 'includes/header.php';
requireITStaff();

$types = $pdo->query("SELECT * FROM device_types ORDER BY type_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $device_type_id = $_POST['device_type_id'] ?? '';
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $serial_number = trim($_POST['serial_number'] ?? '');
    $ip_address = trim($_POST['ip_address'] ?? '');
    $mac_address = trim($_POST['mac_address'] ?? '');
    $specifications = trim($_POST['specifications'] ?? '');
    $purchase_date = $_POST['purchase_date'] ?? null;
    $vendor = trim($_POST['vendor'] ?? '');
    $warranty_expiry = $_POST['warranty_expiry'] ?? null;
    $purchase_price = $_POST['purchase_price'] ?? null;
    $location = trim($_POST['location'] ?? 'IT Stock Room');
    $condition_notes = trim($_POST['condition_notes'] ?? '');

    if (empty($device_type_id) || empty($serial_number)) {
        setFlashMessage('error', 'Device type and serial number are required.');
    } else {
        try {
            $asset_tag = generateAssetTag($device_type_id);

            $stmt = $pdo->prepare("INSERT INTO devices 
                (asset_tag, device_type_id, brand, model, serial_number, ip_address, mac_address, specifications, 
                 purchase_date, vendor, warranty_expiry, purchase_price, location, condition_notes, status, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_inspection', ?)");
            $stmt->execute([$asset_tag, $device_type_id, $brand, $model, $serial_number, $ip_address, $mac_address,
                $specifications, $purchase_date, $vendor, $warranty_expiry, $purchase_price, $location, $condition_notes, $_SESSION['user_id']]);

            $deviceId = $pdo->lastInsertId();

            // Log audit
            logAudit($_SESSION['user_id'], 'Insert', 'devices', $deviceId, null, json_encode(['asset_tag' => $asset_tag, 'serial' => $serial_number]));

            // Check for low stock
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE device_type_id = ? AND status = 'in_stock'");
            $stmt->execute([$device_type_id]);
            $stockCount = $stmt->fetchColumn();
            if ($stockCount <= 2) {
                $typeName = $pdo->query("SELECT type_name FROM device_types WHERE id = $device_type_id")->fetchColumn();
                $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
                foreach ($admins as $admin) {
                    addNotification($admin['id'], 'low_stock', 'Low Stock Alert', "Only $stockCount $typeName(s) remaining in stock.", $deviceId);
                }
            }

            setFlashMessage('success', "Device added successfully with Asset Tag: $asset_tag");
            header('Location: devices.php');
            exit();
        } catch (PDOException $e) {
            setFlashMessage('error', 'Error adding device: ' . $e->getMessage());
        }
    }
}
?>

<div class="page-header">
    <h1><i class="fas fa-plus-circle"></i> Add New Device</h1>
    <a href="devices.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Devices</a>
</div>

<div class="card">
    <div class="card-header">
        <h3>Device Information</h3>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label>Device Type <span class="required">*</span></label>
                    <select name="device_type_id" class="form-control" required>
                        <option value="">Select Type</option>
                        <?php foreach ($types as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo sanitize($t['type_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Brand</label>
                    <input type="text" name="brand" class="form-control" placeholder="e.g., Dell, HP, Lenovo">
                </div>
                <div class="form-group">
                    <label>Model</label>
                    <input type="text" name="model" class="form-control" placeholder="e.g., Latitude 5520">
                </div>
                <div class="form-group">
                    <label>Serial Number <span class="required">*</span></label>
                    <input type="text" name="serial_number" class="form-control" placeholder="Enter serial number" required>
                </div>
                <div class="form-group">
                    <label>IP Address</label>
                    <input type="text" name="ip_address" class="form-control" placeholder="e.g., 192.168.1.100">
                </div>
                <div class="form-group">
                    <label>MAC Address</label>
                    <input type="text" name="mac_address" class="form-control" placeholder="e.g., AA:BB:CC:DD:EE:FF">
                </div>
                <div class="form-group">
                    <label>Purchase Date</label>
                    <input type="date" name="purchase_date" class="form-control">
                </div>
                <div class="form-group">
                    <label>Vendor</label>
                    <input type="text" name="vendor" class="form-control" placeholder="e.g., Dell Philippines">
                </div>
                <div class="form-group">
                    <label>Warranty Expiry</label>
                    <input type="date" name="warranty_expiry" class="form-control">
                </div>
                <div class="form-group">
                    <label>Purchase Price (PHP)</label>
                    <input type="number" name="purchase_price" class="form-control" placeholder="e.g., 45000" step="0.01">
                </div>
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" class="form-control" value="IT Stock Room">
                </div>
                <div class="form-group full-width">
                    <label>Specifications</label>
                    <textarea name="specifications" class="form-control" placeholder="CPU, RAM, Storage, OS, etc."></textarea>
                </div>
                <div class="form-group full-width">
                    <label>Condition Notes</label>
                    <textarea name="condition_notes" class="form-control" placeholder="Physical condition upon arrival"></textarea>
                </div>
            </div>
            <div style="margin-top: 20px; display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Save Device
                </button>
                <a href="devices.php" class="btn btn-light btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
