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

// Get all non-disposed devices for the searchable picker
$devices = $pdo->query("SELECT id, asset_tag, model, status FROM devices WHERE status != 'disposed' ORDER BY asset_tag")->fetchAll();

$flash = getFlashMessage();

// Compute overdue / urgent counts
$overdueCount = 0;
$urgentCount  = 0;
$today        = new DateTime();
foreach ($upcomingMaintenance as $m) {
    $due  = new DateTime($m['next_due_date']);
    $diff = (int)$today->diff($due)->days;
    if ($today > $due) {
        $overdueCount++;
    } elseif ($diff <= 7) {
        $urgentCount++;
    }
}
?>

<style>
/* ── Stats Cards ──────────────────────────────────────── */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 28px;
}
@media (max-width: 768px) {
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}

.stat-card {
    background: #fff;
    border-radius: 10px;
    padding: 20px 22px;
    border: 1px solid #e8ecf0;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
    display: flex;
    align-items: center;
    gap: 16px;
}
.stat-icon {
    width: 48px; height: 48px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}
.stat-text .num {
    font-size: 28px;
    font-weight: 800;
    line-height: 1;
    color: #1a2332;
}
.stat-text .lbl {
    font-size: 12px;
    color: #6b7280;
    margin-top: 3px;
    font-weight: 500;
}

/* ── Table Styles ─────────────────────────────────────── */
.maint-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
}
.maint-table thead tr {
    background: #f8fafc;
    border-bottom: 2px solid #e8ecf0;
}
.maint-table thead th {
    padding: 11px 16px;
    text-align: left;
    font-size: 11.5px;
    font-weight: 700;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: .5px;
    white-space: nowrap;
}
.maint-table tbody tr {
    border-bottom: 1px solid #f0f2f5;
    transition: background .12s;
}
.maint-table tbody tr:last-child { border-bottom: none; }
.maint-table tbody tr:hover { background: #f8fafc; }
.maint-table tbody td {
    padding: 12px 16px;
    vertical-align: middle;
    color: #374151;
}

/* ── Card Section ─────────────────────────────────────── */
.section-card {
    background: #fff;
    border-radius: 10px;
    border: 1px solid #e8ecf0;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
    margin-bottom: 20px;
    overflow: hidden;
}
.section-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 20px;
    border-bottom: 1px solid #f0f2f5;
    flex-wrap: wrap;
    gap: 10px;
    background: #fff;
}
.section-card-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: #1a2332;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* ── Search Input ─────────────────────────────────────── */
.table-search {
    padding: 7px 12px 7px 34px;
    border: 1.5px solid #dde1e7;
    border-radius: 7px;
    font-size: 13px;
    width: 210px;
    background: #f8fafc url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E") no-repeat left 10px center;
    transition: border-color .2s, box-shadow .2s;
    color: #374151;
}
.table-search:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,.12);
    background-color: #fff;
}

/* ── Urgency Badges ───────────────────────────────────── */
.urgency-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
    letter-spacing: .2px;
}
.urgency-overdue  { background: #fff1f0; color: #cf1322; border: 1px solid #ffa39e; }
.urgency-critical { background: #fff7e6; color: #d46b08; border: 1px solid #ffd591; }
.urgency-warning  { background: #fffbe6; color: #ad8b00; border: 1px solid #ffe58f; }

/* ── Type Badges ──────────────────────────────────────── */
.maint-type-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 5px;
    font-size: 11.5px;
    font-weight: 600;
    text-transform: capitalize;
    letter-spacing: .1px;
}
.type-preventive  { background: #eff6ff; color: #1d4ed8; }
.type-corrective  { background: #fff1f0; color: #cf1322; }
.type-calibration { background: #f0fdf4; color: #15803d; }
.type-update      { background: #faf5ff; color: #7c3aed; }
.type-inspection  { background: #fffbeb; color: #b45309; }

/* ── Urgent Alert Banner ──────────────────────────────── */
.urgent-banner {
    background: #fff;
    border-radius: 10px;
    border: 1px solid #fed7aa;
    border-left: 4px solid #f97316;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
    margin-bottom: 20px;
    overflow: hidden;
}
.urgent-banner-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border-bottom: 1px solid #fed7aa;
    background: #fff7ed;
}
.urgent-banner-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: #c2410c;
}

/* ── Page Header ──────────────────────────────────────── */
.page-title-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 12px;
}
.page-title-bar h1 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: #1a2332;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* ── Searchable Device Picker ─────────────────────────── */
.device-picker-wrapper { position: relative; }

.device-picker-search {
    width: 100%;
    padding: 9px 38px 9px 12px;
    border: 1.5px solid #dde1e7;
    border-radius: 7px;
    font-size: 14px;
    box-sizing: border-box;
    background: white url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='15' height='15' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.35-4.35'/%3E%3C/svg%3E") no-repeat right 10px center;
    transition: border-color .2s, box-shadow .2s;
    color: #374151;
}
.device-picker-search:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,.12);
}
.device-picker-search.error {
    border-color: #ef4444;
    box-shadow: 0 0 0 3px rgba(239,68,68,.1);
}

.device-picker-dropdown {
    display: none;
    position: absolute;
    top: calc(100% + 4px);
    left: 0; right: 0;
    background: white;
    border: 1.5px solid #dde1e7;
    border-radius: 8px;
    max-height: 250px;
    overflow-y: auto;
    z-index: 9999;
    box-shadow: 0 8px 28px rgba(0,0,0,.12);
}
.device-picker-dropdown.open { display: block; }

.device-picker-item {
    padding: 10px 14px;
    cursor: pointer;
    border-bottom: 1px solid #f3f4f6;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: background .12s;
}
.device-picker-item:last-child { border-bottom: none; }
.device-picker-item:hover,
.device-picker-item.highlighted { background: #eff6ff; }

.device-picker-item .dtag {
    font-weight: 700;
    font-size: 13px;
    color: #1a2332;
    min-width: 120px;
}
.device-picker-item .dmodel {
    font-size: 12px;
    color: #6b7280;
    flex: 1;
}
.device-picker-item .dstatus {
    font-size: 10px;
    padding: 2px 8px;
    border-radius: 10px;
    background: #eff6ff;
    color: #1d4ed8;
    font-weight: 600;
    text-transform: capitalize;
    white-space: nowrap;
}

.device-picker-no-results {
    padding: 18px;
    color: #9ca3af;
    font-size: 13px;
    text-align: center;
}

.device-picker-selected {
    margin-top: 8px;
    padding: 8px 12px;
    background: #f0fdf4;
    border: 1.5px solid #86efac;
    border-radius: 7px;
    font-size: 13px;
    color: #15803d;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.device-picker-selected.hidden { display: none; }

.clear-device-btn {
    background: none;
    border: none;
    color: #ef4444;
    cursor: pointer;
    font-size: 18px;
    line-height: 1;
    padding: 0 2px;
    margin-left: 6px;
    opacity: .7;
    transition: opacity .15s;
}
.clear-device-btn:hover { opacity: 1; }

/* ── Modal ────────────────────────────────────────────── */
.modal-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(15,23,42,.5);
    backdrop-filter: blur(4px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.modal-overlay.open { display: flex; }

.modal-box {
    background: white;
    border-radius: 14px;
    width: 100%;
    max-width: 580px;
    max-height: 92vh;
    overflow-y: auto;
    box-shadow: 0 24px 64px rgba(0,0,0,.2);
    animation: modalIn .22s cubic-bezier(.16,1,.3,1);
}
@keyframes modalIn {
    from { opacity: 0; transform: translateY(-16px) scale(.96); }
    to   { opacity: 1; transform: none; }
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 24px 16px;
    border-bottom: 1px solid #f0f2f5;
    position: sticky; top: 0;
    background: white; z-index: 1;
}
.modal-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 700;
    color: #1a2332;
    display: flex;
    align-items: center;
    gap: 8px;
}
.modal-close {
    background: #f3f4f6; border: none; border-radius: 50%;
    width: 32px; height: 32px; cursor: pointer; font-size: 18px;
    display: flex; align-items: center; justify-content: center;
    color: #6b7280; transition: background .15s, color .15s;
}
.modal-close:hover { background: #ef4444; color: white; }

.modal-body { padding: 20px 24px 24px; }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

.form-group { margin-bottom: 16px; }
.form-group label {
    display: block;
    font-weight: 600;
    font-size: 13px;
    color: #374151;
    margin-bottom: 6px;
}
.form-group label .req { color: #ef4444; }

.form-control {
    width: 100%;
    padding: 9px 12px;
    border: 1.5px solid #dde1e7;
    border-radius: 7px;
    font-size: 14px;
    box-sizing: border-box;
    transition: border-color .2s, box-shadow .2s;
    font-family: inherit;
    color: #374151;
}
.form-control:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59,130,246,.12);
}

.form-footer {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding-top: 12px;
    border-top: 1px solid #f0f2f5;
    margin-top: 6px;
}
</style>

<?php if ($flash): ?>
<div style="background:<?php echo $flash['type']==='success'?'#d5f5e3':'#fde8e8';?>;color:<?php echo $flash['type']==='success'?'#1e8449':'#922b21';?>;padding:12px 16px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:8px;border:1px solid <?php echo $flash['type']==='success'?'#27ae60':'#e74c3c';?>;">
    <i class="fas fa-<?php echo $flash['type']==='success'?'check-circle':'exclamation-circle';?>"></i>
    <?php echo $flash['message']; ?>
</div>
<?php endif; ?>

<!-- Page Header -->
<div class="page-title-bar">
    <h1>
        <i class="fas fa-calendar-check" style="color:#3b82f6;font-size:18px;"></i>
        Maintenance Reminders
    </h1>
    <button onclick="openCreateSchedule()" class="btn btn-success" style="display:flex;align-items:center;gap:6px;font-size:14px;padding:9px 16px;">
        <i class="fas fa-plus"></i> Add Maintenance Schedule
    </button>
</div>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:#fff7ed;color:#ea580c;">
            <i class="fas fa-calendar-alt"></i>
        </div>
        <div class="stat-text">
            <div class="num" style="color:#ea580c;"><?php echo count($upcomingMaintenance); ?></div>
            <div class="lbl">Due in 30 Days</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fff1f0;color:#cf1322;">
            <i class="fas fa-exclamation-circle"></i>
        </div>
        <div class="stat-text">
            <div class="num" style="color:#cf1322;"><?php echo $overdueCount; ?></div>
            <div class="lbl">Overdue</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fff7e6;color:#d46b08;">
            <i class="fas fa-fire"></i>
        </div>
        <div class="stat-text">
            <div class="num" style="color:#d46b08;"><?php echo $urgentCount; ?></div>
            <div class="lbl">Due This Week</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#eff6ff;color:#1d4ed8;">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <div class="stat-text">
            <div class="num" style="color:#1d4ed8;"><?php echo count($allMaintenance); ?></div>
            <div class="lbl">Total Scheduled</div>
        </div>
    </div>
</div>

<!-- Urgent section -->
<?php if (!empty($upcomingMaintenance)): ?>
<div class="urgent-banner">
    <div class="urgent-banner-header">
        <i class="fas fa-exclamation-triangle" style="color:#f97316;"></i>
        <h3>Urgent — Due in Next 7 Days</h3>
    </div>
    <div style="overflow-x:auto;">
        <table class="maint-table">
            <thead>
                <tr>
                    <th>Device</th>
                    <th>Type</th>
                    <th>Due Date</th>
                    <th>Assigned To</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($upcomingMaintenance as $maint):
                    $dueDate   = new DateTime($maint['next_due_date']);
                    $todayRef  = new DateTime();
                    $diffDays  = (int)$todayRef->diff($dueDate)->days;
                    $isOverdue = $todayRef > $dueDate;
                ?>
                <tr style="<?php echo $isOverdue ? 'background:#fff5f5;' : ''; ?>">
                    <td>
                        <span style="font-weight:600;color:#1a2332;"><?php echo htmlspecialchars($maint['asset_tag']); ?></span>
                        <?php if (!empty($maint['model'])): ?>
                        <br><small style="color:#6b7280;"><?php echo htmlspecialchars($maint['model']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php $tc = 'type-'.strtolower($maint['maintenance_type']); ?>
                        <span class="maint-type-badge <?php echo $tc; ?>">
                            <?php echo str_replace('_', ' ', ucfirst($maint['maintenance_type'])); ?>
                        </span>
                    </td>
                    <td style="color:#374151;"><?php echo date('M d, Y', strtotime($maint['next_due_date'])); ?></td>
                    <td>
                        <span style="color:#374151;"><?php echo htmlspecialchars($maint['full_name'] ?? '—'); ?></span>
                        <?php if (!empty($maint['email'])): ?>
                        <br><small style="color:#6b7280;"><?php echo htmlspecialchars($maint['email']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isOverdue): ?>
                            <span class="urgency-badge urgency-overdue"><i class="fas fa-exclamation-circle"></i> Overdue <?php echo $diffDays; ?>d</span>
                        <?php elseif ($diffDays <= 3): ?>
                            <span class="urgency-badge urgency-critical"><i class="fas fa-fire"></i> <?php echo $diffDays; ?> days left</span>
                        <?php else: ?>
                            <span class="urgency-badge urgency-warning"><i class="fas fa-clock"></i> <?php echo $diffDays; ?> days left</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;">
                        <button onclick="sendMaintenanceReminder(<?php echo $maint['id']; ?>)" class="btn btn-sm btn-primary" title="Send Reminder Email" style="margin-right:4px;">
                            <i class="fas fa-envelope"></i>
                        </button>
                        <form method="POST" style="display:inline;">
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
<?php endif; ?>

<!-- All Scheduled Maintenance -->
<div class="section-card">
    <div class="section-card-header">
        <h3>
            <i class="fas fa-list" style="color:#3b82f6;"></i>
            All Maintenance Schedules
        </h3>
        <input type="text" id="tableSearch" class="table-search"
               placeholder="Filter schedules…"
               oninput="filterTable(this.value)">
    </div>
    <div style="overflow-x:auto;">
        <table class="maint-table" id="allMaintenanceTable">
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
                <?php foreach ($allMaintenance as $maint):
                    $dueDate2 = new DateTime($maint['next_due_date']);
                    $todayNow = new DateTime();
                    $isOv     = $todayNow > $dueDate2;
                    $tc2      = 'type-'.strtolower($maint['maintenance_type']);
                ?>
                <tr>
                    <td>
                        <span style="font-weight:600;color:#1a2332;"><?php echo htmlspecialchars($maint['asset_tag']); ?></span>
                        <?php if (!empty($maint['model'])): ?>
                        <br><small style="color:#6b7280;"><?php echo htmlspecialchars($maint['model']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="maint-type-badge <?php echo $tc2; ?>">
                            <?php echo str_replace('_', ' ', ucfirst($maint['maintenance_type'])); ?>
                        </span>
                    </td>
                    <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#6b7280;"
                        title="<?php echo htmlspecialchars($maint['description']); ?>">
                        <?php echo htmlspecialchars(substr($maint['description'], 0, 50)); ?>
                    </td>
                    <td>
                        <span style="color:<?php echo $isOv ? '#cf1322' : '#374151'; ?>;font-weight:<?php echo $isOv ? '600' : '400'; ?>;">
                            <?php echo date('M d, Y', strtotime($maint['next_due_date'])); ?>
                        </span>
                        <?php if ($isOv): ?><br><span class="urgency-badge urgency-overdue" style="font-size:10px;margin-top:3px;">Overdue</span><?php endif; ?>
                    </td>
                    <td style="color:#374151;"><?php echo htmlspecialchars($maint['full_name'] ?? '—'); ?></td>
                    <td style="color:#6b7280;"><?php echo $maint['last_performed_date'] ? date('M d, Y', strtotime($maint['last_performed_date'])) : '—'; ?></td>
                    <td>
                        <a href="view_device.php?id=<?php echo $maint['device_id']; ?>#maintenance" class="btn btn-sm btn-info" title="View Device">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($allMaintenance)): ?>
                <tr>
                    <td colspan="7" style="text-align:center;padding:40px 20px;color:#9ca3af;">
                        <i class="fas fa-calendar-times" style="font-size:28px;display:block;margin-bottom:10px;opacity:.4;"></i>
                        No maintenance schedules found.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Add Maintenance Schedule Modal ────────────────────── -->
<div id="createScheduleModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-plus" style="color:#27ae60;"></i> Add Maintenance Schedule</h3>
            <button class="modal-close" onclick="closeCreateSchedule()" title="Close">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="createMaintenanceForm">

                <!-- Searchable Device Picker -->
                <div class="form-group">
                    <label>Device <span class="req">*</span></label>
                    <input type="hidden" name="device_id" id="selectedDeviceId">
                    <div class="device-picker-wrapper">
                        <input type="text"
                               id="deviceSearch"
                               class="device-picker-search"
                               placeholder="Search by asset tag or model name…"
                               autocomplete="off"
                               oninput="filterDevices(this.value)"
                               onfocus="openDeviceDropdown()"
                               onkeydown="handleDeviceKey(event)">
                        <div id="deviceDropdown" class="device-picker-dropdown">
                            <?php foreach ($devices as $dev): ?>
                            <div class="device-picker-item"
                                 data-id="<?php echo $dev['id']; ?>"
                                 data-tag="<?php echo htmlspecialchars($dev['asset_tag']); ?>"
                                 data-model="<?php echo htmlspecialchars($dev['model'] ?? ''); ?>"
                                 data-status="<?php echo htmlspecialchars($dev['status']); ?>"
                                 onclick="selectDevice(this)">
                                <span class="dtag"><?php echo htmlspecialchars($dev['asset_tag']); ?></span>
                                <span class="dmodel"><?php echo htmlspecialchars(!empty($dev['model']) ? $dev['model'] : 'No model info'); ?></span>
                                <span class="dstatus"><?php echo htmlspecialchars($dev['status']); ?></span>
                            </div>
                            <?php endforeach; ?>
                            <div id="noDeviceResults" class="device-picker-no-results" style="display:none;">
                                <i class="fas fa-search"></i> No devices match your search
                            </div>
                        </div>
                    </div>
                    <div id="selectedDeviceDisplay" class="device-picker-selected hidden">
                        <span id="selectedDeviceText"></span>
                        <button type="button" class="clear-device-btn" onclick="clearDevice()" title="Clear">&times;</button>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Maintenance Type <span class="req">*</span></label>
                        <select name="maintenance_type" class="form-control" required>
                            <option value="preventive">Preventive</option>
                            <option value="corrective">Corrective</option>
                            <option value="calibration">Calibration</option>
                            <option value="update">Software Update</option>
                            <option value="inspection">Inspection</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Scheduled Date <span class="req">*</span></label>
                        <input type="date" name="scheduled_date" class="form-control" required
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3"
                              placeholder="Describe what needs to be done…"
                              style="resize:vertical;"></textarea>
                </div>

                <div class="form-group">
                    <label>Assign To (IT Staff)</label>
                    <select name="assigned_to" class="form-control">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($itStaff as $staff): ?>
                        <option value="<?php echo $staff['id']; ?>">
                            <?php echo htmlspecialchars($staff['full_name']); ?>
                            &lt;<?php echo htmlspecialchars($staff['email']); ?>&gt;
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-footer">
                    <button type="button" onclick="closeCreateSchedule()" class="btn btn-outline">Cancel</button>
                    <button type="submit" name="create_maintenance" value="1" class="btn btn-primary">
                        <i class="fas fa-calendar-plus"></i> Create Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
/* ── Device Picker ─────────────────────────────────────────── */
let highlightedIndex = -1;

function getVisibleItems() {
    return Array.from(document.querySelectorAll('.device-picker-item'))
                .filter(el => el.style.display !== 'none');
}

function openDeviceDropdown() {
    document.getElementById('deviceDropdown').classList.add('open');
}

function closeDeviceDropdown() {
    document.getElementById('deviceDropdown').classList.remove('open');
    highlightedIndex = -1;
}

function filterDevices(query) {
    const q = query.toLowerCase().trim();
    const items = document.querySelectorAll('.device-picker-item');
    let visible = 0;

    items.forEach(item => {
        const matches = !q
            || item.dataset.tag.toLowerCase().includes(q)
            || item.dataset.model.toLowerCase().includes(q);
        item.style.display = matches ? '' : 'none';
        if (matches) visible++;
    });

    document.getElementById('noDeviceResults').style.display = visible === 0 ? '' : 'none';
    openDeviceDropdown();
    highlightedIndex = -1;
}

function handleDeviceKey(e) {
    const items = getVisibleItems();
    if (!items.length) return;

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        highlightedIndex = Math.min(highlightedIndex + 1, items.length - 1);
        updateHighlight(items);
        items[highlightedIndex].scrollIntoView({ block: 'nearest' });

    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        highlightedIndex = Math.max(highlightedIndex - 1, 0);
        updateHighlight(items);
        items[highlightedIndex].scrollIntoView({ block: 'nearest' });

    } else if (e.key === 'Enter') {
        e.preventDefault();
        if (highlightedIndex >= 0 && items[highlightedIndex]) {
            selectDevice(items[highlightedIndex]);
        }

    } else if (e.key === 'Escape') {
        closeDeviceDropdown();
    }
}

function updateHighlight(items) {
    items.forEach((el, i) => el.classList.toggle('highlighted', i === highlightedIndex));
}

function selectDevice(el) {
    const id     = el.dataset.id;
    const tag    = el.dataset.tag;
    const model  = el.dataset.model || 'No model info';
    const status = el.dataset.status;

    document.getElementById('selectedDeviceId').value = id;
    document.getElementById('deviceSearch').value     = '';
    document.getElementById('deviceSearch').classList.remove('error');

    const display = document.getElementById('selectedDeviceDisplay');
    display.classList.remove('hidden');
    document.getElementById('selectedDeviceText').innerHTML =
        '<i class="fas fa-check-circle"></i> <strong>' + tag + '</strong> &mdash; '
        + model + ' <span style="font-size:11px;opacity:.7;">(' + status + ')</span>';

    closeDeviceDropdown();
    filterDevices(''); // reset list visibility for next time
}

function clearDevice() {
    document.getElementById('selectedDeviceId').value = '';
    document.getElementById('selectedDeviceDisplay').classList.add('hidden');
    document.getElementById('deviceSearch').value = '';
    filterDevices('');
    document.getElementById('deviceSearch').focus();
}

// Close on outside click
document.addEventListener('click', function(e) {
    const wrapper = document.querySelector('.device-picker-wrapper');
    if (wrapper && !wrapper.contains(e.target)) {
        closeDeviceDropdown();
    }
});

/* ── Modal ─────────────────────────────────────────────────── */
function openCreateSchedule() {
    document.getElementById('createScheduleModal').classList.add('open');
    setTimeout(function() {
        document.getElementById('deviceSearch').focus();
    }, 120);
}

function closeCreateSchedule() {
    document.getElementById('createScheduleModal').classList.remove('open');
}

document.getElementById('createScheduleModal').addEventListener('click', function(e) {
    if (e.target === this) closeCreateSchedule();
});

/* ── Form Validation ───────────────────────────────────────── */
document.getElementById('createMaintenanceForm').addEventListener('submit', function(e) {
    const deviceId = document.getElementById('selectedDeviceId').value;
    if (!deviceId) {
        e.preventDefault();
        const searchEl = document.getElementById('deviceSearch');
        searchEl.classList.add('error');
        searchEl.placeholder = 'Please select a device first';
        searchEl.focus();
        openDeviceDropdown();
    }
});

document.getElementById('deviceSearch').addEventListener('input', function() {
    this.classList.remove('error');
    this.placeholder = 'Search by asset tag or model name…';
});

/* ── Table Filter ─────────────────────────────────────────── */
function filterTable(query) {
    const q = query.toLowerCase();
    document.querySelectorAll('#allMaintenanceTable tbody tr').forEach(function(row) {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

/* ── Send Reminder ────────────────────────────────────────── */
function sendMaintenanceReminder(maintenanceId) {
    if (!confirm('Send maintenance reminder email to assigned staff?')) return;

    fetch('api_send_maintenance_reminder.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ maintenance_id: maintenanceId })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        alert(data.success ? 'Reminder email sent successfully.' : 'Error: ' + data.message);
    })
    .catch(function(err) { console.error('Error:', err); });
}
</script>

<?php require_once 'includes/footer.php'; ?>