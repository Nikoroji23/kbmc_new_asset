<?php
/**
 * KBMC Asset Management - IT User Clearance
 * IT staff can review offboarding users, return assigned devices, and generate a printable clearance form.
 */
$pageTitle = 'IT User Clearance';
require_once 'includes/functions.php';
requireITStaff();

$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_clearance') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrfToken)) {
        setFlashMessage('error', 'Invalid request token. Please try again.');
        redirect('it_clearance.php');
    }

    $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    $confirm = isset($_POST['confirm_clearance']) ? true : false;
    $notes = trim($_POST['clearance_notes'] ?? '');

    if ($userId <= 0) {
        $errorMessage = 'Please select a user to clear.';
    } elseif (!$confirm) {
        $errorMessage = 'Please confirm that all items are returned and in good condition.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $errorMessage = 'Selected user was not found or is no longer active.';
        } else {
            $assignStmt = $pdo->prepare("SELECT da.*, d.asset_tag, d.id AS device_id FROM device_assignments da JOIN devices d ON da.device_id = d.id WHERE da.employee_id = ? AND da.status = 'active'");
            $assignStmt->execute([$userId]);
            $assignments = $assignStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($assignments as $assignment) {
                $returnNotes = "Clearance completed by IT. " . ($notes ? "Notes: $notes" : '');
                $pdo->prepare("UPDATE device_assignments SET status = 'returned', returned_date = CURDATE(), notes = CONCAT(COALESCE(notes, ''), ?) WHERE id = ?")
                    ->execute([$returnNotes, $assignment['id']]);
                $pdo->prepare("UPDATE devices SET status = 'in_stock', location = 'IT Stock Room' WHERE id = ?")
                    ->execute([$assignment['device_id']]);
                logAudit($_SESSION['user_id'], 'Clearance Return', 'device_assignments', $assignment['id'], null, 'Offboard clearance return');
            }

            $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")->execute([$userId]);
            logAudit($_SESSION['user_id'], 'Offboard User', 'users', $userId, null, 'User cleared and marked inactive');

            $successMessage = 'Clearance completed successfully. All assigned devices have been returned to stock.';
            setFlashMessage('success', $successMessage);
            redirect('it_clearance.php?user_id=' . $userId);
        }
    }
}

$selectedUserId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
if (empty($selectedUserId) && isset($_POST['user_id'])) {
    $selectedUserId = (int) $_POST['user_id'];
}
$employees = $pdo->query("SELECT id, employee_id, full_name, department, position FROM users WHERE status = 'active' AND role = 'employee' ORDER BY full_name")->fetchAll();
$selectedUser = null;
$assignedDevices = [];

if ($selectedUserId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'employee'");
    $stmt->execute([$selectedUserId]);
    $selectedUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($selectedUser) {
        $assignedDevices = getEmployeeAssignedDevices($selectedUserId);
    }
}

require_once 'includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-user-check"></i> IT User Clearance</h1>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <button class="btn btn-outline no-print" onclick="window.print()"><i class="fas fa-print"></i> Print Clearance Form</button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3>Find Employee for Clearance</h3>
    </div>
    <div class="card-body">
        <?php if ($errorMessage): ?>
            <div class="alert alert-error"><?php echo sanitize($errorMessage); ?></div>
        <?php endif; ?>
        <form method="GET" action="it_clearance.php" class="form-grid" style="align-items:end; gap: 16px;">
            <div class="form-group" style="flex: 1;">
                <label>Select Employee</label>
                <select name="user_id" class="form-control" required>
                    <option value="">Choose an employee</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>" <?php echo $selectedUserId === (int) $emp['id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($emp['full_name'] . ' (' . $emp['employee_id'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="flex: 0 0 auto;">
                <button type="submit" class="btn btn-primary">Load User</button>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedUser): ?>
<div class="card print-section" style="margin-top: 20px;">
    <div class="card-header">
        <h3>Clearance Details</h3>
    </div>
    <div class="card-body">
        <div class="form-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px;">
            <div>
                <div style="font-size:11px;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Employee ID</div>
                <p style="margin:8px 0 16px;font-weight:600;"><?php echo sanitize($selectedUser['employee_id']); ?></p>
            </div>
            <div>
                <div style="font-size:11px;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Full Name</div>
                <p style="margin:8px 0 16px;font-weight:600;"><?php echo sanitize($selectedUser['full_name']); ?></p>
            </div>
            <div>
                <div style="font-size:11px;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Email</div>
                <p style="margin:8px 0 16px;"><?php echo sanitize($selectedUser['email']); ?></p>
            </div>
            <div>
                <div style="font-size:11px;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Phone</div>
                <p style="margin:8px 0 16px;"><?php echo sanitize($selectedUser['phone']); ?></p>
            </div>
            <div>
                <div style="font-size:11px;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Department</div>
                <p style="margin:8px 0 16px;"><?php echo sanitize($selectedUser['department']); ?></p>
            </div>
            <div>
                <div style="font-size:11px;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Position</div>
                <p style="margin:8px 0 16px;"><?php echo sanitize($selectedUser['position']); ?></p>
            </div>
            <div>
                <div style="font-size:11px;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Role</div>
                <p style="margin:8px 0 16px;"><?php echo $role_names[$selectedUser['role']] ?? $selectedUser['role']; ?></p>
            </div>
            <div>
                <div style="font-size:11px;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Status</div>
                <p style="margin:8px 0 16px;"><span class="status-badge" style="background:#27AE6020;color:#27AE60;border:1px solid #27AE60;"><?php echo sanitize(ucfirst($selectedUser['status'])); ?></span></p>
            </div>
        </div>

        <hr style="margin: 18px 0; border-top: 1px solid #eee;">
        <h4 style="font-size: 15px; margin-bottom: 14px;"><i class="fas fa-laptop"></i> Assigned Devices</h4>

        <?php if (empty($assignedDevices)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h4>No active assigned devices</h4>
                <p>There are no active devices currently assigned to this employee.</p>
            </div>
        <?php else: ?>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Asset Tag</th>
                            <th>Device</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Assigned Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignedDevices as $asset): ?>
                        <tr>
                            <td><?php echo sanitize($asset['asset_tag']); ?></td>
                            <td><?php echo sanitize($asset['brand'] . ' ' . $asset['model']); ?></td>
                            <td><?php echo sanitize($asset['type_name']); ?></td>
                            <td><?php echo getStatusBadgeHtml($asset['status']); ?></td>
                            <td><?php echo formatDate($asset['assigned_date']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div style="margin-top: 22px;">
            <form method="POST">
                <?php echo csrfInputField(); ?>
                <input type="hidden" name="action" value="process_clearance">
                <input type="hidden" name="user_id" value="<?php echo sanitize($selectedUser['id']); ?>">

                <div class="form-group">
                    <label class="form-label"><strong>Clearance Confirmation</strong></label>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <input type="checkbox" name="confirm_clearance" id="confirm_clearance" value="1" required>
                        <label for="confirm_clearance">I confirm all assigned devices are returned and in good condition.</label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="clearance_notes">Return Notes (optional)</label>
                    <textarea name="clearance_notes" id="clearance_notes" class="form-control" rows="4" placeholder="Add condition remarks, missing accessories, or handover notes."></textarea>
                </div>

                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <button type="submit" class="btn btn-danger no-print"><i class="fas fa-check"></i> Complete Clearance</button>
                    <button type="button" class="btn btn-outline no-print" onclick="window.print()"><i class="fas fa-print"></i> Print Clearance Form</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
@media print {
    .no-print { display: none !important; }
    .sidebar, .top-header, .app-footer, .btn, .alert, .form-control, .form-group, .card-header a { display: none !important; }
    .main-wrapper { margin: 0; }
    .page-header, .card { box-shadow: none; border: none; }
    .print-section { width: 100%; }
}
</style>

<?php require_once 'includes/footer.php'; ?>
