<?php
/**
 * KBMC Asset Management - All Devices
 */
$pageTitle = 'All Devices';
require_once 'includes/header.php';
requireITStaffOnly();

$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';
$assetTagFilter = $_GET['asset_tag'] ?? '';
$pcNameFilter = $_GET['pc_name'] ?? '';
$ipAddressFilter = $_GET['ip_address'] ?? '';
$assignedToFilter = $_GET['assigned_to'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT d.*, dt.type_name, u.id AS assigned_user_id, u.full_name as assigned_to, 
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
if ($assetTagFilter) { $sql .= " AND d.asset_tag LIKE ?"; $params[] = "%$assetTagFilter%"; }
if ($pcNameFilter) { $sql .= " AND d.pc_name LIKE ?"; $params[] = "%$pcNameFilter%"; }
if ($ipAddressFilter) { $sql .= " AND d.ip_address LIKE ?"; $params[] = "%$ipAddressFilter%"; }
if ($assignedToFilter) { $sql .= " AND u.full_name LIKE ?"; $params[] = "%$assignedToFilter%"; }
if ($search) { $sql .= " AND (d.asset_tag LIKE ? OR d.pc_name LIKE ? OR d.ip_address LIKE ? OR dt.type_name LIKE ? OR u.full_name LIKE ?)"; 
    $s = "%$search%"; $params = array_merge($params, [$s, $s, $s, $s, $s]); }

$sql .= " ORDER BY d.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$devices = $stmt->fetchAll();

$types = $pdo->query("SELECT * FROM device_types ORDER BY type_name")->fetchAll();
$assetTags = $pdo->query("SELECT DISTINCT asset_tag FROM devices ORDER BY asset_tag")->fetchAll(PDO::FETCH_COLUMN);
$pcNames = $pdo->query("SELECT DISTINCT pc_name FROM devices WHERE pc_name IS NOT NULL AND pc_name <> '' ORDER BY pc_name")->fetchAll(PDO::FETCH_COLUMN);
$ipAddresses = $pdo->query("SELECT DISTINCT ip_address FROM devices WHERE ip_address IS NOT NULL AND ip_address <> '' ORDER BY ip_address")->fetchAll(PDO::FETCH_COLUMN);
$assignedUsers = $pdo->query("SELECT DISTINCT u.full_name FROM devices d LEFT JOIN device_assignments da ON d.id = da.device_id AND da.status = 'active' LEFT JOIN users u ON da.employee_id = u.id WHERE u.full_name IS NOT NULL AND u.full_name <> '' ORDER BY u.full_name")->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
    <h1><i class="fas fa-laptop"></i> All Devices</h1>
    <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
        <a href="import_assets.php" class="btn" style="background-color: #27ae60; color: white; padding: 8px 16px; border-radius: 4px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px;">
            <i class="fas fa-file-import"></i> Import Assets
        </a>
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

<form method="GET" id="deviceFilterForm">
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body">
        <div class="filter-row" style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
            <input type="text" name="search" placeholder="Search asset tag, PC name, IP, type or assigned..." value="<?php echo sanitize($search); ?>" style="flex: 1; min-width: 220px; padding: 10px 12px; border: 1px solid #d6d8db; border-radius: 8px;">
            <select name="type" class="header-filter-select" style="min-width: 170px;">
                <option value="">All Types</option>
                <?php foreach ($types as $t): ?>
                <option value="<?php echo $t['id']; ?>" <?php echo $type == $t['id'] ? 'selected' : ''; ?>><?php echo sanitize($t['type_name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" class="header-filter-select" style="min-width: 170px;">
                <option value="">All Statuses</option>
                <option value="in_stock" <?php echo $status == 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                <option value="deployed" <?php echo $status == 'deployed' ? 'selected' : ''; ?>>Deployed</option>
                <option value="under_repair" <?php echo $status == 'under_repair' ? 'selected' : ''; ?>>Under Repair</option>
                <option value="retired" <?php echo $status == 'retired' ? 'selected' : ''; ?>>Retired</option>
                <option value="disposed" <?php echo $status == 'disposed' ? 'selected' : ''; ?>>Disposed</option>
                <option value="pending_inspection" <?php echo $status == 'pending_inspection' ? 'selected' : ''; ?>>Pending Inspection</option>
                <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
            <select name="assigned_to" class="header-filter-select" style="min-width: 180px;">
                <option value="">All Assignees</option>
                <?php foreach ($assignedUsers as $assignee): ?>
                <option value="<?php echo sanitize($assignee); ?>" <?php echo $assignedToFilter == $assignee ? 'selected' : ''; ?>><?php echo sanitize($assignee); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filter</button>
            <a href="devices.php" class="btn btn-light btn-sm"><i class="fas fa-undo"></i> Reset</a>
        </div>
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
                        <th>PC Name</th>
                        <th>Type</th>
                        <th>IP Address</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($devices)): ?>
                    <tr>
                        <td colspan="7" class="empty-state" style="padding: 40px;">
                            <i class="fas fa-search" style="font-size: 40px; color: #ddd;"></i>
                            <h4 style="margin-top: 10px;">No devices found</h4>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($devices as $dev): ?>
                    <tr>
                        <td><strong><?php echo sanitize($dev['asset_tag']); ?></strong></td>
                        <td><?php echo sanitize($dev['pc_name'] ?: 'N/A'); ?></td>
                        <td><?php echo sanitize($dev['type_name']); ?></td>
                        <td><?php echo sanitize($dev['ip_address'] ?: 'N/A'); ?></td>
                        <td><?php echo getStatusBadge($dev['status']); ?></td>
                        <td>
                            <?php if ($dev['assigned_to']): ?>
                            <button type="button" class="btn btn-link view-assignee-btn" data-user-id="<?php echo $dev['assigned_user_id']; ?>" style="padding:0; margin:0; color:#007bff; display:flex; align-items:center; gap:6px; font-size: 0.95rem;">
                                <i class="fas fa-user" style="font-size: 11px; color: #999;"></i>
                                <?php echo sanitize($dev['assigned_to']); ?>
                            </button>
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
</form>

<!-- Assigned User Details Modal -->
<div id="assignedUserModal" class="modal-overlay" style="display:none;">
    <div class="modal-box" style="max-width: 900px; width: 95%;">
        <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
            <h3><i class="fas fa-user"></i> Assigned User Details</h3>
            <div style="display:flex;gap:8px;align-items:center;">
                <button type="button" class="btn btn-outline no-print" onclick="printAssignedUserDetails()"><i class="fas fa-print"></i> Print</button>
                <button type="button" class="modal-close btn btn-outline no-print" onclick="closeAssignedUserModal()">&times;</button>
            </div>
        </div>
        <div class="modal-body" id="assignedUserBody">
            <p style="text-align:center;color:#999;padding:30px;"><i class="fas fa-spinner fa-spin"></i> Loading...</p>
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
                cells[5]?.textContent.trim() || ''
            ]);
        }
    });
    exportToCSV('devices_<?php echo date('Y-m-d'); ?>.csv',
        ['Asset Tag', 'PC Name', 'Type', 'IP Address', 'Status', 'Assigned To'],
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
                cells[5]?.textContent.trim() || ''
            ]);
        }
    });
    exportToPDF('Device Inventory Report',
        ['Asset Tag', 'PC Name', 'Type', 'IP', 'Status', 'Assigned'],
        rows,
        'devices_report_<?php echo date('Y-m-d'); ?>.pdf'
    );
}

function closeAssignedUserModal() {
    document.getElementById('assignedUserModal').style.display = 'none';
}

function printAssignedUserDetails() {
    var content = document.getElementById('assignedUserBody').innerHTML;
    var printWindow = window.open('', '', 'width=1000,height=800');
    printWindow.document.write('<!DOCTYPE html><html><head><title>Print User Details</title>');
    printWindow.document.write('<style>body{font-family:Arial,sans-serif;padding:24px;color:#222;} h1,h2,h3{margin:0 0 .75rem;} table{width:100%;border-collapse:collapse;margin-top:1rem;} th,td{border:1px solid #ccc;padding:10px;text-align:left;} th{background:#f4f4f4;} .status-badge{display:inline-block;padding:4px 8px;border-radius:4px;background:#f0f0f0;color:#333;font-size:12px;} .section-title{margin-top:20px;margin-bottom:10px;font-size:18px;}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<h1>Assigned User Details</h1>');
    printWindow.document.write(content);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.focus();
    printWindow.print();
}

function setupAssignedUserButtons() {
    document.querySelectorAll('.view-assignee-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var userId = this.dataset.userId;
            var body = document.getElementById('assignedUserBody');
            body.innerHTML = '<p style="text-align:center;color:#999;padding:30px;"><i class="fas fa-spinner fa-spin"></i> Loading...</p>';
            document.getElementById('assignedUserModal').style.display = 'flex';

            fetch('users.php?view_user=' + userId, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                var u = data.user;
                var assets = data.assets;
                var assetsHtml = assets.length === 0
                    ? '<p style="color:#999;text-align:center;padding:20px 0;"><i class="fas fa-box-open" style="font-size:28px;display:block;margin-bottom:8px;"></i>No devices currently assigned to this user.</p>'
                    : '<table class="data-table" style="width:100%;margin-top:15px;"><thead><tr>'
                        + '<th>Asset Tag</th><th>PC Name</th><th>IP Address</th><th>Name</th><th>Category</th><th>Status</th><th>Assigned At</th>'
                        + '</tr></thead><tbody>'
                        + assets.map(function(a) {
                            return '<tr>'
                                + '<td><strong>' + (a.asset_tag || 'N/A') + '</strong></td>'
                                + '<td>' + (a.pc_name || 'N/A') + '</td>'
                                + '<td>' + (a.ip_address || 'N/A') + '</td>'
                                + '<td>' + (a.name || 'N/A') + '</td>'
                                + '<td>' + (a.category || 'N/A') + '</td>'
                                + '<td><span class="status-badge">' + (a.status || 'N/A') + '</span></td>'
                                + '<td>' + (a.assigned_at || 'N/A') + '</td>'
                                + '</tr>';
                        }).join('')
                        + '</tbody></table>';

                body.innerHTML = '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:20px;">'
                    + '<div><strong>Employee ID</strong><div>' + (u.employee_id || 'N/A') + '</div></div>'
                    + '<div><strong>Full Name</strong><div>' + (u.full_name || 'N/A') + '</div></div>'
                    + '<div><strong>Email</strong><div>' + (u.email || 'N/A') + '</div></div>'
                    + '<div><strong>Department</strong><div>' + (u.department || 'N/A') + '</div></div>'
                    + '<div><strong>Position</strong><div>' + (u.position || 'N/A') + '</div></div>'
                    + '<div><strong>Status</strong><div>' + (u.status ? '<span class="status-badge">' + u.status.charAt(0).toUpperCase() + u.status.slice(1) + '</span>' : 'N/A') + '</div></div>'
                    + '<div><strong>IP Address</strong><div>' + (assets.find(a => a.ip_address && a.ip_address !== 'N/A')?.ip_address || 'N/A') + '</div></div>'
                    + '<div><strong>PC Name</strong><div>' + (assets.find(a => a.pc_name && a.pc_name !== 'N/A')?.pc_name || 'N/A') + '</div></div>'
                    + '<h4 class="section-title">Assigned Devices</h4>'
                    + '</div>'
                    + assetsHtml;
            })
            .catch(function() {
                body.innerHTML = '<p style="color:#e74c3c;text-align:center;padding:30px;"><i class="fas fa-exclamation-circle"></i> Failed to load user details. Please try again.</p>';
            });
        });
    });
}

function enableDeviceFilterEnter() {
    var filterForm = document.getElementById('deviceFilterForm');
    if (!filterForm) return;

    filterForm.addEventListener('keydown', function(event) {
        if (event.key === 'Enter') {
            var target = event.target;
            if (target && target.tagName === 'INPUT' && target.name === 'search') {
                event.preventDefault();
                filterForm.submit();
            }
        }
    });
}

window.addEventListener('DOMContentLoaded', function() {
    setupAssignedUserButtons();
    enableDeviceFilterEnter();
});

</script>

<?php require_once 'includes/footer.php'; ?>
