<?php
/**
 * KBMC Asset Management - Audit Logs (Admin only)
 */
$pageTitle = 'Audit Logs';
require_once 'includes/header.php';
requireAdmin();

$logs = $pdo->query("SELECT al.*, u.full_name FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 500")->fetchAll();

// Get distinct users, actions, and tables for filtering
$distinctUsers = $pdo->query("SELECT DISTINCT u.id, u.full_name FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id WHERE u.id IS NOT NULL ORDER BY u.full_name")->fetchAll();
$distinctActions = $pdo->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll();
$distinctTables = $pdo->query("SELECT DISTINCT table_name FROM audit_logs ORDER BY table_name")->fetchAll();
?>

<div class="page-header">
    <h1><i class="fas fa-history"></i> Audit Logs</h1>
    <button class="btn btn-outline" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-history"></i> Audit Logs</h3>
    </div>
    <div class="card-body">
        <!-- Filtering Section -->
        <div style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px; border-left: 3px solid #3498db;">
            <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 12px; font-weight: 600;">Filter by User</label>
                    <select id="filterUser" class="form-control" onchange="filterAuditLogs()">
                        <option value="">All Users</option>
                        <?php foreach ($distinctUsers as $user): ?>
                        <option value="<?php echo $user['id']; ?>"><?php echo sanitize($user['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 12px; font-weight: 600;">Filter by Action</label>
                    <select id="filterAction" class="form-control" onchange="filterAuditLogs()">
                        <option value="">All Actions</option>
                        <?php foreach ($distinctActions as $action): ?>
                        <option value="<?php echo strtolower($action['action']); ?>"><?php echo sanitize($action['action']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 12px; font-weight: 600;">Filter by Table</label>
                    <select id="filterTable" class="form-control" onchange="filterAuditLogs()">
                        <option value="">All Tables</option>
                        <?php foreach ($distinctTables as $table): ?>
                        <option value="<?php echo strtolower($table['table_name']); ?>"><?php echo sanitize($table['table_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label style="font-size: 12px; font-weight: 600;">Search (User/IP)</label>
                    <input type="text" id="filterSearch" class="form-control" placeholder="Search..." onkeyup="filterAuditLogs()">
                </div>
                <div class="form-group" style="margin-bottom: 0; display: flex; align-items: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="clearAuditFilters()" style="width: 100%;"><i class="fas fa-times"></i> Clear Filters</button>
                </div>
                <div class="form-group" style="margin-bottom: 0; display: flex; align-items: flex-end;">
                    <button class="btn btn-outline" onclick="window.print()" style="width: 100%;"><i class="fas fa-print"></i> Print</button>
                </div>
            </div>
        </div>

        <!-- Audit Logs Table -->
        <div class="data-table-wrapper">
            <table class="data-table" id="auditLogsTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Table</th>
                        <th>Record ID</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="6" class="empty-state" style="padding: 40px;"><h4>No audit logs</h4></td></tr>
                    <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <tr class="audit-row" data-user="<?php echo $log['user_id'] ?? ''; ?>" data-action="<?php echo strtolower($log['action']); ?>" data-table="<?php echo strtolower($log['table_name'] ?? ''); ?>" data-search="<?php echo strtolower((sanitize($log['full_name'] ?? '') . ' ' . sanitize($log['ip_address'] ?? ''))); ?>">
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

<script>
function filterAuditLogs() {
    const filterUser = document.getElementById('filterUser').value;
    const filterAction = document.getElementById('filterAction').value.toLowerCase();
    const filterTable = document.getElementById('filterTable').value.toLowerCase();
    const filterSearch = document.getElementById('filterSearch').value.toLowerCase();
    
    const rows = document.querySelectorAll('.audit-row');
    
    rows.forEach(row => {
        let show = true;
        
        // Filter by user
        if (filterUser && row.dataset.user !== filterUser) {
            show = false;
        }
        
        // Filter by action
        if (filterAction && row.dataset.action !== filterAction) {
            show = false;
        }
        
        // Filter by table
        if (filterTable && row.dataset.table !== filterTable) {
            show = false;
        }
        
        // Filter by search (user name or IP address)
        if (filterSearch && !row.dataset.search.includes(filterSearch)) {
            show = false;
        }
        
        row.style.display = show ? '' : 'none';
    });
}

function clearAuditFilters() {
    document.getElementById('filterUser').value = '';
    document.getElementById('filterAction').value = '';
    document.getElementById('filterTable').value = '';
    document.getElementById('filterSearch').value = '';
    filterAuditLogs();
}
</script>

<?php require_once 'includes/footer.php'; ?>
