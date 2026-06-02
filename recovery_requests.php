<?php
/**
 * KBMC Asset Management - Account Recovery Requests
 * Admin interface to manage employee account recovery requests
 */
$pageTitle = 'Account Recovery Requests';
require_once 'includes/header.php';
requireAdmin();

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        empty($_POST['csrf_token']) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        http_response_code(403);
        die('CSRF verification failed.');
    }

    $action = $_POST['action'] ?? '';
    $recoveryId = (int)($_POST['recovery_id'] ?? 0);
    
    // For approval, require master key validation
    if ($action === 'approve') {
        $masterKey = $_POST['master_key'] ?? '';
        if (empty($masterKey)) {
            http_response_code(400);
            die('Master key is required to approve recovery requests.');
        }
        
        // Validate master key
        if (!verifyMasterKey($_SESSION['user_id'], $masterKey)) {
            http_response_code(403);
            die('Invalid master key. Approval denied.');
        }
    }
    
    if ($action && $recoveryId) {
        processRecoveryRequest($recoveryId, $action, $_SESSION['user_id']);
        
        $message = $action === 'approve' 
            ? 'Account recovery request approved successfully.' 
            : 'Account recovery request rejected.';
        
        setFlashMessage('success', $message);
        header('Location: recovery_requests.php');
        exit;
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Get filter parameters
$status = $_GET['status'] ?? 'pending';
$sort = $_GET['sort'] ?? 'requested_at DESC';

// Build query
$allowedStatuses = ['pending', 'approved', 'rejected', 'all'];
$status = in_array($status, $allowedStatuses) ? $status : 'pending';

$whereClause = '';
if ($status !== 'all') {
    $whereClause = "WHERE ar.status = '$status'";
}

$stmt = $pdo->query("
    SELECT ar.*, u.full_name, u.email, u.department, u.employee_id, u.status as user_status
    FROM account_recovery_requests ar
    JOIN users u ON ar.user_id = u.id
    $whereClause
    ORDER BY ar.requested_at DESC
");
$requests = $stmt->fetchAll();

// Count by status
$countPending = $pdo->query("SELECT COUNT(*) FROM account_recovery_requests WHERE status = 'pending'")->fetchColumn();
$countApproved = $pdo->query("SELECT COUNT(*) FROM account_recovery_requests WHERE status = 'approved'")->fetchColumn();
$countRejected = $pdo->query("SELECT COUNT(*) FROM account_recovery_requests WHERE status = 'rejected'")->fetchColumn();
?>

<div class="page-header">
    <h1><i class="fas fa-user-shield"></i> Account Recovery Requests</h1>
    <div style="display: flex; gap: 10px; align-items: center;">
        <span style="font-size: 13px; color: #666;">
            <strong><?php echo count($requests); ?></strong> request<?php echo count($requests) !== 1 ? 's' : ''; ?>
        </span>
    </div>
</div>

<!-- Status Tabs -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="display: flex; gap: 10px; flex-wrap: wrap; padding: 12px;">
        <a href="recovery_requests.php?status=pending" class="btn <?php echo $status === 'pending' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 13px;">
            <i class="fas fa-hourglass-end"></i> Pending <span style="background: <?php echo $status === 'pending' ? 'rgba(255,255,255,0.3)' : '#f0f0f0'; ?>; padding: 2px 8px; border-radius: 10px; margin-left: 6px; font-weight: 700;"><?php echo $countPending; ?></span>
        </a>
        <a href="recovery_requests.php?status=approved" class="btn <?php echo $status === 'approved' ? 'btn-success' : 'btn-outline'; ?>" style="font-size: 13px;">
            <i class="fas fa-check-circle"></i> Approved <span style="background: <?php echo $status === 'approved' ? 'rgba(255,255,255,0.3)' : '#f0f0f0'; ?>; padding: 2px 8px; border-radius: 10px; margin-left: 6px; font-weight: 700;"><?php echo $countApproved; ?></span>
        </a>
        <a href="recovery_requests.php?status=rejected" class="btn <?php echo $status === 'rejected' ? 'btn-danger' : 'btn-outline'; ?>" style="font-size: 13px;">
            <i class="fas fa-times-circle"></i> Rejected <span style="background: <?php echo $status === 'rejected' ? 'rgba(255,255,255,0.3)' : '#f0f0f0'; ?>; padding: 2px 8px; border-radius: 10px; margin-left: 6px; font-weight: 700;"><?php echo $countRejected; ?></span>
        </a>
        <a href="recovery_requests.php?status=all" class="btn <?php echo $status === 'all' ? 'btn-info' : 'btn-outline'; ?>" style="font-size: 13px;">
            <i class="fas fa-list"></i> All <span style="background: <?php echo $status === 'all' ? 'rgba(255,255,255,0.3)' : '#f0f0f0'; ?>; padding: 2px 8px; border-radius: 10px; margin-left: 6px; font-weight: 700;"><?php echo count($requests); ?></span>
        </a>
    </div>
</div>

<!-- Requests Table -->
<div class="card">
    <div class="card-body">
        <?php if (empty($requests)): ?>
        <div class="empty-state" style="padding: 60px; text-align: center;">
            <i class="fas fa-user-check" style="font-size: 40px; color: #ddd; margin-bottom: 15px; display: block;"></i>
            <h4 style="color: #aaa; font-weight: 500;">No recovery requests found</h4>
            <p style="color: #bbb; margin-top: 10px;">
                <?php echo $status === 'pending' 
                    ? 'All pending requests have been processed.' 
                    : 'No requests match the selected filter.'; ?>
            </p>
        </div>
        <?php else: ?>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Reason</th>
                        <th>Requested</th>
                        <th>Status</th>
                        <th style="width: 150px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $req): ?>
                    <tr>
                        <td>
                            <strong><?php echo sanitize($req['full_name']); ?></strong>
                            <br><small style="color: #999;"><?php echo sanitize($req['employee_id'] ?? 'N/A'); ?></small>
                        </td>
                        <td><?php echo sanitize($req['email']); ?></td>
                        <td><?php echo sanitize($req['department'] ?? 'N/A'); ?></td>
                        <td style="max-width: 250px;">
                            <small><?php echo sanitize($req['request_reason'] ?? 'N/A'); ?></small>
                        </td>
                        <td>
                            <small><?php echo formatDate($req['requested_at'], 'M d, Y h:i A'); ?></small>
                        </td>
                        <td>
                            <?php 
                            $statusColor = [
                                'pending' => '#F39C12',
                                'approved' => '#27AE60',
                                'rejected' => '#E74C3C'
                            ];
                            $statusColor = $statusColor[$req['status']] ?? '#999';
                            ?>
                            <span style="display: inline-block; padding: 3px 10px; border-radius: 3px; font-size: 11px; font-weight: 600; background: <?php echo $statusColor; ?>20; color: <?php echo $statusColor; ?>;">
                                <?php echo ucfirst($req['status']); ?>
                            </span>
                        </td>
                        <td style="text-align: center; white-space: nowrap;">
                            <?php if ($req['status'] === 'pending'): ?>
                                <button type="button" class="action-btn assign" title="Approve" 
                                        onclick="openMasterKeyDialog(<?php echo $req['id']; ?>, '<?php echo sanitize($req['full_name']); ?>')">
                                    <i class="fas fa-check"></i>
                                </button>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="recovery_id" value="<?php echo $req['id']; ?>">
                                    <button type="submit" class="action-btn delete" title="Reject" onclick="return confirm('Reject recovery request for <?php echo sanitize($req['full_name']); ?>?')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span style="color: #999; font-size: 12px;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Master Key Verification Modal -->
<div id="masterKeyModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 30px; max-width: 450px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.2);">
        <h3 style="margin-top: 0; color: #2c3e50; display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-key" style="color: #e74c3c; font-size: 24px;"></i>
            Master Key Required
        </h3>
        <p style="color: #666; margin: 15px 0; font-size: 13px;">
            Approving an account recovery request is a sensitive operation. Please enter your master key to continue.
        </p>
        
        <form id="masterKeyForm" method="POST" style="margin-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="recovery_id" id="recoveryId" value="">
            
            <div style="margin-bottom: 18px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #2c3e50; margin-bottom: 8px;">
                    Master Key
                </label>
                <input type="password" name="master_key" id="masterKeyInput" 
                       class="form-control" placeholder="Enter master key" required
                       style="width: 100%; padding: 11px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px;">
            </div>
            
            <div style="background: #fef5e7; border-left: 4px solid #f39c12; padding: 12px; border-radius: 0 6px 6px 0; margin-bottom: 20px; font-size: 12px; color: #7d6608;">
                <strong>Tip:</strong> You can find your master key in the admin vault or security settings.
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="button" onclick="closeMasterKeyDialog()" 
                        style="flex: 1; padding: 11px; border: 1px solid #ddd; background: white; color: #333; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                    Cancel
                </button>
                <button type="submit" 
                        style="flex: 1; padding: 11px; background: #27ae60; color: white; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                    <i class="fas fa-check"></i> Approve
                </button>
            </div>
        </form>
    </div>
</div>

<style>
#masterKeyModal.active {
    display: flex !important;
}

#masterKeyModal input:focus {
    outline: none;
    border-color: #27ae60 !important;
    box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.1);
}

#masterKeyModal button:hover {
    opacity: 0.9;
}
</style>

<script>
let currentRecoveryId = null;
let currentEmployeeName = null;

function openMasterKeyDialog(recoveryId, employeeName) {
    currentRecoveryId = recoveryId;
    currentEmployeeName = employeeName;
    document.getElementById('recoveryId').value = recoveryId;
    document.getElementById('masterKeyInput').value = '';
    document.getElementById('masterKeyModal').classList.add('active');
    document.getElementById('masterKeyInput').focus();
}

function closeMasterKeyDialog() {
    document.getElementById('masterKeyModal').classList.remove('active');
    document.getElementById('masterKeyForm').reset();
    currentRecoveryId = null;
    currentEmployeeName = null;
}

document.getElementById('masterKeyForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const masterKey = document.getElementById('masterKeyInput').value;
    if (!masterKey.trim()) {
        alert('Please enter your master key.');
        return;
    }
    
    // Submit the form
    this.submit();
});

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeMasterKeyDialog();
    }
});

// Close modal when clicking outside
document.getElementById('masterKeyModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeMasterKeyDialog();
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
