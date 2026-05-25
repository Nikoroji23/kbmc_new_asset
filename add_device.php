<?php
/**
 * KBMC Asset Management - Add Device
 */
$pageTitle = 'Add New Device';
require_once 'includes/header.php';
requireITStaffOnly();

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
    $custom_asset_tag = trim($_POST['custom_asset_tag'] ?? ''); // NEW

    if (empty($device_type_id) || empty($serial_number)) {
        setFlashMessage('error', 'Device type and serial number are required.');
    } else {
        try {
            // NEW: use custom tag if provided, otherwise auto-generate
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
        } catch (Exception $e) { // NEW: catches custom tag validation errors
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

                <!-- NEW: Asset Tag field with customize toggle -->
                <div class="form-group">
                    <label>
                        Asset Tag
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
                <!-- END NEW -->

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

<!-- NEW: Asset Tag toggle — inline so it runs after its own elements, no timing issues -->
<script>
(function () {
    var input  = document.getElementById('customAssetTag');
    var btn    = document.getElementById('assetTagToggle');
    var badge  = document.getElementById('assetTagBadge');

    if (!input || !btn || !badge) return;

    // Restore custom mode on POST-back if a value was submitted
    var isCustom = input.value.trim().length > 0;

    function applyState() {
        if (isCustom) {
            input.disabled    = false;
            input.placeholder = 'e.g., KBMC-LT-001';
            btn.innerHTML     = '<i class="fas fa-undo"></i> Use Auto';
            btn.style.background = 'var(--kbmc-red)';
            btn.style.color      = '#fff';
            badge.textContent    = 'Custom';
            badge.style.background = '#fff3e0';
            badge.style.color      = '#e65100';
            input.focus();
        } else {
            input.disabled    = true;
            input.value       = '';
            input.placeholder = 'Auto-generated on save';
            btn.innerHTML     = '<i class="fas fa-edit"></i> Customize';
            btn.style.background = '';
            btn.style.color      = '';
            badge.textContent    = 'Auto-generated';
            badge.style.background = '#e8f5e9';
            badge.style.color      = '#2e7d32';
        }
    }

    btn.addEventListener('click', function () {
        isCustom = !isCustom;
        applyState();
    });

    input.addEventListener('input', function () {
        var pos  = this.selectionStart; 
        this.value = this.value.toUpperCase().replace(/[^A-Z0-9\-_]/g, '');
        this.setSelectionRange(pos, pos);
    });

    applyState();
})();
</script>