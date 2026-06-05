<?php
/**
 * KBMC Asset Management - Approve New IT Users
 * Master IT user validation page
 * Only accessible to the designated master IT user
 */
$pageTitle = 'Approve IT Users';
require_once 'includes/header.php';

// Check if user is logged in and is an IT staff member
requireLogin();
if (!hasRole('it_staff')) {
    setFlashMessage('error', 'Access denied. IT staff only.');
    header('Location: dashboard.php');
    exit();
}

// Check if this is the master IT user
$isMaster = isMasterITUser($_SESSION['user_id']);
if (!$isMaster) {
    setFlashMessage('error', 'Access denied. Master IT administrator only.');
    header('Location: it_dashboard.php');
    exit();
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $itUserId = (int)($_POST['it_user_id'] ?? 0);
    $masterKeySecret = $_POST['master_key_secret'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    if (!$itUserId || !$masterKeySecret) {
        setFlashMessage('error', 'Missing required fields.');
        header('Location: approve_it_users.php');
        exit();
    }

    if ($action === 'approve') {
        $result = approveITUserWithMasterKey($itUserId, $masterKeySecret);
        if ($result['success']) {
            setFlashMessage('success', $result['message']);
        } else {
            setFlashMessage('error', $result['message']);
        }
    } elseif ($action === 'reject') {
        $result = rejectITUserApproval($itUserId, $reason);
        if ($result['success']) {
            setFlashMessage('success', $result['message']);
        } else {
            setFlashMessage('error', $result['message']);
        }
    }

    header('Location: approve_it_users.php');
    exit();
}

// Get pending IT user approvals
$pendingUsers = getPendingITUserApprovals();
$hasPending = !empty($pendingUsers);
?>

<div class="page-header">
    <h1><i class="fas fa-user-shield"></i> IT User Approvals</h1>
    <p style="margin: 10px 0 0 0; color: #666; font-size: 14px;">
        <i class="fas fa-lock"></i> Master IT Administrator - Manage IT Staff User Approvals
    </p>
</div>

<?php if ($hasPending): ?>
    <div class="card" style="margin-bottom: 20px;">
        <div class="card-header" style="background: #fff3cd; border-color: #ffc107; color: #856404;">
            <i class="fas fa-exclamation-triangle"></i>
            <strong><?php echo count($pendingUsers); ?> Pending IT User<?php echo count($pendingUsers) !== 1 ? 's' : ''; ?></strong>
        </div>
        <div class="card-body">
            <p style="margin-bottom: 20px; color: #666;">
                New IT staff accounts are created as inactive until approved by the master IT administrator. 
                Review the pending users below and use your master key to approve or reject them.
            </p>

            <?php foreach ($pendingUsers as $user): ?>
                <div class="pending-user-card" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 8px; background: #f8f9fa;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label style="display: block; font-weight: 600; color: #333; margin-bottom: 5px; font-size: 12px;">Full Name</label>
                            <p style="margin: 0;"><?php echo htmlspecialchars($user['full_name']); ?></p>
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; color: #333; margin-bottom: 5px; font-size: 12px;">Email</label>
                            <p style="margin: 0;"><a href="mailto:<?php echo htmlspecialchars($user['email']); ?>"><?php echo htmlspecialchars($user['email']); ?></a></p>
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; color: #333; margin-bottom: 5px; font-size: 12px;">Employee ID</label>
                            <p style="margin: 0;"><?php echo htmlspecialchars($user['employee_id']); ?></p>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label style="display: block; font-weight: 600; color: #333; margin-bottom: 5px; font-size: 12px;">Department</label>
                            <p style="margin: 0;"><?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; color: #333; margin-bottom: 5px; font-size: 12px;">Position</label>
                            <p style="margin: 0;"><?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?></p>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; font-weight: 600; color: #333; margin-bottom: 5px; font-size: 12px;">Created</label>
                            <p style="margin: 0;"><?php echo date('M d, Y h:i A', strtotime($user['created_at'])); ?></p>
                        </div>
                        <div>
                            <label style="display: block; font-weight: 600; color: #333; margin-bottom: 5px; font-size: 12px;">Status</label>
                            <p style="margin: 0;"><span class="badge" style="background: #ffc107; color: #333; padding: 5px 10px; border-radius: 4px; font-size: 12px;">INACTIVE (Pending Approval)</span></p>
                        </div>
                    </div>

                    <!-- Approval Section -->
                    <div style="background: #e7f3ff; border: 1px solid #b3d9ff; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                        <h4 style="margin: 0 0 15px 0; color: #004085;">Approve This User</h4>
                        <form method="POST" style="display: grid; grid-template-columns: 1fr 120px; gap: 10px;">
                            <div>
                                <label for="master_key_<?php echo $user['id']; ?>" style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 12px;">Master Key Secret</label>
                                <input 
                                    type="password" 
                                    id="master_key_<?php echo $user['id']; ?>" 
                                    name="master_key_secret" 
                                    class="form-control" 
                                    placeholder="Enter master key secret to approve"
                                    required>
                                <small style="color: #666; margin-top: 5px; display: block;">Enter your master key to validate this approval</small>
                            </div>
                            <div>
                                <input type="hidden" name="it_user_id" value="<?php echo $user['id']; ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="btn btn-success" style="width: 100%; margin-top: 22px;">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Rejection Section -->
                    <button type="button" class="btn btn-outline-danger" style="font-size: 12px;" onclick="showRejectForm(<?php echo $user['id']; ?>)">
                        <i class="fas fa-times"></i> Reject This User
                    </button>

                    <!-- Hidden Rejection Form -->
                    <form method="POST" id="reject-form-<?php echo $user['id']; ?>" style="display: none; background: #ffe7e7; border: 1px solid #ffb3b3; padding: 15px; border-radius: 6px; margin-top: 15px;">
                        <input type="hidden" name="it_user_id" value="<?php echo $user['id']; ?>">
                        <input type="hidden" name="action" value="reject">
                        
                        <h4 style="margin: 0 0 15px 0; color: #721c24;">Reject This User</h4>
                        <div style="margin-bottom: 15px;">
                            <label for="reason_<?php echo $user['id']; ?>" style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 12px;">Reason for Rejection</label>
                            <textarea 
                                id="reason_<?php echo $user['id']; ?>" 
                                name="reason" 
                                class="form-control" 
                                placeholder="Explain why this user is being rejected"
                                rows="3"
                                required></textarea>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-danger" style="font-size: 12px;">
                                <i class="fas fa-trash"></i> Confirm Rejection
                            </button>
                            <button type="button" class="btn btn-outline" style="font-size: 12px;" onclick="hideRejectForm(<?php echo $user['id']; ?>)">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 40px 20px;">
            <i class="fas fa-check-circle" style="font-size: 48px; color: #28a745; margin-bottom: 15px; display: block;"></i>
            <h3 style="margin: 0 0 10px 0; color: #28a745;">No Pending Approvals</h3>
            <p style="color: #666; margin: 0;">All IT staff users have been approved and activated.</p>
        </div>
    </div>
<?php endif; ?>

<!-- Master IT Information Card -->
<div class="card" style="margin-top: 30px;">
    <div class="card-header" style="background: #f8f9fa; border-color: #dee2e6;">
        <strong><i class="fas fa-info-circle"></i> System Information</strong>
    </div>
    <div class="card-body">
        <div style="background: #e7f3ff; border-left: 4px solid #0066cc; padding: 15px; border-radius: 6px;">
            <p style="margin: 0 0 10px 0;"><strong>Master IT Administrator:</strong></p>
            <p style="margin: 0 0 15px 0; font-family: monospace;">alfonsoaninias0527@gmail.com</p>
            
            <p style="margin: 0 0 10px 0;"><strong>About IT User Approvals:</strong></p>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li>New IT staff accounts are created in an INACTIVE state</li>
                <li>Only the master IT administrator can approve new IT users</li>
                <li>Use your master key secret to approve or reject users</li>
                <li>All approval/rejection actions are logged in the audit trail</li>
                <li>Email notifications are sent to the IT user about their account status</li>
            </ul>
        </div>
    </div>
</div>

<script>
function showRejectForm(userId) {
    document.getElementById('reject-form-' + userId).style.display = 'block';
}

function hideRejectForm(userId) {
    document.getElementById('reject-form-' + userId).style.display = 'none';
}
</script>

<?php require_once 'includes/footer.php'; ?>
