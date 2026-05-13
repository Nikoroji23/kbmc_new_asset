<?php
/**
 * KBMC Asset Management - User Management (Admin only)
 */
$pageTitle = 'Manage Users';
require_once 'includes/header.php';
requireAdmin();

// Handle account recovery approval/rejection
if (isset($_GET['recover_action']) && isset($_GET['recover_id'])) {
    $recoverId = $_GET['recover_id'];
    $action = $_GET['recover_action'];

    $stmt = $pdo->prepare("SELECT ar.*, u.email, u.full_name FROM account_recovery_requests ar JOIN users u ON ar.user_id = u.id WHERE ar.id = ?");
    $stmt->execute([$recoverId]);
    $req = $stmt->fetch();

    if ($req) {
        if ($action == 'approve') {
            // Reactivate user account
            $pdo->prepare("UPDATE users SET status = 'active', failed_logins = 0, locked_until = NULL WHERE id = ?")
                ->execute([$req['user_id']]);
            $pdo->prepare("UPDATE account_recovery_requests SET status = 'approved', resolved_at = NOW(), resolved_by = ? WHERE id = ?")
                ->execute([$_SESSION['user_id'], $recoverId]);

            // Notify user
            $emailBody = emailTemplate(
                'Account Reactivated',
                "<p>Hello <strong>" . sanitize($req['full_name']) . "</strong>,</p>
                <p>Your account recovery request has been <strong style='color: #27ae60;'>APPROVED</strong>.</p>
                <p>Your account is now active and you can log in using your existing credentials.</p>",
                'Go to Login',
                (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/login.php'
            );
            sendEmail($req['email'], 'Account Reactivated', $emailBody);

            setFlashMessage('success', 'Account recovery approved. User has been notified.');
        } else {
            $pdo->prepare("UPDATE account_recovery_requests SET status = 'rejected', resolved_at = NOW(), resolved_by = ? WHERE id = ?")
                ->execute([$_SESSION['user_id'], $recoverId]);

            $emailBody = emailTemplate(
                'Account Recovery Rejected',
                "<p>Hello <strong>" . sanitize($req['full_name']) . "</strong>,</p>
                <p>Your account recovery request has been <strong style='color: #e74c3c;'>REJECTED</strong>.</p>
                <p>Please contact your IT administrator for more information.</p>"
            );
            sendEmail($req['email'], 'Account Recovery Rejected', $emailBody);

            setFlashMessage('warning', 'Account recovery rejected. User has been notified.');
        }
    }
    header('Location: users.php');
    exit();
}


// Add user
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    $employee_id = trim($_POST['employee_id'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'employee';
    $department = trim($_POST['department'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($full_name && $email && $password) {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (employee_id, full_name, email, password, role, department, position, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$employee_id, $full_name, $email, password_hash($password, PASSWORD_BCRYPT), $role, $department, $position, $phone]);
            setFlashMessage('success', "User $full_name added successfully.");
            header('Location: users.php');
            exit();
        } catch (PDOException $e) {
            setFlashMessage('error', 'Error: ' . $e->getMessage());
        }
    }
}

// Toggle user status
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $userId = $_GET['id'];
    $current = $pdo->query("SELECT status FROM users WHERE id = $userId")->fetchColumn();
    $newStatus = $current == 'active' ? 'inactive' : 'active';
    $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$newStatus, $userId]);
    setFlashMessage('success', "User status updated to $newStatus.");
    header('Location: users.php');
    exit();
}

$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
?>

<div class="page-header">
    <h1><i class="fas fa-users-cog"></i> Manage Users</h1>
    <button class="btn btn-primary" data-modal="addUserModal"><i class="fas fa-plus"></i> Add User</button>
</div>

<div class="card">
    <div class="card-body">
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Position</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($users)): ?>
                    <tr><td colspan="9" class="empty-state" style="padding: 40px;"><h4>No users found</h4></td></tr>
                    <?php else: ?>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?php echo sanitize($u['employee_id'] ?: 'N/A'); ?></td>
                        <td><strong><?php echo sanitize($u['full_name']); ?></strong></td>
                        <td><?php echo sanitize($u['email']); ?></td>
                        <td><?php echo $role_names[$u['role']] ?? $u['role']; ?></td>
                        <td><?php echo sanitize($u['department']); ?></td>
                        <td><?php echo sanitize($u['position']); ?></td>
                        <td><span class="status-badge" style="background: <?php echo $u['status'] == 'active' ? '#27AE6020' : '#E74C3C20'; ?>; color: <?php echo $u['status'] == 'active' ? '#27AE60' : '#E74C3C'; ?>; border: 1px solid <?php echo $u['status'] == 'active' ? '#27AE60' : '#E74C3C'; ?>;"><?php echo ucfirst($u['status']); ?></span></td>
                        <td><?php echo formatDate($u['created_at']); ?></td>
                        <td>
                            <a href="users.php?toggle=1&id=<?php echo $u['id']; ?>" class="btn btn-sm <?php echo $u['status'] == 'active' ? 'btn-danger' : 'btn-success'; ?>" onclick="return confirm('<?php echo $u['status'] == 'active' ? 'Deactivate' : 'Activate'; ?> this user?')">
                                <?php echo $u['status'] == 'active' ? '<i class="fas fa-ban"></i> Deactivate' : '<i class="fas fa-check"></i> Activate'; ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Add New User</h3>
            <button class="modal-close" data-dismiss="modal">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Employee ID</label>
                        <input type="text" name="employee_id" class="form-control" placeholder="KBMC-EMP-001">
                    </div>
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Password <span class="required">*</span></label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select name="role" class="form-control">
                            <option value="employee">Employee</option>
                            <option value="it_staff">IT Staff</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" name="department" class="form-control" placeholder="Sales Department">
                    </div>
                    <div class="form-group">
                        <label>Position</label>
                        <input type="text" name="position" class="form-control" placeholder="Sales Associate">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" placeholder="+63...">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
                <button type="submit" name="add_user" class="btn btn-primary"><i class="fas fa-save"></i> Add User</button>
            </div>
        </form>
    </div>
</div>


<!-- Account Recovery Requests -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-user-shield"></i> Account Recovery Requests</h3>
    </div>
    <div class="card-body">
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $recoveryRequests = getPendingRecoveryRequests();
                    if (empty($recoveryRequests)): 
                    ?>
                    <tr>
                        <td colspan="7" class="empty-state" style="padding: 40px;">
                            <i class="fas fa-check-circle" style="font-size: 40px; color: #27AE60;"></i>
                            <h4 style="margin-top: 10px;">No pending recovery requests</h4>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($recoveryRequests as $req): ?>
                    <tr>
                        <td><?php echo formatDate($req['requested_at'], 'M d, Y h:i A'); ?></td>
                        <td><strong><?php echo sanitize($req['full_name']); ?></strong><br><small><?php echo sanitize($req['employee_id'] ?: 'N/A'); ?></small></td>
                        <td><?php echo sanitize($req['email']); ?></td>
                        <td><?php echo sanitize($req['department'] ?: 'N/A'); ?></td>
                        <td><?php echo sanitize(substr($req['request_reason'], 0, 50)) . (strlen($req['request_reason']) > 50 ? '...' : ''); ?></td>
                        <td>
                            <span class="status-badge" style="background: #F39C1220; color: #F39C12; border: 1px solid #F39C12;">Pending</span>
                        </td>
                        <td>
                            <div class="action-btns">
                                <a href="users.php?recover_action=approve&recover_id=<?php echo $req['id']; ?>" 
                                   class="action-btn assign" title="Approve & Reactivate"
                                   onclick="return confirm('Approve account recovery and reactivate user?')">
                                    <i class="fas fa-check"></i>
                                </a>
                                <a href="users.php?recover_action=reject&recover_id=<?php echo $req['id']; ?>" 
                                   class="action-btn delete" title="Reject"
                                   onclick="return confirm('Reject this recovery request?')">
                                    <i class="fas fa-times"></i>
                                </a>
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


<?php require_once 'includes/footer.php'; ?>
