<?php
/**
 * KBMC Asset Management - Maintenance Reminders & Scheduling
 * IT/Admin manage preventive maintenance schedules and send reminders
 */

$pageTitle = 'Maintenance Reminders';
require_once 'includes/header.php';

requireITStaff();

// Handle maintenance completion
if (isset($_POST['mark_completed']) && isset($_POST['maintenance_id'])) {
    $maintenanceId = (int)$_POST['maintenance_id'];
    markMaintenanceCompleted($maintenanceId);
    setFlashMessage('success', 'Maintenance marked as completed.');
    header('Location: maintenance_reminders.php');
    exit();
}

// Handle new maintenance schedule
if (isset($_POST['create_maintenance'])) {
    $deviceId = (int)$_POST['device_id'];
    $maintenanceType = $_POST['maintenance_type'];
    $description = sanitize($_POST['description']);
    $scheduledDate = $_POST['scheduled_date'];
    $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
    
    createMaintenanceSchedule($deviceId, $maintenanceType, $description, $scheduledDate, $assignedTo);
    setFlashMessage('success', 'Maintenance schedule created. Reminders will be sent.');
    header('Location: maintenance_reminders.php');
    exit();
}

// Get upcoming maintenance
$upcomingMaintenance = getUpcomingMaintenanceReminders(30);
$allMaintenance = $pdo->query("
    SELECT ms.*, d.asset_tag, d.model, u.full_name, u.email
    FROM maintenance_schedules ms
    JOIN devices d ON ms.device_id = d.id
    LEFT JOIN users u ON ms.assigned_to = u.id
    ORDER BY ms.next_due_date ASC
    LIMIT 50
")->fetchAll();

// Get IT staff for assignment
$itStaff = $pdo->query("SELECT id, full_name, email FROM users WHERE role IN ('admin', 'it_staff') ORDER BY full_name")->fetchAll();

$flash = getFlashMessage();
?>

<?php if ($flash): ?>
<div style="background: <?php echo $flash['type'] === 'success' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $flash['type'] === 'success' ? '#155724' : '#721c24'; ?>; padding: 12px 20px; border-radius: 4px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <span><i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> <?php echo $flash['message']; ?></span>
</div>
<?php endif; ?>

<div class="page-header">
    <h1><i class="fas fa-calendar-check"></i> Maintenance Reminders</h1>
    <button onclick="openCreateSchedule()" class="btn btn-success">
        <i class="fas fa-plus"></i> Add Maintenance Schedule
    </button>
</div>

<!-- Stats -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
    <div class="card" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); color: white; padding: 20px; border-radius: 8px;">
        <div style="font-size: 28px; font-weight: bold;"><?php echo count($upcomingMaintenance); ?></div>
        <div style="font-size: 12px; opacity: 0.9;">Due in Next 30 Days</div>
    </div>
    
    <div class="card" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 20px; border-radius: 8px;">
        <div style="font-size: 28px; font-weight: bold;"><?php echo count($allMaintenance); ?></div>
        <div style="font-size: 12px; opacity: 0.9;">Total Scheduled</div>
    </div>
</div>

<?php if (!empty($upcomingMaintenance)): ?>
<div class="card" style="border-left: 4px solid #f39c12; background: #fffbf0;">
    <div class="card-header">
        <h3><i class="fas fa-exclamation-triangle"></i> Urgent - Due in Next 7 Days</h3>
    </div>
    <div class="card-body">
        <div style="overflow-x: auto;">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Device</th>
                        <th>Maintenance Type</th>
                        <th>Due Date</th>
                        <th>Assigned To</th>
                        <th>Days Until Due</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcomingMaintenance as $maint): ?>
                    <?php 
                        $dueDate = new DateTime($maint['next_due_date']);
                        $today = new DateTime();
                        $daysUntilDue = $today->diff($dueDate)->days;
                        $isOverdue = $today > $dueDate;
                    ?>
                    <tr style="<?php echo $isOverdue ? 'background: #ffe4e1;' : ''; ?>">
                        <td><strong><?php echo $maint['asset_tag']; ?></strong><br><small style="color: #7f8c8d;"><?php echo $maint['model']; ?></small></td>
                        <td><span style="background: #e8f4f8; color: #2980b9; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">
                            <?php echo str_replace('_', ' ', ucfirst($maint['maintenance_type'])); ?>
                        </span></td>
                        <td><?php echo date('M d, Y', strtotime($maint['next_due_date'])); ?></td>
                        <td><?php echo htmlspecialchars($maint['full_name'] ?? '—'); ?><br><small style="color: #7f8c8d;"><?php echo htmlspecialchars($maint['email'] ?? ''); ?></small></td>
                        <td>
                            <?php if ($isOverdue): ?>
                            <span style="background: #e74c3c; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
                                OVERDUE <?php echo abs($daysUntilDue); ?> days
                            </span>
                            <?php else: ?>
                            <span style="<?php echo $daysUntilDue <= 3 ? 'background: #e67e22; color: white;' : 'background: #f39c12; color: white;'; ?> padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold;">
                                <?php echo $daysUntilDue; ?> days
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button onclick="sendMaintenanceReminder(<?php echo $maint['id']; ?>)" class="btn btn-sm btn-primary" title="Send Email Reminder">
                                <i class="fas fa-envelope"></i>
                            </button>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="mark_completed" value="1">
                                <input type="hidden" name="maintenance_id" value="<?php echo $maint['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-success" title="Mark Completed" onclick="return confirm('Mark this maintenance as completed?');">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- All Scheduled Maintenance -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> All Maintenance Schedules</h3>
    </div>
    <div class="card-body">
        <div style="overflow-x: auto;">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Device</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Next Due</th>
                        <th>Assigned To</th>
                        <th>Last Performed</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allMaintenance as $maint): ?>
                    <tr>
                        <td><strong><?php echo $maint['asset_tag']; ?></strong></td>
                        <td><span style="background: #e8f4f8; color: #2980b9; padding: 4px 8px; border-radius: 4px; font-size: 11px;">
                            <?php echo str_replace('_', ' ', ucfirst($maint['maintenance_type'])); ?>
                        </span></td>
                        <td><?php echo htmlspecialchars(substr($maint['description'], 0, 40)); ?></td>
                        <td><?php echo date('M d, Y', strtotime($maint['next_due_date'])); ?></td>
                        <td><?php echo htmlspecialchars($maint['full_name'] ?? '—'); ?></td>
                        <td><?php echo $maint['last_performed_date'] ? date('M d, Y', strtotime($maint['last_performed_date'])) : '—'; ?></td>
                        <td>
                            <a href="view_device.php?id=<?php echo $maint['device_id']; ?>#maintenance" class="btn btn-sm btn-info">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Maintenance Schedule Modal -->
<div id="createScheduleModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 90%; max-width: 600px; background: white;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h3>Add Maintenance Schedule</h3>
            <button onclick="closeCreateSchedule()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="deviceId" style="display: block; font-weight: 600; margin-bottom: 5px;">Device *</label>
                    <select name="device_id" id="deviceId" required style="width: 100%; padding: 8px; border: 1px solid #bdc3c7; border-radius: 4px;">
                        <option value="">— Select a device —</option>
                        <?php 
                            $devices = $pdo->query("SELECT id, asset_tag, model FROM devices WHERE status != 'disposed' ORDER BY asset_tag")->fetchAll();
                            foreach ($devices as $dev):
                        ?>
                        <option value="<?php echo $dev['id']; ?>"><?php echo $dev['asset_tag'] . ' - ' . $dev['model']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="maintenanceType" style="display: block; font-weight: 600; margin-bottom: 5px;">Maintenance Type *</label>
                    <select name="maintenance_type" id="maintenanceType" required style="width: 100%; padding: 8px; border: 1px solid #bdc3c7; border-radius: 4px;">
                        <option value="preventive">Preventive</option>
                        <option value="corrective">Corrective</option>
                        <option value="calibration">Calibration</option>
                        <option value="update">Software Update</option>
                        <option value="inspection">Inspection</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="description" style="display: block; font-weight: 600; margin-bottom: 5px;">Description</label>
                    <textarea name="description" id="description" style="width: 100%; min-height: 80px; padding: 8px; border: 1px solid #bdc3c7; border-radius: 4px;"></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="scheduledDate" style="display: block; font-weight: 600; margin-bottom: 5px;">Scheduled Date *</label>
                    <input type="date" name="scheduled_date" id="scheduledDate" required style="width: 100%; padding: 8px; border: 1px solid #bdc3c7; border-radius: 4px;">
                </div>

                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="assignedTo" style="display: block; font-weight: 600; margin-bottom: 5px;">Assign To (IT Staff)</label>
                    <select name="assigned_to" id="assignedTo" style="width: 100%; padding: 8px; border: 1px solid #bdc3c7; border-radius: 4px;">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($itStaff as $staff): ?>
                        <option value="<?php echo $staff['id']; ?>"><?php echo htmlspecialchars($staff['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeCreateSchedule()" class="btn btn-outline">Cancel</button>
                    <button type="submit" name="create_maintenance" value="1" class="btn btn-primary">Create Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openCreateSchedule() {
    document.getElementById('createScheduleModal').style.display = 'flex';
}

function closeCreateSchedule() {
    document.getElementById('createScheduleModal').style.display = 'none';
}

function sendMaintenanceReminder(maintenanceId) {
    if (confirm('Send maintenance reminder email to assigned staff?')) {
        fetch('api_send_maintenance_reminder.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({maintenance_id: maintenanceId})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Reminder email sent successfully.');
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => console.error('Error:', error));
    }
}

document.getElementById('createScheduleModal').addEventListener('click', function(e) {
    if (e.target === this) closeCreateSchedule();
});
</script>

<?php require_once 'includes/footer.php'; ?>
