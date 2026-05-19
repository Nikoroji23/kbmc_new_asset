<?php
/**
 * KBMC Asset Management - User Asset Dashboard
 * Shows employees their assigned devices with status, maintenance alerts, and repair history
 */

$pageTitle = 'My Devices';
require_once 'includes/header.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['email'];

// Get employee's assigned devices
$assignedDevices = getEmployeeAssignedDevices($userId);
$stats = getEmployeeDeviceStats($userId);
?>

<div class="page-header">
    <h1><i class="fas fa-laptop-house"></i> My Assigned Devices</h1>
</div>

<!-- Stats Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
    <div class="card" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 20px; border-radius: 8px;">
        <div style="font-size: 28px; font-weight: bold;"><?php echo $stats['total_devices'] ?? 0; ?></div>
        <div style="font-size: 12px; opacity: 0.9;">Total Devices Assigned</div>
    </div>
    
    <div class="card" style="background: linear-gradient(135deg, #27ae60 0%, #229954 100%); color: white; padding: 20px; border-radius: 8px;">
        <div style="font-size: 28px; font-weight: bold;"><?php echo $stats['active_devices'] ?? 0; ?></div>
        <div style="font-size: 12px; opacity: 0.9;">Active & Functional</div>
    </div>
    
    <div class="card" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); color: white; padding: 20px; border-radius: 8px;">
        <div style="font-size: 28px; font-weight: bold;"><?php echo $stats['devices_under_repair'] ?? 0; ?></div>
        <div style="font-size: 12px; opacity: 0.9;">Under Repair</div>
    </div>
    
    <div class="card" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; padding: 20px; border-radius: 8px;">
        <div style="font-size: 28px; font-weight: bold;"><?php echo $stats['pending_repairs'] ?? 0; ?></div>
        <div style="font-size: 12px; opacity: 0.9;">Pending Repairs</div>
    </div>
</div>

<?php if (!empty($assignedDevices)): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Your Devices</h3>
    </div>
    <div class="card-body">
        <div style="overflow-x: auto;">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Asset Tag</th>
                        <th>Device Type</th>
                        <th>Brand & Model</th>
                        <th>Status</th>
                        <th>Assigned Date</th>
                        <th>Maintenance Due</th>
                        <th>Repairs Pending</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignedDevices as $device): ?>
                    <?php $statusColor = getStatusColor($device['status']); ?>
                    <tr>
                        <td><strong><?php echo $device['asset_tag']; ?></strong></td>
                        <td><?php echo $device['type_name']; ?></td>
                        <td><?php echo $device['brand'] . ' ' . $device['model']; ?></td>
                        <td>
                            <span class="status-badge" style="background-color: <?php echo $statusColor['color_code']; ?>20; color: <?php echo $statusColor['color_code']; ?>; border: 1px solid <?php echo $statusColor['color_code']; ?>; padding: 4px 8px; border-radius: 4px; font-size: 11px; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="<?php echo $statusColor['icon_class']; ?>"></i> <?php echo $statusColor['display_label']; ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($device['assigned_date'])); ?></td>
                        <td>
                            <?php if ($device['upcoming_maintenance'] > 0): ?>
                            <span style="background: #f39c12; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo $device['upcoming_maintenance']; ?> Due
                            </span>
                            <?php else: ?>
                            <span style="color: #27ae60; font-size: 11px;">✓ On Schedule</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($device['pending_repairs'] > 0): ?>
                            <span style="background: #e74c3c; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
                                <i class="fas fa-wrench"></i> <?php echo $device['pending_repairs']; ?>
                            </span>
                            <?php else: ?>
                            <span style="color: #95a5a6; font-size: 11px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="view_device.php?id=<?php echo $device['id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>
                            <button onclick="reportIssue(<?php echo $device['id']; ?>, '<?php echo $device['asset_tag']; ?>')" class="btn btn-sm btn-warning" title="Report Issue">
                                <i class="fas fa-exclamation-circle"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body" style="text-align: center; padding: 50px;">
        <i class="fas fa-inbox" style="font-size: 40px; color: #bdc3c7; margin-bottom: 15px; display: block;"></i>
        <h4 style="color: #7f8c8d;">No Devices Assigned Yet</h4>
        <p style="color: #95a5a6;">You don't have any devices assigned to you. Contact IT if you need a device.</p>
    </div>
</div>
<?php endif; ?>

<!-- Report Issue Modal -->
<div id="reportIssueModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 90%; max-width: 500px; background: white;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3>Report Device Issue</h3>
            <button onclick="closeReportIssue()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <div class="card-body">
            <form id="reportForm" method="POST" enctype="multipart/form-data">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="deviceTag" style="display: block; font-weight: 600; margin-bottom: 5px;">Device</label>
                    <input type="text" id="deviceTag" readonly style="background: #ecf0f1; padding: 8px; border: 1px solid #bdc3c7; border-radius: 4px; width: 100%;">
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="issueDescription" style="display: block; font-weight: 600; margin-bottom: 5px;">Issue Description *</label>
                    <textarea id="issueDescription" name="issue_description" required style="width: 100%; min-height: 120px; padding: 8px; border: 1px solid #bdc3c7; border-radius: 4px; font-family: Arial, sans-serif;"></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="severity" style="display: block; font-weight: 600; margin-bottom: 5px;">Severity Level</label>
                    <select id="severity" name="severity" style="width: 100%; padding: 8px; border: 1px solid #bdc3c7; border-radius: 4px;">
                        <option value="low">Low - Device works but has issues</option>
                        <option value="medium" selected>Medium - Device is problematic</option>
                        <option value="high">High - Device barely functional</option>
                        <option value="critical">Critical - Device not working at all</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="attachment" style="display: block; font-weight: 600; margin-bottom: 5px;">Attach Evidence (optional)</label>
                    <input type="file" id="attachment" name="attachment" accept="image/*,.pdf" style="padding: 8px; border: 1px solid #bdc3c7; border-radius: 4px; width: 100%;">
                    <small style="color: #7f8c8d;">Max 5MB. Images or PDF only.</small>
                </div>

                <input type="hidden" id="deviceId" name="device_id">

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeReportIssue()" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-primary">Report Issue</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function reportIssue(deviceId, assetTag) {
    document.getElementById('deviceId').value = deviceId;
    document.getElementById('deviceTag').value = assetTag;
    document.getElementById('reportForm').reset();
    document.getElementById('reportIssueModal').style.display = 'flex';
}

function closeReportIssue() {
    document.getElementById('reportIssueModal').style.display = 'none';
}

document.getElementById('reportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    fetch('api_report_device_issue.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Issue reported successfully. IT team has been notified.');
            closeReportIssue();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to report issue.');
    });
});

// Close modal when clicking outside
document.getElementById('reportIssueModal').addEventListener('click', function(e) {
    if (e.target === this) closeReportIssue();
});
</script>

<?php require_once 'includes/footer.php'; ?>
