<?php
/**
 * KBMC Asset Management - Audit Logs (Admin only)
 */
$pageTitle = 'Audit Logs';
require_once 'includes/header.php';
requireAdmin();

$logs = $pdo->query("SELECT al.*, u.full_name FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 200")->fetchAll();
?>

<div class="page-header">
    <h1><i class="fas fa-history"></i> Audit Logs</h1>
    <button class="btn btn-outline" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
</div>

<div class="card">
    <div class="card-body">
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead><tr><th>Date</th><th>User</th><th>Action</th><th>Table</th><th>Record ID</th><th>IP Address</th></tr></thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="6" class="empty-state" style="padding: 40px;"><h4>No audit logs</h4></td></tr>
                    <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></td>
                        <td><strong><?php echo sanitize($log['full_name'] ?? 'System'); ?></strong></td>
                        <td><?php echo sanitize($log['action']); ?></td>
                        <td><?php echo sanitize($log['table_name'] ?? 'N/A'); ?></td>
                        <td><?php echo $log['record_id'] ?? 'N/A'; ?></td>
                        <td><?php echo sanitize($log['ip_address'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
