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
$itStaff = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('admin', 'it_staff') AND status = 'active' ORDER BY full_name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $device_type_id = $_POST['device_type_id'] ?? '';
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $serial_number = trim($_POST['serial_number'] ?? '');
    $ip_address = trim($_POST['ip_address'] ?? '');
    $pc_name = trim($_POST['pc_name'] ?? '');
    $mac_address = trim($_POST['mac_address'] ?? '');
    $specifications = trim($_POST['specifications'] ?? '');
    $purchase_date = $_POST['purchase_date'] ?: null;
    $vendor = trim($_POST['vendor'] ?? '');
    $warranty_expiry = $_POST['warranty_expiry'] ?: null;
    $purchase_price = $_POST['purchase_price'] ?: null;
    $location = trim($_POST['location'] ?? '');
    $condition_notes = trim($_POST['condition_notes'] ?? '');
    $status = $_POST['status'] ?? $device['status'];
    $new_asset_tag = trim($_POST['asset_tag'] ?? '');
    $asset_tag_changed_by = $_POST['asset_tag_changed_by'] ?? null;

    try {
        // Check if asset tag is being changed
        $assetTagChanged = false;
        if (!empty($new_asset_tag) && $new_asset_tag !== $device['asset_tag']) {
            if (!preg_match('/^[A-Za-z0-9\-_\/]{3,30}$/', $new_asset_tag)) {
                throw new Exception('Invalid asset tag format. Use 3–30 characters: letters, numbers, hyphens, underscores, or forward slash (e.g., N/A) only.');
            }
            if (empty($asset_tag_changed_by)) {
                throw new Exception('Asset tag change requires IT staff member selection. Please select who is making this change.');
            }
            
            // Allow multiple N/A entries, but prevent duplicate custom tags WITHIN SAME DEVICE TYPE
            // This allows related items (Laptop + Charger) to share the same asset tag across types
            $new_asset_tag_upper = strtoupper($new_asset_tag);
            if ($new_asset_tag_upper === 'N/A') {
                // Convert N/A to NULL to allow multiple items without asset tags
                $new_asset_tag_upper = null;
            } else {
                // Check for duplicates only within the same device type
                $chk = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE asset_tag = ? AND device_type_id = ? AND id != ?");
                $chk->execute([$new_asset_tag_upper, $device_type_id, $id]);
                if ($chk->fetchColumn() > 0) {
                    throw new Exception('Asset tag "' . htmlspecialchars($new_asset_tag_upper) . '" already exists for this device type. Please choose a different one.');
                }
            }
            $assetTagChanged = true;
        }

        $oldData = json_encode($device);
        
        // Update device
        if ($assetTagChanged) {
            $stmt = $pdo->prepare("UPDATE devices SET device_type_id=?, brand=?, model=?, serial_number=?, ip_address=?, pc_name=?, mac_address=?, specifications=?, purchase_date=?, vendor=?, warranty_expiry=?, purchase_price=?, location=?, condition_notes=?, status=?, asset_tag=? WHERE id=?");
            $stmt->execute([$device_type_id, $brand, $model, $serial_number, $ip_address, $pc_name, $mac_address, $specifications, $purchase_date, $vendor, $warranty_expiry, $purchase_price, $location, $condition_notes, $status, $new_asset_tag_upper, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE devices SET device_type_id=?, brand=?, model=?, serial_number=?, ip_address=?, pc_name=?, mac_address=?, specifications=?, purchase_date=?, vendor=?, warranty_expiry=?, purchase_price=?, location=?, condition_notes=?, status=? WHERE id=?");
            $stmt->execute([$device_type_id, $brand, $model, $serial_number, $ip_address, $pc_name, $mac_address, $specifications, $purchase_date, $vendor, $warranty_expiry, $purchase_price, $location, $condition_notes, $status, $id]);
        }

        $newData = json_encode(['serial' => $serial_number, 'status' => $status, 'ip' => $ip_address]);
        logAudit($_SESSION['user_id'], 'Update', 'devices', $id, $oldData, $newData);
        
        // Log asset tag change if it occurred
        if ($assetTagChanged) {
            $changeDetails = json_encode([
                'old_asset_tag' => $device['asset_tag'],
                'new_asset_tag' => $new_asset_tag_upper,
                'changed_by' => $_SESSION['user_id'],
                'changed_by_id' => $asset_tag_changed_by,
                'change_timestamp' => date('Y-m-d H:i:s')
            ]);
            logAudit($_SESSION['user_id'], 'Asset Tag Change', 'devices', $id, json_encode(['asset_tag' => $device['asset_tag']]), $changeDetails);
        }

        setFlashMessage('success', 'Device updated successfully.' . ($assetTagChanged ? ' Asset tag changed to ' . $new_asset_tag_upper : ''));
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
                    <div style="display: flex; gap: 10px; align-items: flex-start;">
                        <div style="flex: 1;">
                            <input type="text" name="asset_tag" class="form-control" placeholder="Leave blank to keep current" value="" maxlength="30" style="text-transform: uppercase;">
                            <small style="font-size:12px; color:#888; margin-top:4px; display:block;">
                                Leave blank to keep: <strong><?php echo sanitize($device['asset_tag']); ?></strong><br>
                                Custom: 3–30 chars, letters/numbers/hyphens/underscores/forward slash (e.g., N/A).
                            </small>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Asset Tag Changed By <span class="required" id="staffRequiredSpan" style="display:none;">*</span>
                        <span style="font-size: 11px; color: #999;">(Only required if changing asset tag)</span>
                    </label>
                    <select name="asset_tag_changed_by" id="assetTagChangedBy" class="form-control">
                        <option value="">Select IT Staff (if changing tag)</option>
                        <?php foreach ($itStaff as $staff): ?>
                        <option value="<?php echo $staff['id']; ?>"><?php echo sanitize($staff['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small style="font-size:11px; color:#aaa; margin-top:4px; display:block;" id="staffHelp" style="display:none;">
                        Required when changing the asset tag for audit tracking.
                    </small>
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
                    <label>PC Name</label>
                    <input type="text" name="pc_name" class="form-control" value="<?php echo sanitize($device['pc_name'] ?? ''); ?>">
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

<script>
// Make IT staff field conditional - only require if asset tag is actually being changed
(function() {
    const currentAssetTag = '<?php echo sanitize($device['asset_tag']); ?>';
    const assetTagInput = document.querySelector('input[name="asset_tag"]');
    const staffSelect = document.getElementById('assetTagChangedBy');
    const staffRequired = document.getElementById('staffRequiredSpan');
    const staffHelp = document.getElementById('staffHelp');
    const form = assetTagInput.closest('form');

    function updateStaffFieldRequirement() {
        const newAssetTag = assetTagInput.value.trim().toUpperCase();
        const isChanging = newAssetTag !== '' && newAssetTag !== currentAssetTag;

        if (isChanging) {
            // Asset tag is being changed - require IT staff selection
            staffSelect.required = true;
            staffRequired.style.display = 'inline';
            staffHelp.style.display = 'block';
            staffSelect.parentElement.style.opacity = '1';
        } else {
            // Asset tag is not being changed - don't require IT staff
            staffSelect.required = false;
            staffSelect.value = '';
            staffRequired.style.display = 'none';
            staffHelp.style.display = 'none';
            staffSelect.parentElement.style.opacity = '0.7';
        }
    }

    // Update on asset tag input change
    if (assetTagInput) {
        assetTagInput.addEventListener('input', updateStaffFieldRequirement);
        assetTagInput.addEventListener('change', updateStaffFieldRequirement);
    }

    // Form submission validation
    if (form) {
        form.addEventListener('submit', function(e) {
            const newAssetTag = assetTagInput.value.trim().toUpperCase();
            const isChanging = newAssetTag !== '' && newAssetTag !== currentAssetTag;
            
            if (isChanging && !staffSelect.value) {
                e.preventDefault();
                alert('Please select which IT staff member is making this asset tag change.');
                staffSelect.focus();
                return false;
            }
        });
    }

    // Initialize on page load
    updateStaffFieldRequirement();
})();
</script>

<?php require_once 'includes/footer.php'; ?>
