<?php
/**
 * KBMC Asset Management - Device Search by Serial Number
 * Search and locate devices by serial number, asset tag, brand, or model
 */

$pageTitle = 'Device Search';
require_once 'includes/header.php';

requireLogin();

$searchResults = [];
$searchTerm = '';
$noResults = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_term'])) {
    $searchTerm = sanitize($_POST['search_term']);
    if (strlen($searchTerm) >= 2) {
        $searchResults = searchDevicesBySerialOrAsset($searchTerm);
        $noResults = empty($searchResults);
    }
}
?>

<div class="page-header">
    <h1><i class="fas fa-search"></i> Device Search</h1>
    <p style="color: #7f8c8d; margin-top: 5px;">Find devices by serial number, asset tag, brand, or model</p>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST" style="display: flex; gap: 10px; margin-bottom: 20px;">
            <input type="text" name="search_term" placeholder="Enter serial #, asset tag, brand, or model..." 
                   value="<?php echo htmlspecialchars($searchTerm); ?>" 
                   style="flex: 1; padding: 10px; border: 1px solid #bdc3c7; border-radius: 4px; font-size: 14px;"
                   required>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Search
            </button>
            <?php if ($searchTerm): ?>
            <a href="device_search.php" class="btn btn-outline">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($searchTerm): ?>
    <?php if ($noResults): ?>
    <div class="card" style="text-align: center; padding: 40px; background: #ecf0f1;">
        <i class="fas fa-search" style="font-size: 40px; color: #bdc3c7; margin-bottom: 15px; display: block;"></i>
        <h4 style="color: #7f8c8d;">No Devices Found</h4>
        <p style="color: #95a5a6;">No devices match your search for "<strong><?php echo htmlspecialchars($searchTerm); ?></strong>"</p>
        <p style="color: #95a5a6; font-size: 12px;">Try searching with a different term.</p>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="card-header">
            <h3>Search Results (<strong><?php echo count($searchResults); ?></strong> device<?php echo count($searchResults) !== 1 ? 's' : ''; ?> found)</h3>
        </div>
        <div class="card-body">
            <div style="overflow-x: auto;">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Asset Tag</th>
                            <th>Serial Number</th>
                            <th>Device Type</th>
                            <th>Brand & Model</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Location</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($searchResults as $device): ?>
                        <?php 
                            $statusColor = getStatusColor($device['status']);
                            // Get current assignment
                            $stmt = $pdo->prepare("
                                SELECT u.full_name FROM device_assignments da
                                JOIN users u ON da.employee_id = u.id
                                WHERE da.device_id = ? AND da.status = 'active'
                                LIMIT 1
                            ");
                            $stmt->execute([$device['id']]);
                            $assignment = $stmt->fetch();
                            $assignedTo = $assignment ? $assignment['full_name'] : '—';
                        ?>
                        <tr>
                            <td><strong><?php echo $device['asset_tag']; ?></strong></td>
                            <td><code style="background: #ecf0f1; padding: 3px 6px; border-radius: 3px; font-size: 11px;"><?php echo $device['serial_number']; ?></code></td>
                            <td><?php echo $device['type_name']; ?></td>
                            <td><?php echo $device['brand'] . ' ' . $device['model']; ?></td>
                            <td>
                                <span class="status-badge" style="background-color: <?php echo $statusColor['color_code']; ?>20; color: <?php echo $statusColor['color_code']; ?>; border: 1px solid <?php echo $statusColor['color_code']; ?>; padding: 4px 8px; border-radius: 4px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="<?php echo $statusColor['icon_class']; ?>"></i> <?php echo $statusColor['display_label']; ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($assignedTo); ?></td>
                            <td><?php echo htmlspecialchars($device['location'] ?? '—'); ?></td>
                            <td>
                                <a href="view_device.php?id=<?php echo $device['id']; ?>" class="btn btn-sm btn-info" title="View Full Details">
                                    <i class="fas fa-eye"></i> Details
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php else: ?>
<div class="card" style="text-align: center; padding: 60px 20px; background: linear-gradient(135deg, #ecf0f1 0%, #f8f9fa 100%);">
    <i class="fas fa-magnifying-glass" style="font-size: 50px; color: #bdc3c7; margin-bottom: 20px; display: block;"></i>
    <h4 style="color: #7f8c8d; margin-bottom: 10px;">Start Searching</h4>
    <p style="color: #95a5a6; margin-bottom: 20px;">Enter a device serial number, asset tag, brand, or model name above to find devices in the system.</p>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 30px;">
        <div style="padding: 15px; background: white; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <i class="fas fa-hashtag" style="font-size: 20px; color: #3498db; margin-bottom: 8px; display: block;"></i>
            <strong>By Serial #</strong><br>
            <small style="color: #7f8c8d;">e.g., SN123456789</small>
        </div>
        
        <div style="padding: 15px; background: white; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <i class="fas fa-tag" style="font-size: 20px; color: #27ae60; margin-bottom: 8px; display: block;"></i>
            <strong>By Asset Tag</strong><br>
            <small style="color: #7f8c8d;">e.g., KBMC-LAP-001</small>
        </div>
        
        <div style="padding: 15px; background: white; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            <i class="fas fa-laptop" style="font-size: 20px; color: #f39c12; margin-bottom: 8px; display: block;"></i>
            <strong>By Brand/Model</strong><br>
            <small style="color: #7f8c8d;">e.g., Dell XPS</small>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
