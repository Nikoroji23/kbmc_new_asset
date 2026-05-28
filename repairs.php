<?php
/**
 * KBMC Asset Management - Device Repairs
 * IT/Admin manage device repairs and mark as completed
 */
$pageTitle = 'Device Repairs';
require_once 'includes/header.php';
requireITStaffOnly();

// Get pending repairs
$pendingRepairs = getPendingRepairs();
$completedRepairs = getCompletedRepairs(10);
$repairableDevices = $pdo->query("SELECT id, asset_tag, CONCAT(brand, ' ', model) as name FROM devices WHERE status IN ('deployed', 'in_stock') ORDER BY asset_tag")->fetchAll();

// Handle manual repair submission (IT staff can report)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_repair'])) {
    $device_id = $_POST['device_id'] ?? '';
    $issue_description = trim($_POST['issue_description'] ?? '');

    try {
        $stmt = $pdo->prepare("INSERT INTO device_repairs (device_id, reported_by, issue_description, repair_status, started_date) VALUES (?, ?, ?, 'under_repair', NOW())");
        $stmt->execute([$device_id, $_SESSION['user_id'], $issue_description]);

        // Update device status to under repair
        $pdo->prepare("UPDATE devices SET status = 'under_repair' WHERE id = ?")->execute([$device_id]);

        logAudit($_SESSION['user_id'], 'Create Repair Request', 'device_repairs', $pdo->lastInsertId());
        setFlashMessage('success', 'Repair request created and marked as under repair.');
        header('Location: repairs.php');
        exit();
    } catch (PDOException $e) {
        setFlashMessage('error', 'Error: ' . $e->getMessage());
    }
}

$flash = getFlashMessage();
?>

<?php if ($flash): ?>
<div class="alert <?php echo $flash['type'] === 'success' ? 'alert-success' : 'alert-error'; ?>">
    <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <span><?php echo $flash['message']; ?></span>
    <button type="button" class="alert-close" onclick="this.closest('.alert').remove();" aria-label="Close">&times;</button>
</div>
<?php endif; ?>

<div class="page-header">
    <h1><i class="fas fa-tools"></i> Device Repairs Management</h1>
    <button onclick="openRepairForm()" class="btn btn-success">
        <i class="fas fa-plus"></i> New Repair Request
    </button>
</div>

<!-- Pending Repairs - High Priority -->
<?php if (!empty($pendingRepairs)): ?>
<div class="card" style="border-left: 4px solid #e74c3c; background: #fff5f5;">
    <div class="card-header">
        <h3><i class="fas fa-exclamation-circle"></i> Pending Repairs (<strong><?php echo count($pendingRepairs); ?></strong>)</h3>
    </div>
    <div class="card-body">
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Device</th>
                        <th>Issue</th>
                        <th>Reported By</th>
                        <th>Status</th>
                        <th>Days in Repair</th>
                        <th>Severity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingRepairs as $r): 
                        $severityColor = $r['severity'] === 'critical' ? '#e74c3c' : ($r['severity'] === 'high' ? '#f39c12' : ($r['severity'] === 'medium' ? '#3498db' : '#95a5a6'));
                        $issueSnippet = strlen($r['issue_description']) > 60 ? substr($r['issue_description'], 0, 60) . '...' : $r['issue_description'];
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo sanitize($r['asset_tag']); ?></strong>
                            <div class="text-muted" style="font-size: 12px; margin-top: 4px;"><?php echo sanitize($r['model']); ?></div>
                        </td>
                        <td>
                            <div><?php echo sanitize($issueSnippet); ?></div>
                            <?php if (!empty($r['incident_report_file']) && file_exists($r['incident_report_file'])): ?>
                                <a href="<?php echo htmlspecialchars($r['incident_report_file']); ?>" target="_blank" title="View attached evidence" class="btn btn-sm btn-light" style="margin-top: 6px; display: inline-flex; align-items: center; gap: 6px;">
                                    <i class="fas fa-paperclip"></i> Evidence
                                </a>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div><?php echo sanitize($r['reporter_name']); ?></div>
                            <div class="text-muted" style="font-size: 12px; margin-top: 4px;"><?php echo sanitize($r['email']); ?></div>
                        </td>
                        <td>
                            <span class="status-badge" style="background: #fff3cd; color: #856404;"><?php echo str_replace('_', ' ', ucfirst($r['repair_status'])); ?></span>
                        </td>
                        <td><strong><?php echo $r['days_in_repair']; ?></strong> days</td>
                        <td>
                            <span class="status-badge" style="background: <?php echo $severityColor; ?>20; color: <?php echo $severityColor; ?>;"><?php echo strtoupper($r['severity'] ?? 'medium'); ?></span>
                        </td>
                        <td class="action-btns">
                            <button onclick="markRepairDone(event, <?php echo $r['id']; ?>, '<?php echo sanitize($r['asset_tag']); ?>')" class="btn btn-sm btn-success" title="Mark as Complete">
                                <i class="fas fa-check"></i>
                            </button>
                            <a href="view_device.php?id=<?php echo $r['device_id']; ?>" class="btn btn-sm btn-secondary" title="View Device">
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
<?php endif; ?>

<!-- Completed Repairs -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-check-circle"></i> Recently Completed Repairs</h3>
    </div>
    <div class="card-body">
        <?php if (empty($completedRepairs)): ?>
        <div style="text-align: center; padding: 30px; color: #7f8c8d;">
            <i class="fas fa-box" style="font-size: 30px; margin-bottom: 10px; display: block;"></i>
            <p>No completed repairs yet</p>
        </div>
        <?php else: ?>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Device</th>
                        <th>Reported By</th>
                        <th>Started</th>
                        <th>Completed</th>
                        <th>Days to Repair</th>
                        <th>Repair Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($completedRepairs as $r): ?>
                    <tr>
                        <td>
                            <strong><?php echo sanitize($r['asset_tag']); ?></strong>
                            <div class="text-muted" style="font-size: 12px; margin-top: 4px;"><?php echo sanitize($r['model']); ?></div>
                        </td>
                        <td><?php echo sanitize($r['reporter_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($r['started_date'])); ?></td>
                        <td><?php echo date('M d, Y', strtotime($r['completed_date'])); ?></td>
                        <td><strong><?php echo $r['days_to_repair']; ?></strong> days</td>
                        <td><span class="text-muted" style="font-size: 12px;"><?php echo sanitize(strlen($r['repair_notes'] ?? 'N/A') > 50 ? substr($r['repair_notes'] ?? 'N/A', 0, 50) . '...' : ($r['repair_notes'] ?? 'N/A')); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- New Repair Request Modal -->
<div id="repairFormModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3>New Repair Request</h3>
            <button type="button" class="modal-close" onclick="closeRepairForm()">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <div class="form-group">
                    <label for="deviceId">Device <span class="required">*</span></label>
                    <select name="device_id" id="deviceId" required class="form-control">
                        <option value="">— Select a device —</option>
                        <?php foreach ($repairableDevices as $rd): ?>
                        <option value="<?php echo $rd['id']; ?>"><?php echo sanitize($rd['asset_tag'] . ' - ' . $rd['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="issueDesc">Issue Description <span class="required">*</span></label>
                    <textarea name="issue_description" id="issueDesc" required class="form-control"></textarea>
                </div>

                <div class="modal-footer">
                    <button type="button" onclick="closeRepairForm()" class="btn btn-outline">Cancel</button>
                    <button type="submit" name="submit_repair" value="1" class="btn btn-primary">Create Repair Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mark Repair Done Modal -->
<div id="markDoneModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Mark Repair as Complete</h3>
            <button type="button" class="modal-close" onclick="closeMarkDone()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="repairInfo" style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                <strong>Device:</strong> <span id="repairDeviceTag"></span><br>
                <small style="color: #7f8c8d;" id="repairDeviceModel"></small>
            </div>

            <div class="form-group">
                <label for="completionNotes">Completion Notes</label>
                <textarea id="completionNotes" class="form-control" placeholder="Describe what was done to fix the device..."></textarea>
                <small class="form-hint">This will be included in the email sent to the employee</small>
            </div>

            <div style="background: #e8f4f8; padding: 12px; border-left: 3px solid #3498db; margin-bottom: 15px; border-radius: 3px;">
                <strong>Employee will be notified:</strong>
                <ul style="margin: 8px 0 0 20px; font-size: 12px; color: #555;">
                    <li>System notification when repair completes</li>
                    <li>Email with repair completion details</li>
                    <li>Device will be marked as "Deployed" and ready for use</li>
                </ul>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="closeMarkDone()" class="btn btn-outline">Cancel</button>
                <button type="button" onclick="submitRepairCompletion()" class="btn btn-success">Mark as Complete</button>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="repairIdToMark">

<script>
function openRepairForm() {
    document.getElementById('repairFormModal').style.display = 'flex';
}

function closeRepairForm() {
    document.getElementById('repairFormModal').style.display = 'none';
}

function markRepairDone(e, repairId, assetTag) {
    const row = e.target.closest('tr');
    const model = row ? (row.querySelector('small') ? row.querySelector('small').textContent : '') : '';

    document.getElementById('repairIdToMark').value = repairId;
    document.getElementById('repairDeviceTag').textContent = assetTag;
    document.getElementById('repairDeviceModel').textContent = model;
    document.getElementById('completionNotes').value = '';
    document.getElementById('markDoneModal').style.display = 'flex';
}

function closeMarkDone() {
    document.getElementById('markDoneModal').style.display = 'none';
}

function submitRepairCompletion() {
    const repairId = document.getElementById('repairIdToMark').value;
    const completionNotes = document.getElementById('completionNotes').value;
    
    if (!repairId) {
        alert('Error: Repair ID not found');
        return;
    }
    
    fetch('api_mark_repair_done.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            repair_id: parseInt(repairId),
            completion_notes: completionNotes
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closeMarkDone();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to mark repair as complete');
    });
}

// Close modals when clicking outside
document.getElementById('repairFormModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeRepairForm();
});

document.getElementById('markDoneModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeMarkDone();
});
</script>

<?php require_once 'includes/footer.php'; ?>
