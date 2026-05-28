<?php
/**
 * KBMC Asset Management - Add Device
 */
$pageTitle = 'Add New Device';
require_once 'includes/header.php';
requireITStaffOnly();

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
    $purchase_date = $_POST['purchase_date'] ?? null;
    $vendor = trim($_POST['vendor'] ?? '');
    $checked_by = $_POST['checked_by'] ?? null;
    $warranty_duration = $_POST['warranty_expiry'] ?? null;
    $purchase_date_val = $_POST['purchase_date'] ?? null;
    $warranty_expiry = null;
    if ($warranty_duration && $purchase_date_val && $warranty_duration !== 'no_warranty') {
        $pd = new DateTime($purchase_date_val);
        switch ($warranty_duration) {
            case '6_months': $pd->modify('+6 months');  break;
            case '1_year':   $pd->modify('+1 year');    break;
            case '2_years':  $pd->modify('+2 years');   break;
            case '3_years':  $pd->modify('+3 years');   break;
            case '5_years':  $pd->modify('+5 years');   break;
            case 'lifetime': $pd->setDate(9999, 12, 31); break;
        }
        $warranty_expiry = $pd->format('Y-m-d');
    }
    $purchase_price = $_POST['purchase_price'] ?? null;
    $location = trim($_POST['location'] ?? 'IT Stock Room');
    $condition_notes = trim($_POST['condition_notes'] ?? '');
    $custom_asset_tag = trim($_POST['custom_asset_tag'] ?? '');

    if (empty($device_type_id) || empty($serial_number)) {
        setFlashMessage('error', 'Device type and serial number are required.');
    } else {
        try {
            if (!empty($custom_asset_tag)) {
                if (!preg_match('/^[A-Za-z0-9\-_]{3,30}$/', $custom_asset_tag)) {
                    throw new Exception('Invalid asset tag format. Use 3–30 characters: letters, numbers, hyphens, or underscores only.');
                }
                $chk = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE asset_tag = ?");
                $chk->execute([$custom_asset_tag]);
                if ($chk->fetchColumn() > 0) {
                    throw new Exception('Asset tag "' . htmlspecialchars($custom_asset_tag) . '" is already in use. Please choose a different one.');
                }
                $asset_tag = strtoupper($custom_asset_tag);
            } else {
                $asset_tag = generateAssetTag($device_type_id);
            }

            $stmt = $pdo->prepare("INSERT INTO devices 
                (asset_tag, device_type_id, brand, model, serial_number, ip_address, pc_name, mac_address, specifications, 
                 purchase_date, vendor, warranty_expiry, purchase_price, location, condition_notes, status, created_by, checked_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_inspection', ?, ?)");
            $stmt->execute([$asset_tag, $device_type_id, $brand, $model, $serial_number, $ip_address, $pc_name, $mac_address,
                $specifications, $purchase_date, $vendor, $warranty_expiry, $purchase_price, $location, $condition_notes, $_SESSION['user_id'], $checked_by]);

            $deviceId = $pdo->lastInsertId();

            logAudit($_SESSION['user_id'], 'Insert', 'devices', $deviceId, null, json_encode(['asset_tag' => $asset_tag, 'serial' => $serial_number, 'checked_by' => $checked_by]));

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
        } catch (Exception $e) {
            setFlashMessage('error', $e->getMessage());
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
        <form method="POST" id="addDeviceForm">
            <div class="form-grid">

                <div class="form-group">
                    <label>Device Type <span class="required">*</span></label>
                    <select name="device_type_id" class="form-control" required>
                        <option value="">Select Type</option>
                        <?php foreach ($types as $t): ?>
                        <option value="<?php echo $t['id']; ?>" <?php echo (($_POST['device_type_id'] ?? '') == $t['id']) ? 'selected' : ''; ?>><?php echo sanitize($t['type_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><span class="required">*</span> Asset Tag
                        <span class="asset-tag-mode-badge" id="assetTagBadge">Auto-generated</span>
                    </label>
                    <div class="asset-tag-input-row">
                        <input type="text"
                               name="custom_asset_tag"
                               id="customAssetTag"
                               class="form-control"
                               placeholder="Auto-generated on save"
                               maxlength="30"
                               value="<?php echo isset($_POST['custom_asset_tag']) ? sanitize($_POST['custom_asset_tag']) : ''; ?>"
                               disabled>
                        <button type="button" class="btn btn-outline asset-tag-toggle-btn" id="assetTagToggle">
                            <i class="fas fa-edit"></i> Customize
                        </button>
                    </div>
                    <small style="font-size:12px; color:#888; margin-top:4px; display:block;">
                        Leave blank to auto-generate. Custom: 3–30 chars, letters/numbers/hyphens/underscores only.
                    </small>
                </div>

                <div class="form-group">
                    <label>Brand</label>
                    <input type="text" name="brand" class="form-control" placeholder="e.g., Dell, HP, Lenovo" value="<?php echo sanitize($_POST['brand'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Model</label>
                    <input type="text" name="model" class="form-control" placeholder="e.g., Latitude 5520" value="<?php echo sanitize($_POST['model'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Serial Number <span class="required">*</span></label>
                    <input type="text" name="serial_number" class="form-control" placeholder="Enter serial number" value="<?php echo sanitize($_POST['serial_number'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label>IP Address <span class="required">*</span></label>
                    <input type="text" name="ip_address" class="form-control" placeholder="e.g., 192.168.1.100" value="<?php echo sanitize($_POST['ip_address'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label>PC Name</label>
                    <input type="text" name="pc_name" class="form-control" placeholder="e.g., DESKTOP-1234ABC" value="<?php echo sanitize($_POST['pc_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>MAC Address <span class="required">*</span></label>
                    <input type="text" name="mac_address" class="form-control" placeholder="e.g., AA:BB:CC:DD:EE:FF" value="<?php echo sanitize($_POST['mac_address'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label>Purchase Date <span class="required">*</span></label>
                    <input type="date" name="purchase_date" class="form-control" value="<?php echo sanitize($_POST['purchase_date'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label>Vendor <span class="required">*</span></label>
                    <input type="text" name="vendor" class="form-control" placeholder="e.g., Dell Philippines" value="<?php echo sanitize($_POST['vendor'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label>Warranty Duration <span class="required">*</span></label>
                    <select name="warranty_expiry" class="form-control" required>
                        <option value="">Select Warranty</option>
                        <option value="6_months"   <?php echo (($_POST['warranty_expiry'] ?? '') == '6_months')   ? 'selected' : ''; ?>>6 Months</option>
                        <option value="1_year"     <?php echo (($_POST['warranty_expiry'] ?? '') == '1_year')     ? 'selected' : ''; ?>>1 Year</option>
                        <option value="2_years"    <?php echo (($_POST['warranty_expiry'] ?? '') == '2_years')    ? 'selected' : ''; ?>>2 Years</option>
                        <option value="3_years"    <?php echo (($_POST['warranty_expiry'] ?? '') == '3_years')    ? 'selected' : ''; ?>>3 Years</option>
                        <option value="5_years"    <?php echo (($_POST['warranty_expiry'] ?? '') == '5_years')    ? 'selected' : ''; ?>>5 Years</option>
                        <option value="lifetime"   <?php echo (($_POST['warranty_expiry'] ?? '') == 'lifetime')   ? 'selected' : ''; ?>>Lifetime</option>
                        <option value="no_warranty"<?php echo (($_POST['warranty_expiry'] ?? '') == 'no_warranty')? 'selected' : ''; ?>>No Warranty</option>
                    </select>
                    <small style="font-size:12px; color:#888; margin-top:4px; display:block;">
                        Warranty period counted from purchase date.
                    </small>
                </div>

                <div class="form-group">
                    <label>Purchase Price (PHP) <span class="required">*</span></label>
                    <input type="number" name="purchase_price" class="form-control" placeholder="e.g., 45000" step="0.01" value="<?php echo sanitize($_POST['purchase_price'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" class="form-control" value="<?php echo sanitize($_POST['location'] ?? 'IT Stock Room'); ?>">
                </div>

                <div class="form-group">
                    <label>Checked By <span class="required">*</span></label>
                    <select name="checked_by" class="form-control" required>
                        <option value="">Select IT Staff</option>
                        <?php foreach ($itStaff as $staff): ?>
                        <option value="<?php echo $staff['id']; ?>" <?php echo (($_POST['checked_by'] ?? '') == $staff['id']) ? 'selected' : ''; ?>>
                            <?php echo sanitize($staff['full_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="font-size:12px; color:#888; margin-top:4px; display:block;">
                        IT staff who physically inspected this device upon arrival.
                    </small>
                </div>

                <div class="form-group full-width">
                    <label>Specifications</label>
                    <textarea name="specifications" class="form-control" placeholder="CPU, RAM, Storage, OS, etc."><?php echo sanitize($_POST['specifications'] ?? ''); ?></textarea>
                </div>

                <div class="form-group full-width">
                    <label>Condition Notes</label>
                    <textarea name="condition_notes" class="form-control" placeholder="Physical condition upon arrival"><?php echo sanitize($_POST['condition_notes'] ?? ''); ?></textarea>
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

<script>
(function () {
    var input  = document.getElementById('customAssetTag');
    var btn    = document.getElementById('assetTagToggle');
    var badge  = document.getElementById('assetTagBadge');

    if (!input || !btn || !badge) return;

    var isCustom = input.value.trim().length > 0;

    function applyState() {
        if (isCustom) {
            input.disabled       = false;
            input.placeholder    = 'e.g., KBMC-LT-001';
            btn.innerHTML        = '<i class="fas fa-undo"></i> Use Auto';
            btn.style.background = 'var(--kbmc-red)';
            btn.style.color      = '#fff';
            badge.textContent      = 'Custom';
            badge.style.background = '#fff3e0';
            badge.style.color      = '#e65100';
            input.focus();
        } else {
            input.disabled       = true;
            input.value          = '';
            input.placeholder    = 'Auto-generated on save';
            btn.innerHTML        = '<i class="fas fa-edit"></i> Customize';
            btn.style.background = '';
            btn.style.color      = '';
            badge.textContent      = 'Auto-generated';
            badge.style.background = '#e8f5e9';
            badge.style.color      = '#2e7d32';
        }
    }

    btn.addEventListener('click', function () {
        isCustom = !isCustom;
        applyState();
    });

    input.addEventListener('input', function () {
        var pos = this.selectionStart;
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9\-_]/g, '');
        this.setSelectionRange(pos, pos);
    });

    applyState();

    document.getElementById('addDeviceForm').addEventListener('submit', function(e) {
        if (!input.disabled) {
            if (input.value.trim() === '') {
                e.preventDefault();
                input.style.borderColor = '#e74c3c';
                input.focus();
                alert('Please enter a custom asset tag or switch back to auto-generate.');
                return;
            }
            var pattern = /^[A-Za-z0-9\-_]{3,30}$/;
            if (!pattern.test(input.value.trim())) {
                e.preventDefault();
                input.style.borderColor = '#e74c3c';
                input.focus();
                alert('Asset tag must be 3–30 characters: letters, numbers, hyphens, or underscores only.');
                return;
            }
            input.style.borderColor = '';
        }
    });

})();
</script>