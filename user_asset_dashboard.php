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

// Get current active assignments for voluntary return buttons
$activeAssignments = [];
if (!empty($assignedDevices)) {
    $placeholders = implode(',', array_fill(0, count($assignedDevices), '?'));
    $ids = array_column($assignedDevices, 'id');
    $stmt = $pdo->prepare("SELECT id, device_id FROM device_assignments WHERE id IN ($placeholders) AND status = 'active'");
    $stmt->execute($ids);
    $activeAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $activeAssignmentMap = array_column($activeAssignments, 'id', 'device_id');
}
?>

<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
        <div>
            <h1><i class="fas fa-laptop-house"></i> My Assigned Devices</h1>
            <p style="color: #7f8c8d; font-size: 14px; margin-top: 8px;">Manage your assigned devices and request returns</p>
        </div>
        <button onclick="window.print()" class="btn btn-outline" title="Print or save as PDF" style="display: flex; align-items: center; gap: 8px; padding: 10px 15px; border-radius: 6px;">
            <i class="fas fa-print"></i> Print / PDF
        </button>
    </div>
</div>

<style>
/* Print Styles */
@media print {
    body, html {
        background: white;
        margin: 0;
        padding: 10px;
    }
    
    .sidebar, .page-header button, .btn:not(.print-only), .action-btn {
        display: none !important;
    }
    
    .page-header {
        margin-bottom: 30px;
        border-bottom: 3px solid #333;
        padding-bottom: 15px;
        display: flex;
        justify-content: space-between;
    }
    
    .page-header h1 {
        font-size: 24px;
        margin: 0;
        color: #000;
        font-weight: bold;
    }
    
    .page-header p {
        display: none;
    }
    
    /* Stats cards in print */
    .stat-card {
        border: 2px solid #333 !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }
    
    .card {
        box-shadow: none !important;
        border: 2px solid #333 !important;
        page-break-inside: avoid;
        margin-bottom: 20px;
    }
    
    .card-header {
        background: #f5f5f5;
        border-bottom: 2px solid #333;
        padding: 12px;
        font-weight: bold;
    }
    
    .card-body {
        padding: 0 !important;
    }
    
    .table-container {
        border: none !important;
    }
    
    table {
        border-collapse: collapse;
        width: 100%;
        font-size: 11px;
    }
    
    th {
        background: #f0f0f0 !important;
        border: 1px solid #333 !important;
        padding: 8px !important;
        text-align: left;
        font-weight: bold !important;
        color: #000 !important;
    }
    
    td {
        border: 1px solid #999 !important;
        padding: 7px !important;
        color: #000 !important;
    }
    
    tr:nth-child(even) {
        background: #f9f9f9;
    }
    
    .status-badge {
        border: 1px solid #000 !important;
        background-color: white !important;
        color: black !important;
    }
    
    .page-break {
        page-break-after: always;
    }
}

/* Screen Styles */
.card {
    border-radius: 12px;
    border: none;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    margin-bottom: 25px;
    transition: box-shadow 0.3s ease;
}

.card:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
}

.card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-bottom: 2px solid #dee2e6;
    padding: 20px;
    border-radius: 12px 12px 0 0;
}

.card-header h3 {
    margin: 0;
    color: #2c3e50;
    font-size: 16px;
    font-weight: 600;
}

/* Fixed table header */
.table-container {
    max-height: 600px;
    overflow-y: auto;
    border: 1px solid #ecf0f1;
    border-radius: 8px;
}

.table-container table {
    width: 100%;
}

.table-container thead th {
    position: sticky;
    top: 0;
    background-color: #f8f9fa !important;
    border-bottom: 2px solid #dee2e6 !important;
    z-index: 10;
    font-weight: 600;
}

.table-container tbody tr {
    border-bottom: 1px solid #ecf0f1;
}

.table-container tbody tr:hover {
    background-color: #f5f7ff;
}

/* Better spacing */
.main {
    padding: 25px;
}
</style>

<!-- Stats Cards -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 35px;">
    <div class="stat-card" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%); color: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(52, 152, 219, 0.2); transition: transform 0.3s ease; position: relative; overflow: hidden;">
        <div style="position: absolute; top: -20px; right: -20px; opacity: 0.1; font-size: 60px;"><i class="fas fa-laptop"></i></div>
        <div style="font-size: 32px; font-weight: 700; margin-bottom: 8px;"><?php echo $stats['total_devices'] ?? 0; ?></div>
        <div style="font-size: 13px; opacity: 0.95; font-weight: 500;">Total Devices Assigned</div>
    </div>
    
    <div class="stat-card" style="background: linear-gradient(135deg, #27ae60 0%, #229954 100%); color: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(39, 174, 96, 0.2); transition: transform 0.3s ease; position: relative; overflow: hidden;">
        <div style="position: absolute; top: -20px; right: -20px; opacity: 0.1; font-size: 60px;"><i class="fas fa-check-circle"></i></div>
        <div style="font-size: 32px; font-weight: 700; margin-bottom: 8px;"><?php echo $stats['active_devices'] ?? 0; ?></div>
        <div style="font-size: 13px; opacity: 0.95; font-weight: 500;">Active & Functional</div>
    </div>
    
    <div class="stat-card" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); color: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(243, 156, 18, 0.2); transition: transform 0.3s ease; position: relative; overflow: hidden;">
        <div style="position: absolute; top: -20px; right: -20px; opacity: 0.1; font-size: 60px;"><i class="fas fa-tools"></i></div>
        <div style="font-size: 32px; font-weight: 700; margin-bottom: 8px;"><?php echo $stats['devices_under_repair'] ?? 0; ?></div>
        <div style="font-size: 13px; opacity: 0.95; font-weight: 500;">Under Repair</div>
    </div>
    
    <div class="stat-card" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(231, 76, 60, 0.2); transition: transform 0.3s ease; position: relative; overflow: hidden;">
        <div style="position: absolute; top: -20px; right: -20px; opacity: 0.1; font-size: 60px;"><i class="fas fa-clock"></i></div>
        <div style="font-size: 32px; font-weight: 700; margin-bottom: 8px;"><?php echo $stats['pending_repairs'] ?? 0; ?></div>
        <div style="font-size: 13px; opacity: 0.95; font-weight: 500;">Pending Repairs</div>
    </div>
</div>

<?php if (!empty($assignedDevices)): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Your Devices (<?php echo count($assignedDevices); ?> Total)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table class="table table-hover" style="margin-bottom: 0;">
                <thead>
                    <tr style="background-color: #f8f9fa;">
                        <th style="width: 18%;">Asset Tag</th>
                        <th style="width: 20%;">Device Type</th>
                        <th style="width: 15%;">Status</th>
                        <th style="width: 15%;">Assigned Date</th>
                        <th style="width: 14%;">Maintenance</th>
                        <th style="width: 12%;">Repairs</th>
                        <th style="width: 26%; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignedDevices as $device): ?>
                    <?php $statusColor = getStatusColor($device['status']); ?>
                    <?php $assignmentId = isset($activeAssignmentMap[$device['id']]) ? $activeAssignmentMap[$device['id']] : null; ?>
                    <tr style="border-bottom: 1px solid #ecf0f1;">
                        <td><strong style="color: #2c3e50; font-size: 13px;"><?php echo $device['asset_tag']; ?></strong></td>
                        <td style="font-size: 13px; color: #34495e;"><?php echo $device['type_name']; ?></td>
                        <td>
                            <span class="status-badge" style="background-color: <?php echo $statusColor['color_code']; ?>20; color: <?php echo $statusColor['color_code']; ?>; border: 1.5px solid <?php echo $statusColor['color_code']; ?>; padding: 6px 10px; border-radius: 6px; font-size: 11px; display: inline-flex; align-items: center; gap: 5px; font-weight: 600;">
                                <i class="<?php echo $statusColor['icon_class']; ?>"></i> <?php echo $statusColor['display_label']; ?>
                            </span>
                        </td>
                        <td style="font-size: 12px; color: #7f8c8d;"><?php echo date('M d, Y', strtotime($device['assigned_date'])); ?></td>
                        <td>
                            <?php if ($device['upcoming_maintenance'] > 0): ?>
                            <span style="background: #fff3cd; color: #856404; padding: 5px 8px; border-radius: 5px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo $device['upcoming_maintenance']; ?> Due
                            </span>
                            <?php else: ?>
                            <span style="color: #27ae60; font-size: 11px; font-weight: 600;">✓ On Schedule</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($device['pending_repairs'] > 0): ?>
                            <span style="background: #f8d7da; color: #721c24; padding: 5px 8px; border-radius: 5px; font-size: 11px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-wrench"></i> <?php echo $device['pending_repairs']; ?>
                            </span>
                            <?php else: ?>
                            <span style="color: #95a5a6; font-size: 11px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <div style="display: inline-flex; gap: 8px; flex-wrap: wrap; justify-content: center;">
                                <!-- View Button -->
                                <a href="view_device.php?id=<?php echo $device['id']; ?>" class="action-btn" title="View Device Details" style="display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; background: #3498db; color: white; border-radius: 6px; text-decoration: none; font-size: 14px; transition: all 0.3s ease; box-shadow: 0 2px 5px rgba(52, 152, 219, 0.2);" onmouseover="this.style.background='#2980b9'; this.style.boxShadow='0 4px 10px rgba(52, 152, 219, 0.3)';" onmouseout="this.style.background='#3498db'; this.style.boxShadow='0 2px 5px rgba(52, 152, 219, 0.2)';">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <!-- Report Issue Button -->
                                <button onclick="reportIssue(<?php echo $device['id']; ?>, '<?php echo $device['asset_tag']; ?>')" class="action-btn" title="Report Device Issue" style="display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; background: #f39c12; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; transition: all 0.3s ease; box-shadow: 0 2px 5px rgba(243, 156, 18, 0.2);" onmouseover="this.style.background='#e67e22'; this.style.boxShadow='0 4px 10px rgba(243, 156, 18, 0.3)';" onmouseout="this.style.background='#f39c12'; this.style.boxShadow='0 2px 5px rgba(243, 156, 18, 0.2)';">
                                    <i class="fas fa-exclamation-circle"></i>
                                </button>
                                
                                <!-- Voluntary Return Button -->
                                <?php if ($assignmentId): ?>
                                <button onclick="voluntarilyReturnDevice(<?php echo $assignmentId; ?>, '<?php echo $device['asset_tag']; ?>')" class="action-btn" title="Request Voluntary Return" style="display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; background: #e74c3c; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; transition: all 0.3s ease; box-shadow: 0 2px 5px rgba(231, 76, 60, 0.2);" onmouseover="this.style.background='#c0392b'; this.style.boxShadow='0 4px 10px rgba(231, 76, 60, 0.3)';" onmouseout="this.style.background='#e74c3c'; this.style.boxShadow='0 2px 5px rgba(231, 76, 60, 0.2)';">
                                    <i class="fas fa-hand-holding"></i>
                                </button>
                                <?php endif; ?>
                            </div>
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
    <div class="card-body" style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 50px; color: #bdc3c7; margin-bottom: 20px;"><i class="fas fa-inbox"></i></div>
        <h4 style="color: #2c3e50; font-size: 18px; margin-bottom: 10px;">No Devices Assigned Yet</h4>
        <p style="color: #7f8c8d; font-size: 14px;">You don't have any devices assigned to you. Contact IT if you need a device.</p>
    </div>
</div>
<?php endif; ?>

<!-- Report Issue Modal -->
<div id="reportIssueModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 90%; max-width: 550px; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #ecf0f1;">
            <h3 style="margin: 0;"><i class="fas fa-exclamation-triangle"></i> Report Device Issue</h3>
            <button onclick="closeReportIssue()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #7f8c8d; transition: color 0.2s;" onmouseover="this.style.color='#e74c3c';" onmouseout="this.style.color='#7f8c8d';">&times;</button>
        </div>
        <div class="card-body">
            <form id="reportForm" method="POST" enctype="multipart/form-data">
                <div class="form-group" style="margin-bottom: 18px;">
                    <label for="deviceTag" style="display: block; font-weight: 600; margin-bottom: 6px; color: #2c3e50;">Device</label>
                    <input type="text" id="deviceTag" readonly style="background: #ecf0f1; padding: 10px 12px; border: 1px solid #bdc3c7; border-radius: 6px; width: 100%; font-size: 14px; color: #7f8c8d;">
                </div>

                <div class="form-group" style="margin-bottom: 18px;">
                    <label for="issueDescription" style="display: block; font-weight: 600; margin-bottom: 6px; color: #2c3e50;">Issue Description <span style="color: #e74c3c;">*</span></label>
                    <textarea id="issueDescription" name="issue_description" required style="width: 100%; min-height: 100px; padding: 10px 12px; border: 1px solid #bdc3c7; border-radius: 6px; font-family: 'Segoe UI', Arial, sans-serif; font-size: 14px; resize: vertical;" placeholder="Describe the issue you're experiencing..."></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 18px;">
                    <label for="severity" style="display: block; font-weight: 600; margin-bottom: 6px; color: #2c3e50;">Severity Level</label>
                    <select id="severity" name="severity" style="width: 100%; padding: 10px 12px; border: 1px solid #bdc3c7; border-radius: 6px; font-size: 14px; background: white;">
                        <option value="low">Low - Device works but has issues</option>
                        <option value="medium" selected>Medium - Device is problematic</option>
                        <option value="high">High - Device barely functional</option>
                        <option value="critical">Critical - Device not working at all</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 22px;">
                    <label for="attachment" style="display: block; font-weight: 600; margin-bottom: 6px; color: #2c3e50;">Attach Evidence (optional)</label>
                    <input type="file" id="attachment" name="attachment" accept="image/*,.pdf" style="padding: 8px 12px; border: 2px dashed #bdc3c7; border-radius: 6px; width: 100%; font-size: 13px; color: #7f8c8d;">
                    <small style="color: #95a5a6; display: block; margin-top: 6px;">Max 5MB. Images or PDF only.</small>
                </div>

                <input type="hidden" id="deviceId" name="device_id">

                <div style="display: flex; gap: 12px; justify-content: flex-end; border-top: 1px solid #ecf0f1; padding-top: 18px;">
                    <button type="button" onclick="closeReportIssue()" class="btn btn-outline" style="padding: 10px 20px; border-radius: 6px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="padding: 10px 20px; border-radius: 6px;">Report Issue</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Voluntary Return Modal -->
<div id="voluntaryReturnModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="card" style="width: 90%; max-width: 550px; background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #ecf0f1;">
            <h3 style="margin: 0;"><i class="fas fa-hand-holding"></i> Request Device Return</h3>
            <button onclick="closeVoluntaryReturn()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #7f8c8d; transition: color 0.2s;" onmouseover="this.style.color='#e74c3c';" onmouseout="this.style.color='#7f8c8d';">&times;</button>
        </div>
        <div class="card-body">
            <div style="background: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <p style="margin: 0; color: #2c3e50; font-size: 14px;"><strong>Important:</strong> A return request will notify IT staff. They will assess your device clearance and coordinate the return process.</p>
            </div>
            
            <form id="voluntaryReturnForm" method="POST">
                <div class="form-group" style="margin-bottom: 18px;">
                    <label for="returnDeviceTag" style="display: block; font-weight: 600; margin-bottom: 6px; color: #2c3e50;">Device</label>
                    <input type="text" id="returnDeviceTag" readonly style="background: #ecf0f1; padding: 10px 12px; border: 1px solid #bdc3c7; border-radius: 6px; width: 100%; font-size: 14px; color: #7f8c8d;">
                </div>

                <div class="form-group" style="margin-bottom: 18px;">
                    <label for="returnReason" style="display: block; font-weight: 600; margin-bottom: 6px; color: #2c3e50;">Reason for Return (optional)</label>
                    <textarea id="returnReason" name="return_reason" style="width: 100%; min-height: 80px; padding: 10px 12px; border: 1px solid #bdc3c7; border-radius: 6px; font-family: 'Segoe UI', Arial, sans-serif; font-size: 14px; resize: vertical;" placeholder="Please provide any details about why you're returning this device..."></textarea>
                </div>

                <input type="hidden" id="returnAssignmentId" name="assignment_id">

                <div style="display: flex; gap: 12px; justify-content: flex-end; border-top: 1px solid #ecf0f1; padding-top: 18px;">
                    <button type="button" onclick="closeVoluntaryReturn()" class="btn btn-outline" style="padding: 10px 20px; border-radius: 6px;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="padding: 10px 20px; border-radius: 6px; background: #e74c3c; border-color: #c0392b;">Request Return</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Report Issue Functions
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

// Close report modal when clicking outside
document.getElementById('reportIssueModal').addEventListener('click', function(e) {
    if (e.target === this) closeReportIssue();
});

// Voluntary Return Functions
function voluntarilyReturnDevice(assignmentId, assetTag) {
    document.getElementById('returnAssignmentId').value = assignmentId;
    document.getElementById('returnDeviceTag').value = assetTag;
    document.getElementById('voluntaryReturnForm').reset();
    document.getElementById('voluntaryReturnModal').style.display = 'flex';
}

function closeVoluntaryReturn() {
    document.getElementById('voluntaryReturnModal').style.display = 'none';
}

document.getElementById('voluntaryReturnForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const assignmentId = document.getElementById('returnAssignmentId').value;
    const returnReason = document.getElementById('returnReason').value;
    
    fetch('api_voluntary_device_return.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            assignment_id: assignmentId,
            return_reason: returnReason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Your return request has been submitted successfully. IT staff will contact you to arrange clearance and pickup.');
            closeVoluntaryReturn();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to submit return request. Please try again.');
    });
});

// Close modal when clicking outside
document.getElementById('voluntaryReturnModal').addEventListener('click', function(e) {
    if (e.target === this) closeVoluntaryReturn();
});
</script>

<?php require_once 'includes/footer.php'; ?>
