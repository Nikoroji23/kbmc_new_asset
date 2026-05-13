<?php
/**
 * KBMC Asset Management - Device Requests
 */
$pageTitle = 'Device Requests';
require_once 'includes/header.php';

// Submit request (employees)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_request'])) {
    $device_type_id = $_POST['device_type_id'] ?? null;
    $request_reason = trim($_POST['request_reason'] ?? '');
    $urgency = $_POST['urgency'] ?? 'medium';

    try {
        $stmt = $pdo->prepare("INSERT INTO device_requests (requester_id, device_type_id, request_reason, urgency, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->execute([$_SESSION['user_id'], $device_type_id, $request_reason, $urgency]);

        // Notify admins
        $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
        $typeName = $device_type_id ? $pdo->query("SELECT type_name FROM device_types WHERE id = $device_type_id")->fetchColumn() : 'Any';
        foreach ($admins as $admin) {
            addNotification($admin['id'], 'request_approved', 'New Device Request', "New request for $typeName from " . $_SESSION['full_name'], $pdo->lastInsertId());
        }

        setFlashMessage('success', 'Your device request has been submitted.');
        header('Location: requests.php');
        exit();
    } catch (PDOException $e) {
        setFlashMessage('error', 'Error submitting request.');
    }
}

// Approve/Reject request (admin/it_staff)
if (hasRole('admin') || hasRole('it_staff')) {
    if (isset($_GET['action']) && isset($_GET['id'])) {
        $reqId = $_GET['id'];
        $newStatus = $_GET['action'] == 'approve' ? 'approved' : 'rejected';
        $pdo->prepare("UPDATE device_requests SET status = ?, approved_by = ?, approved_date = CURDATE() WHERE id = ?")->execute([$newStatus, $_SESSION['user_id'], $reqId]);

        // Notify requester
        $requester = $pdo->query("SELECT requester_id FROM device_requests WHERE id = $reqId")->fetchColumn();
        addNotification($requester, $newStatus == 'approved' ? 'request_approved' : 'request_rejected',
            $newStatus == 'approved' ? 'Request Approved' : 'Request Rejected',
            "Your device request has been $newStatus.", $reqId);

        setFlashMessage('success', 'Request ' . $newStatus . '.');
        header('Location: requests.php');
        exit();
    }
}

$types = $pdo->query("SELECT * FROM device_types ORDER BY type_name")->fetchAll();

if (hasRole('admin') || hasRole('it_staff')) {
    $stmt = $pdo->query("SELECT dr.*, dt.type_name, u.full_name as requester_name, u.department, ab.full_name as approved_by_name 
        FROM device_requests dr LEFT JOIN device_types dt ON dr.device_type_id = dt.id 
        JOIN users u ON dr.requester_id = u.id LEFT JOIN users ab ON dr.approved_by = ab.id ORDER BY dr.created_at DESC");
} else {
    $stmt = $pdo->prepare("SELECT dr.*, dt.type_name, u.full_name as requester_name, ab.full_name as approved_by_name 
        FROM device_requests dr LEFT JOIN device_types dt ON dr.device_type_id = dt.id 
        JOIN users u ON dr.requester_id = u.id LEFT JOIN users ab ON dr.approved_by = ab.id 
        WHERE dr.requester_id = ? ORDER BY dr.created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
}
$requests = $stmt->fetchAll();
?>

<div class="page-header">
    <h1><i class="fas fa-hand-paper"></i> Device Requests</h1>
    <?php if (isset($_GET['action']) && $_GET['action'] == 'new'): ?>
    <a href="requests.php" class="btn btn-outline">View Requests</a>
    <?php else: ?>
    <a href="requests.php?action=new" class="btn btn-primary"><i class="fas fa-plus"></i> New Request</a>
    <?php endif; ?>
</div>

<?php if (isset($_GET['action']) && $_GET['action'] == 'new'): ?>
<div class="card">
    <div class="card-header"><h3>Submit Device Request</h3></div>
    <div class="card-body">
        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label>Device Type (Optional)</label>
                    <select name="device_type_id" class="form-control">
                        <option value="">Any / No Preference</option>
                        <?php foreach ($types as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo sanitize($t['type_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Urgency</label>
                    <select name="urgency" class="form-control">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
                <div class="form-group full-width">
                    <label>Request Reason <span class="required">*</span></label>
                    <textarea name="request_reason" class="form-control" placeholder="Explain why you need this device..." required></textarea>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <button type="submit" name="submit_request" class="btn btn-primary btn-lg"><i class="fas fa-paper-plane"></i> Submit Request</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3><?php echo (hasRole('admin') || hasRole('it_staff')) ? 'All Requests' : 'My Requests'; ?></h3>
    </div>
    <div class="card-body">
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead><tr><th>ID</th><th>Requester</th><th>Department</th><th>Device Type</th><th>Reason</th><th>Urgency</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                    <tr><td colspan="9" class="empty-state" style="padding: 40px;"><i class="fas fa-hand-paper" style="font-size: 40px; color: #ddd;"></i><h4>No requests found</h4></td></tr>
                    <?php else: ?>
                    <?php foreach ($requests as $r): ?>
                    <tr>
                        <td>#<?php echo $r['id']; ?></td>
                        <td><?php echo sanitize($r['requester_name']); ?></td>
                        <td><?php echo sanitize($r['department']); ?></td>
                        <td><?php echo sanitize($r['type_name'] ?? 'Any'); ?></td>
                        <td><?php echo sanitize(substr($r['request_reason'], 0, 50)) . (strlen($r['request_reason']) > 50 ? '...' : ''); ?></td>
                        <td>
                            <span class="status-badge" style="background: <?php echo $r['urgency'] == 'critical' ? '#E74C3C20' : ($r['urgency'] == 'high' ? '#F39C1220' : '#3498DB20'); ?>; color: <?php echo $r['urgency'] == 'critical' ? '#E74C3C' : ($r['urgency'] == 'high' ? '#F39C12' : '#3498DB'); ?>; border: 1px solid <?php echo $r['urgency'] == 'critical' ? '#E74C3C' : ($r['urgency'] == 'high' ? '#F39C12' : '#3498DB'); ?>;"><?php echo ucfirst($r['urgency']); ?></span>
                        </td>
                        <td>
                            <span class="status-badge" style="background: <?php echo $r['status'] == 'pending' ? '#F39C1220' : ($r['status'] == 'approved' ? '#27AE6020' : ($r['status'] == 'fulfilled' ? '#3498DB20' : '#E74C3C20')); ?>; color: <?php echo $r['status'] == 'pending' ? '#F39C12' : ($r['status'] == 'approved' ? '#27AE60' : ($r['status'] == 'fulfilled' ? '#3498DB' : '#E74C3C')); ?>;"><?php echo ucfirst($r['status']); ?></span>
                        </td>
                        <td><?php echo formatDate($r['created_at']); ?></td>
                        <td>
                            <?php if ($r['status'] == 'pending' && (hasRole('admin') || hasRole('it_staff'))): ?>
                            <div class="action-btns">
                                <a href="requests.php?action=approve&id=<?php echo $r['id']; ?>" class="action-btn assign" title="Approve" onclick="return confirm('Approve this request?')"><i class="fas fa-check"></i></a>
                                <a href="requests.php?action=reject&id=<?php echo $r['id']; ?>" class="action-btn delete" title="Reject" onclick="return confirm('Reject this request?')"><i class="fas fa-times"></i></a>
                            </div>
                            <?php else: ?>
                            <span style="color: #999; font-size: 12px;">-</span>
                            <?php endif; ?>
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
