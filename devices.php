<?php
/**
 * KBMC Asset Management - All Devices
 */
$pageTitle = 'All Devices';
require_once 'includes/header.php';
requireITStaff();

$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT d.*, dt.type_name, u.full_name as assigned_to, 
        (SELECT full_name FROM users WHERE id = da.assigned_by) as assigned_by_name,
        da.assigned_date, da.status as assignment_status, da.id as assignment_id
        FROM devices d 
        JOIN device_types dt ON d.device_type_id = dt.id 
        LEFT JOIN device_assignments da ON d.id = da.device_id AND da.status = 'active'
        LEFT JOIN users u ON da.employee_id = u.id
        WHERE 1=1";
$params = [];

if ($status) { $sql .= " AND d.status = ?"; $params[] = $status; }
if ($type) { $sql .= " AND d.device_type_id = ?"; $params[] = $type; }
if ($search) { $sql .= " AND (d.asset_tag LIKE ? OR d.serial_number LIKE ? OR d.model LIKE ? OR d.brand LIKE ? OR d.ip_address LIKE ?)"; 
    $s = "%$search%"; $params = array_merge($params, [$s, $s, $s, $s, $s]); }

$sql .= " ORDER BY d.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$devices = $stmt->fetchAll();

$types = $pdo->query("SELECT * FROM device_types ORDER BY type_name")->fetchAll();
?>

<div class="page-header">
    <h1><i class="fas fa-laptop"></i> All Devices</h1>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button class="btn btn-outline" onclick="exportDevicesCSV()">
            <i class="fas fa-file-csv"></i> Export CSV
        </button>
        <button class="btn btn-outline" onclick="exportDevicesPDF()">
            <i class="fas fa-file-pdf"></i> Export PDF
        </button>
        <a href="add_device.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Device
        </a>
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body">
        <form method="GET" class="filter-row">
            <input type="text" name="search" placeholder="Search by asset tag, serial, model, IP..." value="<?php echo sanitize($search); ?>" style="flex: 1; min-width: 200px;">
            <select name="status">
                <option value="">All Status</option>
                <option value="in_stock" <?php echo $status == 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                <option value="deployed" <?php echo $status == 'deployed' ? 'selected' : ''; ?>>Deployed</option>
                <option value="under_repair" <?php echo $status == 'under_repair' ? 'selected' : ''; ?>>Under Repair</option>
                <option value="retired" <?php echo $status == 'retired' ? 'selected' : ''; ?>>Retired</option>
                <option value="disposed" <?php echo $status == 'disposed' ? 'selected' : ''; ?>>Disposed</option>
                <option value="pending_inspection" <?php echo $status == 'pending_inspection' ? 'selected' : ''; ?>>Pending Inspection</option>
                <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
            <select name="type">
                <option value="">All Types</option>
                <?php foreach ($types as $t): ?>
                <option value="<?php echo $t['id']; ?>" <?php echo $type == $t['id'] ? 'selected' : ''; ?>><?php echo sanitize($t['type_name']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
            <a href="devices.php" class="btn btn-light btn-sm"><i class="fas fa-undo"></i> Reset</a>
        </form>
    </div>
</div>

<!-- Devices Table -->
<div class="card">
    <div class="card-body">
        <div class="data-table-wrapper">
            <table class="data-table" id="devicesTable">
                <thead>
                    <tr>
                        <th>Asset Tag</th>
                        <th>Type</th>
                        <th>Brand / Model</th>
                        <th>Serial Number</th>
                        <th>IP Address</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($devices)): ?>
                    <tr>
                        <td colspan="8" class="empty-state" style="padding: 40px;">
                            <i class="fas fa-search" style="font-size: 40px; color: #ddd;"></i>
                            <h4 style="margin-top: 10px;">No devices found</h4>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($devices as $dev): ?>
                    <tr>
                        <td><strong><?php echo sanitize($dev['asset_tag']); ?></strong></td>
                        <td><?php echo sanitize($dev['type_name']); ?></td>
                        <td><?php echo sanitize($dev['brand'] . ' ' . $dev['model']); ?></td>
                        <td><?php echo sanitize($dev['serial_number']); ?></td>
                        <td><?php echo sanitize($dev['ip_address'] ?: 'N/A'); ?></td>
                        <td><?php echo getStatusBadge($dev['status']); ?></td>
                        <td>
                            <?php if ($dev['assigned_to']): ?>
                            <span style="display: flex; align-items: center; gap: 5px;">
                                <i class="fas fa-user" style="font-size: 11px; color: #999;"></i>
                                <?php echo sanitize($dev['assigned_to']); ?>
                            </span>
                            <?php else: ?>
                            <span style="color: #999; font-size: 12px;">Unassigned</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-btns">
                                <a href="view_device.php?id=<?php echo $dev['id']; ?>" class="action-btn view" title="View Details"><i class="fas fa-eye"></i></a>
                                <a href="edit_device.php?id=<?php echo $dev['id']; ?>" class="action-btn edit" title="Edit"><i class="fas fa-edit"></i></a>
                                <?php if ($dev['status'] == 'in_stock'): ?>
                                <a href="deployments.php?action=assign&device=<?php echo $dev['id']; ?>" class="action-btn assign" title="Assign"><i class="fas fa-hand-holding"></i></a>
                                <?php endif; ?>
                                <a href="delete_device.php?id=<?php echo $dev['id']; ?>" class="action-btn delete delete-confirm" title="Delete"><i class="fas fa-trash"></i></a>
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
function exportDevicesCSV() {
    const rows = [];
    document.querySelectorAll('#devicesTable tbody tr').forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length > 1) {
            rows.push([
                cells[0]?.textContent.trim() || '',
                cells[1]?.textContent.trim() || '',
                cells[2]?.textContent.trim() || '',
                cells[3]?.textContent.trim() || '',
                cells[4]?.textContent.trim() || '',
                cells[5]?.textContent.trim() || '',
                cells[6]?.textContent.trim() || ''
            ]);
        }
    });
    exportToCSV('devices_<?php echo date('Y-m-d'); ?>.csv',
        ['Asset Tag', 'Type', 'Brand/Model', 'Serial Number', 'IP Address', 'Status', 'Assigned To'],
        rows
    );
}

function exportDevicesPDF() {
    const rows = [];
    document.querySelectorAll('#devicesTable tbody tr').forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length > 1) {
            rows.push([
                cells[0]?.textContent.trim() || '',
                cells[1]?.textContent.trim() || '',
                cells[2]?.textContent.trim() || '',
                cells[3]?.textContent.trim() || '',
                cells[4]?.textContent.trim() || '',
                cells[5]?.textContent.trim() || '',
                cells[6]?.textContent.trim() || ''
            ]);
        }
    });
    exportToPDF('Device Inventory Report',
        ['Asset Tag', 'Type', 'Brand/Model', 'Serial', 'IP', 'Status', 'Assigned'],
        rows,
        'devices_report_<?php echo date('Y-m-d'); ?>.pdf'
    );
}
</script>

<?php require_once 'includes/footer.php'; ?>
