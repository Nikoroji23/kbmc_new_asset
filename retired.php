<?php
/**
 * KBMC Asset Management - Retired / Disposed Devices
 */
$pageTitle = 'Retired / Disposed Devices';
require_once 'includes/header.php';
requireITStaff();

$stmt = $pdo->query("SELECT d.*, dt.type_name FROM devices d JOIN device_types dt ON d.device_type_id = dt.id WHERE d.status IN ('retired', 'disposed') ORDER BY d.updated_at DESC");
$devices = $stmt->fetchAll();
?>

<div class="page-header">
    <h1><i class="fas fa-trash-alt"></i> Retired / Disposed Devices</h1>
    <button class="btn btn-outline" onclick="exportToPDF('Retired/Disposed Devices Report', ['Asset Tag', 'Type', 'Brand/Model', 'Serial', 'Status', 'Date'], retiredRows, 'retired_devices_<?php echo date('Y-m-d'); ?>.pdf')"><i class="fas fa-file-pdf"></i> Export PDF</button>
</div>

<div class="card">
    <div class="card-body">
        <div class="data-table-wrapper">
            <table class="data-table" id="retiredTable">
                <thead><tr><th>Asset Tag</th><th>Type</th><th>Brand / Model</th><th>Serial Number</th><th>Status</th><th>Last Updated</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($devices)): ?>
                    <tr><td colspan="7" class="empty-state" style="padding: 40px;"><i class="fas fa-check-circle" style="font-size: 40px; color: #27AE60;"></i><h4>No retired/disposed devices</h4><p>All devices are active.</p></td></tr>
                    <?php else: ?>
                    <?php foreach ($devices as $dev): ?>
                    <tr>
                        <td><strong><?php echo sanitize($dev['asset_tag']); ?></strong></td>
                        <td><?php echo sanitize($dev['type_name']); ?></td>
                        <td><?php echo sanitize($dev['brand'] . ' ' . $dev['model']); ?></td>
                        <td><?php echo sanitize($dev['serial_number']); ?></td>
                        <td><?php echo getStatusBadge($dev['status']); ?></td>
                        <td><?php echo formatDate($dev['updated_at']); ?></td>
                        <td><a href="view_device.php?id=<?php echo $dev['id']; ?>" class="action-btn view"><i class="fas fa-eye"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const retiredRows = [];
document.querySelectorAll('#retiredTable tbody tr').forEach(row => {
    const cells = row.querySelectorAll('td');
    if (cells.length > 1) {
        retiredRows.push([cells[0]?.textContent.trim(), cells[1]?.textContent.trim(), cells[2]?.textContent.trim(), cells[3]?.textContent.trim(), cells[4]?.textContent.trim(), cells[5]?.textContent.trim()]);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
