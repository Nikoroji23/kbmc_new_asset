<?php
/**
 * KBMC Asset Management - Maintenance & Repairs Management
 * Combined view for managing preventive maintenance schedules, repairs, and device issues
 */

$pageTitle = 'Maintenance & Repairs';
require_once 'includes/header.php';

requireITStaff();
ensureMaintenanceSchema();

// Determine current tab from GET parameter
$activeTab = $_GET['tab'] ?? 'maintenance';

// ─────────────────────────────────────────────────────────────
// MAINTENANCE SECTION
// ─────────────────────────────────────────────────────────────

// Handle maintenance completion
if (isset($_POST['complete_maintenance']) && isset($_POST['maintenance_id'])) {
    $maintenanceId   = (int)$_POST['maintenance_id'];
    $completedBy     = !empty($_POST['completed_by']) ? (int)$_POST['completed_by'] : $_SESSION['user_id'];
    $completedAt     = !empty($_POST['completed_at']) ? $_POST['completed_at'] : null;
    $completionNotes = !empty($_POST['completion_notes']) ? sanitize($_POST['completion_notes']) : null;
    $ok = markMaintenanceCompleted($maintenanceId, $completedBy, $completedAt, $completionNotes);
    if ($ok) {
        setFlashMessage('success', 'Maintenance completion recorded.');
    } else {
        setFlashMessage('error', 'Could not update maintenance record.');
    }
    header('Location: maintenance_repairs.php?tab=maintenance');
    exit();
}

// Handle new maintenance schedule
if (isset($_POST['create_maintenance'])) {
    $deviceId = (int)$_POST['device_id'];
    $maintenanceType = $_POST['maintenance_type'];
    $description = sanitize($_POST['description']);
    $scheduledDate = $_POST['scheduled_date'];
    $assignedTo = !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;

    createMaintenanceSchedule($deviceId, $maintenanceType, $description, $scheduledDate, $assignedTo, $_SESSION['user_id']);
    setFlashMessage('success', 'Maintenance schedule created. Reminders will be sent.');
    header('Location: maintenance_repairs.php?tab=maintenance');
    exit();
}

// Get upcoming maintenance
$upcomingMaintenance = getUpcomingMaintenanceReminders(30);
$select = [
    'ms.*',
    'd.asset_tag',
    'd.model',
    "a.full_name AS assigned_to_name",
    "a.email AS assigned_to_email",
];

$joins = [
    'FROM maintenance_schedules ms',
    'JOIN devices d ON ms.device_id = d.id',
    'LEFT JOIN users a ON ms.assigned_to = a.id',
];

if (function_exists('columnExists') && columnExists('maintenance_schedules', 'requested_by')) {
    $select[] = "r.full_name AS requested_by_name";
    $joins[]   = 'LEFT JOIN users r ON ms.requested_by = r.id';
}

if (function_exists('columnExists') && columnExists('maintenance_schedules', 'completed_by')) {
    $select[] = "c.full_name AS completed_by_name";
    $joins[]   = 'LEFT JOIN users c ON ms.completed_by = c.id';
}

$sql = 'SELECT ' . implode(', ', $select) . ' ' . implode(' ', $joins) . ' WHERE ms.last_performed_date IS NULL ORDER BY ms.next_due_date ASC LIMIT 50';
$allMaintenance = $pdo->query($sql)->fetchAll();

// ─────────────────────────────────────────────────────────────
// REPAIRS SECTION
// ─────────────────────────────────────────────────────────────

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
        header('Location: maintenance_repairs.php?tab=repairs');
        exit();
    } catch (PDOException $e) {
        setFlashMessage('error', 'Error: ' . $e->getMessage());
    }
}

// Get pending and completed repairs
$pendingRepairs = getPendingRepairs();
$completedRepairs = getCompletedRepairs(10);

// Get IT staff for assignment
$itStaff = $pdo->query("SELECT id, full_name, email FROM users WHERE role IN ('admin', 'it_staff') ORDER BY full_name")->fetchAll();

// Get all non-disposed devices for the searchable picker
$devices = $pdo->query("SELECT id, asset_tag, model, status FROM devices WHERE status != 'disposed' ORDER BY asset_tag")->fetchAll();
$repairableDevices = $pdo->query("SELECT id, asset_tag, CONCAT(brand, ' ', model) as name FROM devices WHERE status IN ('deployed', 'in_stock') ORDER BY asset_tag")->fetchAll();

$flash = getFlashMessage();

// Compute maintenance overdue / urgent counts
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
/* ── Tabs ──────────────────────────────────────────────── */
.tab-navigation {
    display: flex;
    gap: 10px;
    margin-bottom: 24px;
    border-bottom: 2px solid #e8ecf0;
}
.tab-btn {
    background: none;
    border: none;
    padding: 12px 20px;
    font-size: 15px;
    font-weight: 600;
    color: #6b7280;
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all .2s;
    position: relative;
    top: 2px;
}
.tab-btn:hover {
    color: #1a2332;
}
.tab-btn.active {
    color: #3b82f6;
    border-bottom-color: #3b82f6;
}
.tab-content {
    display: none;
}
.tab-content.active {
    display: block;
}

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
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13.5px;
}
.data-table thead tr {
    background: #f8fafc;
    border-bottom: 2px solid #e8ecf0;
}
.data-table thead th {
    padding: 11px 16px;
    text-align: left;
    font-size: 11.5px;
    font-weight: 700;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: .5px;
    white-space: nowrap;
}
.data-table tbody tr {
    border-bottom: 1px solid #f0f2f5;
    transition: background .12s;
}
.data-table tbody tr:last-child { border-bottom: none; }
.data-table tbody tr:hover { background: #f8fafc; }
.data-table tbody td {
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

/* ── Page Title Bar ────────────────────────────────────── */
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

/* ── Device Picker ─────────────────────────────────────── */
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

.action-btns {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}
</style>

<?php if ($flash): ?>
<div class="alert <?php echo $flash['type'] === 'success' ? 'alert-success' : 'alert-error'; ?>">
    <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
    <span><?php echo $flash['message']; ?></span>
    <button type="button" class="alert-close" onclick="this.closest('.alert').remove();" aria-label="Close">&times;</button>
</div>
<?php endif; ?>

<div class="page-title-bar">
    <h1>
        <i class="fas fa-tools" style="color:#3b82f6;font-size:18px;"></i>
        Maintenance & Repairs Management
    </h1>
</div>

<!-- Tab Navigation -->
<div class="tab-navigation">
    <button class="tab-btn <?php echo $activeTab === 'maintenance' ? 'active' : ''; ?>" onclick="switchTab('maintenance')">
        <i class="fas fa-calendar-check"></i> Maintenance Schedules
    </button>
    <button class="tab-btn <?php echo $activeTab === 'repairs' ? 'active' : ''; ?>" onclick="switchTab('repairs')">
        <i class="fas fa-tools"></i> Device Repairs
    </button>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MAINTENANCE TAB
     ═══════════════════════════════════════════════════════════ -->
<div id="maintenance-tab" class="tab-content <?php echo $activeTab === 'maintenance' ? 'active' : ''; ?>">

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

    <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
        <button onclick="openCreateSchedule()" class="btn btn-success">
            <i class="fas fa-plus"></i> Add Maintenance Schedule
        </button>
    </div>

    <!-- Urgent Maintenance -->
    <?php if (!empty($upcomingMaintenance)): ?>
    <div class="urgent-banner">
        <div class="urgent-banner-header">
            <i class="fas fa-exclamation-triangle" style="color:#f97316;"></i>
            <h3>Urgent — Due in Next 7 Days</h3>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table">
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
                            <span style="color:#374151;"><?php echo htmlspecialchars($maint['assigned_to_name'] ?? '—'); ?></span>
                            <?php if (!empty($maint['assigned_to_email'])): ?>
                            <br><small style="color:#6b7280;"><?php echo htmlspecialchars($maint['assigned_to_email']); ?></small>
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
                        <td class="action-btns">
                            <button type="button" onclick="openCompleteModal(<?php echo $maint['id']; ?>)" class="btn btn-sm btn-success" title="Record Completion">
                                <i class="fas fa-check"></i>
                            </button>
                            <button onclick="sendMaintenanceReminder(<?php echo $maint['id']; ?>)" class="btn btn-sm btn-primary" title="Send Reminder Email">
                                <i class="fas fa-envelope"></i>
                            </button>
                            <a href="view_device.php?id=<?php echo $maint['device_id']; ?>" class="btn btn-sm btn-secondary" title="View Device">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- All Maintenance Schedules -->
    <div class="section-card">
        <div class="section-card-header">
            <h3>
                <i class="fas fa-list" style="color:#3b82f6;"></i>
                All Maintenance Schedules
            </h3>
            <input type="text" id="maintTableSearch" class="table-search"
                   placeholder="Filter schedules…"
                   oninput="filterTable('maintTableSearch', this.value)">
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table" id="maintTable">
                <thead>
                    <tr>
                        <th>Device</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Next Due</th>
                        <th>Assigned To</th>
                        <th>Completed</th>
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
                        <td style="color:#374151;"><?php echo htmlspecialchars($maint['assigned_to_name'] ?? '—'); ?></td>
                        <td style="color:#6b7280;">
                            <?php if (!empty($maint['completed_at'])): ?>
                                <?php echo date('M d, Y H:i', strtotime($maint['completed_at'])); ?><br>
                                <small>by <?php echo htmlspecialchars($maint['completed_by_name'] ?? '—'); ?></small>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="action-btns">
                            <button type="button" onclick="openCompleteModal(<?php echo $maint['id']; ?>)" class="btn btn-sm btn-success" title="Record Completion">
                                <i class="fas fa-check"></i>
                            </button>
                            <a href="view_device.php?id=<?php echo $maint['device_id']; ?>#maintenance" class="btn btn-sm btn-secondary" title="View Device">
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

</div>

<!-- ═══════════════════════════════════════════════════════════
     REPAIRS TAB
     ═══════════════════════════════════════════════════════════ -->
<div id="repairs-tab" class="tab-content <?php echo $activeTab === 'repairs' ? 'active' : ''; ?>">

    <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
        <button onclick="openRepairForm()" class="btn btn-success">
            <i class="fas fa-plus"></i> New Repair Request
        </button>
    </div>

    <!-- Pending Repairs -->
    <?php if (!empty($pendingRepairs)): ?>
    <div class="section-card" style="border-left: 4px solid #e74c3c; background: #fff5f5;">
        <div class="section-card-header">
            <h3><i class="fas fa-exclamation-circle"></i> Pending Repairs (<strong><?php echo count($pendingRepairs); ?></strong>)</h3>
        </div>
        <div style="overflow-x:auto;">
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
                            <button onclick="sendRepairNotification(event, <?php echo $r['id']; ?>, '<?php echo sanitize($r['asset_tag']); ?>')" class="btn btn-sm btn-info" title="Send Notification">
                                <i class="fas fa-bell"></i>
                            </button>
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
    <?php endif; ?>

    <!-- Completed Repairs -->
    <div class="section-card">
        <div class="section-card-header">
            <h3><i class="fas fa-check-circle"></i> Recently Completed Repairs</h3>
        </div>
        <div style="overflow-x:auto;">
            <?php if (empty($completedRepairs)): ?>
            <div style="text-align: center; padding: 30px; color: #7f8c8d;">
                <i class="fas fa-box" style="font-size: 30px; margin-bottom: 10px; display: block;"></i>
                <p>No completed repairs yet</p>
            </div>
            <?php else: ?>
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
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- ═══════════════════════════════════════════════════════════
     MODALS
     ═══════════════════════════════════════════════════════════ -->

<!-- Add Maintenance Schedule Modal -->
<div id="createScheduleModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-plus" style="color:#27ae60;"></i> Add Maintenance Schedule</h3>
            <button class="modal-close" onclick="closeCreateSchedule()" title="Close">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="createMaintenanceForm">
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

<!-- Complete Maintenance Modal -->
<div id="completeMaintenanceModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fas fa-check-circle" style="color:#16a34a;"></i> Record Maintenance Completion</h3>
            <button class="modal-close" onclick="closeCompleteModal()" title="Close">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="completeMaintenanceForm">
                <input type="hidden" name="maintenance_id" id="completionMaintenanceId">
                <input type="hidden" name="complete_maintenance" value="1">

                <div class="form-group">
                    <label>Completed By</label>
                    <select name="completed_by" id="completedBySelect" class="form-control">
                        <?php foreach ($itStaff as $staff): ?>
                        <option value="<?php echo $staff['id']; ?>"<?php echo $staff['id'] == $_SESSION['user_id'] ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars($staff['full_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Completion Date</label>
                    <input type="date" name="completed_at" id="completedAtInput" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label>Proof / Notes</label>
                    <textarea name="completion_notes" id="completionNotes" class="form-control" rows="4" placeholder="Describe what was done, findings, or attach proof URL..." style="resize:vertical;"></textarea>
                </div>

                <div class="form-footer">
                    <button type="button" onclick="closeCompleteModal()" class="btn btn-outline">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Completion
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- New Repair Request Modal -->
<div id="repairFormModal" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fas fa-tools"></i> New Repair Request</h3>
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

                <div class="form-footer">
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
            <h3><i class="fas fa-check-circle"></i> Mark Repair as Complete</h3>
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

            <div class="form-footer">
                <button type="button" onclick="closeMarkDone()" class="btn btn-outline">Cancel</button>
                <button type="button" onclick="submitRepairCompletion()" class="btn btn-success">Mark as Complete</button>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="repairIdToMark">

<script>
// ─────────────────────────────────────────────────────────────
// TAB SWITCHING
// ─────────────────────────────────────────────────────────────
function switchTab(tabName) {
    // Hide all tabs
    document.getElementById('maintenance-tab').classList.remove('active');
    document.getElementById('repairs-tab').classList.remove('active');
    
    // Deactivate all buttons
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    
    // Show selected tab and activate button
    document.getElementById(tabName + '-tab').classList.add('active');
    event.target.classList.add('active');
    
    // Update URL
    window.history.replaceState({}, '', '?tab=' + tabName);
}

// ─────────────────────────────────────────────────────────────
// MAINTENANCE FUNCTIONS
// ─────────────────────────────────────────────────────────────

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
    filterDevices('');
}

function clearDevice() {
    document.getElementById('selectedDeviceId').value = '';
    document.getElementById('selectedDeviceDisplay').classList.add('hidden');
    document.getElementById('deviceSearch').value = '';
    filterDevices('');
    document.getElementById('deviceSearch').focus();
}

document.addEventListener('click', function(e) {
    const wrapper = document.querySelector('.device-picker-wrapper');
    if (wrapper && !wrapper.contains(e.target)) {
        closeDeviceDropdown();
    }
});

function openCreateSchedule() {
    document.getElementById('createScheduleModal').classList.add('open');
    setTimeout(function() {
        document.getElementById('deviceSearch').focus();
    }, 120);
}

function closeCreateSchedule() {
    document.getElementById('createScheduleModal').classList.remove('open');
}

function openCompleteModal(maintenanceId) {
    document.getElementById('completionMaintenanceId').value = maintenanceId;
    document.getElementById('completionNotes').value = '';
    document.getElementById('completeMaintenanceModal').classList.add('open');
}

function closeCompleteModal() {
    document.getElementById('completeMaintenanceModal').classList.remove('open');
}

document.getElementById('createScheduleModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeCreateSchedule();
});

document.getElementById('completeMaintenanceModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeCompleteModal();
});

document.getElementById('createMaintenanceForm')?.addEventListener('submit', function(e) {
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

document.getElementById('deviceSearch')?.addEventListener('input', function() {
    this.classList.remove('error');
    this.placeholder = 'Search by asset tag or model name…';
});

function filterTable(tableId, query) {
    const q = query.toLowerCase();
    const tableElement = document.getElementById(tableId === 'maintTableSearch' ? 'maintTable' : tableId);
    if (tableElement) {
        tableElement.querySelectorAll('tbody tr').forEach(function(row) {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    }
}

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

// ─────────────────────────────────────────────────────────────
// REPAIR FUNCTIONS
// ─────────────────────────────────────────────────────────────

function openRepairForm() {
    document.getElementById('repairFormModal').style.display = 'flex';
}

function closeRepairForm() {
    document.getElementById('repairFormModal').style.display = 'none';
}

function sendRepairNotification(e, repairId, assetTag) {
    e.preventDefault();
    const message = 'Send repair notification for device ' + assetTag + '?';
    if (!confirm(message)) return;
    
    fetch('api_send_repair_notification.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            repair_id: parseInt(repairId),
            asset_tag: assetTag
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Notification sent successfully to IT staff.');
        } else {
            alert('Error: ' + (data.message || 'Failed to send notification'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to send notification');
    });
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

document.getElementById('repairFormModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeRepairForm();
});

document.getElementById('markDoneModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeMarkDone();
});
</script>

<?php require_once 'includes/footer.php'; ?>
