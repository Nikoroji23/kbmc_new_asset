<?php
/**
 * KBMC Asset Management - Admin Dashboard
 * Exclusive dashboard for Administrators with security controls
 */
$pageTitle = 'Admin Dashboard';
require_once 'includes/header.php';
requireAdmin();

// Check if user is a security IT approver
$isSecurityAdmin = isSecurityAdmin($_SESSION['user_id']);

// Get admin-specific statistics
$totalDevices = getTotalDeviceCount();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$itStaffCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'it_staff' AND status = 'active'")->fetchColumn();
$adminCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn();
$employeeCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'employee' AND status = 'active'")->fetchColumn();
$pendingApprovals = $pdo->query("SELECT COUNT(*) FROM user_approval_requests WHERE status = 'pending'")->fetchColumn();

// Get pending user approvals
$approvalRequests = getPendingUserApprovals();

// Get recent audit logs
$stmt = $pdo->query("SELECT al.*, u.full_name FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 10");
$recentLogs = $stmt->fetchAll();

// Get account recovery requests
$stmt = $pdo->query("SELECT ar.*, u.full_name, u.email FROM account_recovery_requests ar JOIN users u ON ar.user_id = u.id WHERE ar.status = 'pending' ORDER BY ar.requested_at DESC LIMIT 5");
$recoveryRequests = $stmt->fetchAll();

// Master Key: handle regeneration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'regen_master_key') {
    $uid    = (int)$_POST['user_id'];
    $newKey = strtoupper(bin2hex(random_bytes(4)));
    $pdo->prepare("UPDATE users SET master_key = :mk WHERE id = :id")->execute([':mk' => $newKey, ':id' => $uid]);
    header("Location: admin_dashboard.php?mk_msg=Master+key+regenerated+successfully");
    exit;
}

// Master Key: fetch all users with their keys (searchable)
$mkSearch = trim($_GET['mk_search'] ?? '');
$mkWhere  = "WHERE 1=1";
$mkParams = [];
if ($mkSearch !== '') {
    $mkWhere .= " AND (full_name LIKE :s OR employee_id LIKE :s OR department LIKE :s)";
    $mkParams[':s'] = "%$mkSearch%";
}
$mkStmt = $pdo->prepare("SELECT id, employee_id, full_name, email, role, department, master_key, status FROM users $mkWhere ORDER BY full_name ASC");
$mkStmt->execute($mkParams);
$mkUsers = $mkStmt->fetchAll();

// Auto-generate missing master keys on first load
foreach ($mkUsers as $mu) {
    if (empty($mu['master_key'])) {
        $key = strtoupper(bin2hex(random_bytes(4)));
        $pdo->prepare("UPDATE users SET master_key = :mk WHERE id = :id")->execute([':mk' => $key, ':id' => $mu['id']]);
    }
}
// Re-fetch after backfill
$mkStmt->execute($mkParams);
$mkUsers = $mkStmt->fetchAll();
?>

<!-- Master Key styles (scoped, no conflict) -->
<style>
.mk-panel { background:#fff; border-radius:8px; border:2px solid #f0c040; margin-top:20px; overflow:hidden; }
.mk-header { background:linear-gradient(135deg,#f39c12,#d68910); color:#fff; padding:16px 20px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
.mk-header-text h3 { margin:0; font-size:16px; font-weight:700; }
.mk-header-text p  { margin:4px 0 0; font-size:12px; opacity:.85; }
.mk-search { display:flex; gap:8px; }
.mk-search input  { padding:7px 12px; border-radius:6px; border:none; font-size:13px; min-width:200px; outline:none; }
.mk-search button { padding:7px 14px; background:rgba(0,0,0,.2); color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:13px; font-weight:600; }
.mk-search button:hover { background:rgba(0,0,0,.35); }
.mk-table-wrap { overflow-x:auto; }
table.mk-tbl { width:100%; border-collapse:collapse; font-size:13px; }
.mk-tbl thead th { background:#fffbf0; color:#7d6000; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; padding:10px 14px; border-bottom:1px solid #f0e0a0; }
.mk-tbl tbody tr { border-bottom:1px solid #faf5e4; }
.mk-tbl tbody tr:last-child { border-bottom:none; }
.mk-tbl tbody tr:hover { background:#fffef5; }
.mk-tbl tbody td { padding:11px 14px; vertical-align:middle; }
.mk-chip { display:inline-flex; align-items:center; gap:8px; background:#fff8e1; border:1px solid #f0c040; border-radius:7px; padding:5px 11px; font-family:'Courier New',monospace; font-size:13px; font-weight:700; letter-spacing:2px; color:#7d6000; }
.mk-key-val { transition:filter .2s; filter:blur(5px); user-select:none; }
.mk-key-val.shown { filter:none; user-select:auto; }
.mk-copy-btn { background:none; border:none; cursor:pointer; color:#7d6000; padding:2px 4px; border-radius:4px; font-size:13px; }
.mk-copy-btn:hover { background:#f0e0a0; }
.mk-reveal-btn { background:none; border:none; cursor:pointer; font-size:11px; color:#aaa; margin-top:4px; display:block; padding:0; }
.mk-regen-btn { background:none; border:none; cursor:pointer; color:#e67e22; font-size:12px; font-weight:600; display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:6px; }
.mk-regen-btn:hover { background:#fff4e5; }
.mk-copied-tip { position:fixed; background:#333; color:#fff; padding:4px 10px; border-radius:6px; font-size:12px; pointer-events:none; opacity:0; transition:opacity .2s; z-index:9999; }
.mk-copied-tip.show { opacity:1; }
/* Regen confirm modal */
#mkRegenModal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:500; align-items:center; justify-content:center; }
#mkRegenModal.open { display:flex; }
#mkRegenModal .mk-modal-box { background:#fff; border-radius:10px; width:380px; max-width:95vw; box-shadow:0 16px 48px rgba(0,0,0,.2); overflow:hidden; }
#mkRegenModal .mk-modal-head { padding:16px 20px; border-bottom:1px solid #eee; display:flex; align-items:center; justify-content:space-between; }
#mkRegenModal .mk-modal-head h4 { margin:0; font-size:15px; }
#mkRegenModal .mk-modal-body { padding:24px; text-align:center; }
#mkRegenModal .mk-modal-body i { font-size:40px; color:#e67e22; display:block; margin-bottom:12px; }
#mkRegenModal .mk-modal-body p { font-size:13px; color:#555; margin:0; }
#mkRegenModal .mk-modal-foot { padding:14px 20px; border-top:1px solid #eee; display:flex; justify-content:center; gap:10px; }
</style>

<!-- Admin Dashboard Header -->
<div style="margin-bottom: 20px; padding: 15px 20px; background: linear-gradient(135deg, #D9232E 0%, #B91C24 100%); border-radius: 8px; color: white;">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h2 style="margin: 0; font-size: 24px;"><i class="fas fa-crown"></i> Administrator Dashboard</h2>
            <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 13px;">System Management & Security Control</p>
        </div>
        <div style="text-align: right; font-size: 12px; opacity: 0.9;">
            <div><strong><?php echo date('l, F d, Y'); ?></strong></div>
            <div>User: <?php echo sanitize($_SESSION['full_name']); ?></div>
            <?php if ($isSecurityAdmin): ?>
            <div style="margin-top: 5px; background: rgba(255,255,255,0.2); padding: 3px 8px; border-radius: 3px; display: inline-block;">
                <i class="fas fa-key"></i> Security IT
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Admin Metrics -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-users"></i></div>
        <div class="stat-info">
            <h3><?php echo $totalUsers; ?></h3>
            <span>Active Users</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange"><i class="fas fa-user-tie"></i></div>
        <div class="stat-info">
            <h3><?php echo $adminCount; ?></h3>
            <span>Administrators</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-user-cog"></i></div>
        <div class="stat-info">
            <h3><?php echo $itStaffCount; ?></h3>
            <span>IT Staff</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-user-friends"></i></div>
        <div class="stat-info">
            <h3><?php echo $employeeCount; ?></h3>
            <span>Employees</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-laptop"></i></div>
        <div class="stat-info">
            <h3><?php echo $totalDevices; ?></h3>
            <span>Total Devices</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning"><i class="fas fa-hourglass-end"></i></div>
        <div class="stat-info">
            <h3><?php echo $pendingApprovals; ?></h3>
            <span>Pending Approvals</span>
        </div>
    </div>
</div>

<!-- Admin Quick Actions (Reports button removed) -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-bolt"></i> Admin Controls</h3>
    </div>
    <div class="card-body" style="display: flex; gap: 12px; flex-wrap: wrap;">
        <a href="users.php" class="btn btn-primary" style="flex: 1; min-width: 150px;">
            <i class="fas fa-users-cog"></i> Manage Users
        </a>
        <a href="admin_accounts.php" class="btn btn-warning" style="flex: 1; min-width: 150px;">
            <i class="fas fa-user-check"></i> Account Records
        </a>
        <a href="audit_logs.php" class="btn btn-info" style="flex: 1; min-width: 150px;">
            <i class="fas fa-history"></i> Audit Logs
        </a>
        <?php if ($isSecurityAdmin): ?>
        <a href="security_control.php" class="btn btn-danger" style="flex: 1; min-width: 150px;">
            <i class="fas fa-shield-alt"></i> Security Control
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- ══════════════════════════════════════════════
     SUPER ADMIN — MASTER KEY VAULT
══════════════════════════════════════════════ -->
<?php if (!empty($_GET['mk_msg'])): ?>
<div class="card" style="margin-top:16px; background:#d4edda; border:1px solid #c3e6cb; padding:12px 18px; border-radius:8px; color:#155724; font-size:13px; display:flex; align-items:center; gap:8px;">
    <i class="fas fa-check-circle"></i> <?php echo sanitize($_GET['mk_msg']); ?>
</div>
<?php endif; ?>

<div class="mk-panel">
    <div class="mk-header">
        <div class="mk-header-text">
            <h3><i class="fas fa-key"></i> Super Admin — Master Key Vault</h3>
            <p>Visible to Super Admins only. Use master keys to bypass user passwords for account recovery.</p>
        </div>
        <form method="GET" action="admin_dashboard.php" class="mk-search">
            <input type="text" name="mk_search" value="<?php echo htmlspecialchars($mkSearch); ?>" placeholder="Search name, ID, department…">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
    </div>

    <div class="mk-table-wrap">
        <table class="mk-tbl">
            <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Master Key <i class="fas fa-eye-slash" style="opacity:.4"></i></th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($mkUsers)): ?>
                <tr>
                    <td colspan="7" style="text-align:center; padding:40px; color:#aaa;">
                        <i class="fas fa-key" style="display:block; font-size:28px; margin-bottom:10px;"></i>
                        No users found.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($mkUsers as $mu): ?>
                <tr>
                    <td style="font-size:12px; color:#888;"><?php echo sanitize($mu['employee_id'] ?? '—'); ?></td>
                    <td>
                        <strong><?php echo sanitize($mu['full_name']); ?></strong>
                        <div style="font-size:11px; color:#aaa;"><?php echo sanitize($mu['email']); ?></div>
                    </td>
                    <td><?php echo sanitize($mu['department'] ?? '—'); ?></td>
                    <td><?php echo sanitize(ucfirst(str_replace('_',' ',$mu['role'] ?? '—'))); ?></td>
                    <td>
                        <?php $st = strtolower($mu['status'] ?? 'active'); ?>
                        <span style="display:inline-flex; align-items:center; gap:4px; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:700;
                            background:<?php echo $st==='active' ? '#d4edda' : '#f8d7da'; ?>;
                            color:<?php echo $st==='active' ? '#155724' : '#721c24'; ?>;">
                            <i class="fas fa-circle" style="font-size:6px;"></i>
                            <?php echo strtoupper($st); ?>
                        </span>
                    </td>
                    <td>
                        <?php $mk = $mu['master_key'] ?? '????????'; ?>
                        <div class="mk-chip">
                            <i class="fas fa-lock" style="font-size:11px; opacity:.5;"></i>
                            <span class="mk-key-val" id="mkv-<?php echo $mu['id']; ?>"><?php echo sanitize($mk); ?></span>
                            <button class="mk-copy-btn" onclick="mkCopy('<?php echo sanitize($mk); ?>', event)" title="Copy key">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <button class="mk-reveal-btn" onclick="mkReveal(<?php echo $mu['id']; ?>)" id="mkrbtn-<?php echo $mu['id']; ?>">
                            <i class="fas fa-eye"></i> Reveal
                        </button>
                    </td>
                    <td>
                        <button class="mk-regen-btn" onclick="mkConfirmRegen(<?php echo $mu['id']; ?>, '<?php echo addslashes(sanitize($mu['full_name'])); ?>')">
                            <i class="fas fa-sync-alt"></i> Regenerate
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pending User Approvals (Security Alert) -->
<?php if ($isSecurityAdmin && $pendingApprovals > 0): ?>
<div class="card" style="margin-top: 20px; border-left: 4px solid #E74C3C;">
    <div class="card-header" style="background: #FDEDEC;">
        <h3 style="color: #E74C3C;"><i class="fas fa-exclamation-circle"></i> Pending IT/Admin User Approvals (<?php echo $pendingApprovals; ?>)</h3>
        <a href="security_control.php#approvals" class="btn btn-sm btn-danger">Review All</a>
    </div>
    <div class="card-body">
        <?php if (empty($approvalRequests)): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle" style="color: #27AE60;"></i>
            <h4>All approvals processed</h4>
            <p>No pending user approvals.</p>
        </div>
        <?php else: ?>
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Requested By</th>
                        <th>New User</th>
                        <th>Requested Role</th>
                        <th>Reason</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($approvalRequests, 0, 5) as $req): ?>
                    <tr>
                        <td><?php echo sanitize($req['requested_by_name'] ?? 'Admin'); ?></td>
                        <td><strong><?php echo sanitize($req['full_name']); ?></strong><br><small><?php echo sanitize($req['email']); ?></small></td>
                        <td>
                            <span style="padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; background: <?php echo $req['requested_role'] == 'admin' ? '#D9232E30' : '#3498DB30'; ?>; color: <?php echo $req['requested_role'] == 'admin' ? '#D9232E' : '#3498DB'; ?>;">
                                <?php echo $req['requested_role'] == 'admin' ? 'Administrator' : 'IT Staff'; ?>
                            </span>
                        </td>
                        <td><small><?php echo sanitize(substr($req['reason'] ?? '', 0, 30)); ?></small></td>
                        <td><?php echo formatDate($req['created_at']); ?></td>
                        <td>
                            <form method="POST" action="security_control.php" style="display:inline;">
                                <?php echo csrfInputField(); ?>
                                <input type="hidden" name="action" value="quick_approve">
                                <input type="hidden" name="approval_id" value="<?php echo $req['id']; ?>">
                                <button type="submit" class="action-btn assign" title="Approve" onclick="return confirm('Approve this user creation?')">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                            <form method="POST" action="security_control.php" style="display:inline;">
                                <?php echo csrfInputField(); ?>
                                <input type="hidden" name="action" value="quick_reject">
                                <input type="hidden" name="approval_id" value="<?php echo $req['id']; ?>">
                                <button type="submit" class="action-btn delete" title="Reject" onclick="return confirm('Reject this user creation?')">
                                    <i class="fas fa-times"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Recent Activity Log -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-history"></i> Recent System Activity</h3>
        <a href="audit_logs.php" class="btn btn-sm btn-outline">View All</a>
    </div>
    <div class="card-body">
        <?php if (empty($recentLogs)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h4>No activity yet</h4>
        </div>
        <?php else: ?>
        <?php foreach ($recentLogs as $log): ?>
        <div class="activity-item">
            <div class="activity-icon" style="background: var(--kbmc-red-light); color: var(--kbmc-red);">
                <i class="fas fa-<?php echo strpos($log['action'], 'Create') !== false ? 'plus' : (strpos($log['action'], 'Update') !== false ? 'edit' : (strpos($log['action'], 'Delete') !== false ? 'trash' : 'sign-in-alt')); ?>"></i>
            </div>
            <div class="activity-content">
                <div class="activity-title"><?php echo sanitize($log['action']); ?> - <?php echo sanitize($log['table_name'] ?? 'System'); ?></div>
                <div class="activity-time">By <?php echo sanitize($log['full_name'] ?? 'System'); ?> on <?php echo formatDate($log['created_at'], 'M d, Y h:i A'); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Account Recovery Requests -->
<?php if (!empty($recoveryRequests)): ?>
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-user-shield"></i> Pending Account Recovery Requests</h3>
        <a href="users.php#recovery" class="btn btn-sm btn-outline">View All</a>
    </div>
    <div class="card-body">
        <div class="data-table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Reason</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recoveryRequests as $req): ?>
                    <tr>
                        <td><strong><?php echo sanitize($req['full_name']); ?></strong></td>
                        <td><?php echo sanitize($req['email']); ?></td>
                        <td><?php echo sanitize($req['department'] ?? 'N/A'); ?></td>
                        <td><small><?php echo sanitize(substr($req['request_reason'] ?? '', 0, 40)); ?></small></td>
                        <td><?php echo formatDate($req['requested_at']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ══ Regen Confirm Modal ══ -->
<div id="mkRegenModal">
    <div class="mk-modal-box">
        <div class="mk-modal-head">
            <h4><i class="fas fa-key" style="color:#e67e22;margin-right:6px;"></i> Regenerate Master Key</h4>
            <button onclick="document.getElementById('mkRegenModal').classList.remove('open')" style="background:none;border:none;cursor:pointer;font-size:18px;color:#aaa;">&times;</button>
        </div>
        <div class="mk-modal-body">
            <i class="fas fa-exclamation-triangle"></i>
            <p id="mkRegenMsg">The old master key will be permanently replaced.</p>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="regen_master_key">
            <input type="hidden" name="user_id" id="mkRegenUserId">
            <div class="mk-modal-foot">
                <button type="button" class="btn btn-outline" onclick="document.getElementById('mkRegenModal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-warning"><i class="fas fa-sync-alt"></i> Yes, Regenerate</button>
            </div>
        </form>
    </div>
</div>

<!-- Copy tooltip -->
<div class="mk-copied-tip" id="mkCopiedTip">Copied!</div>

<script>
// Reveal / hide key
const mkRevealed = {};
function mkReveal(id) {
    const span = document.getElementById('mkv-' + id);
    const btn  = document.getElementById('mkrbtn-' + id);
    mkRevealed[id] = !mkRevealed[id];
    if (mkRevealed[id]) {
        span.classList.add('shown');
        btn.innerHTML = '<i class="fas fa-eye-slash"></i> Hide';
    } else {
        span.classList.remove('shown');
        btn.innerHTML = '<i class="fas fa-eye"></i> Reveal';
    }
}

// Copy to clipboard
function mkCopy(key, e) {
    navigator.clipboard.writeText(key).then(() => {
        const t = document.getElementById('mkCopiedTip');
        t.style.left = (e.clientX + 8) + 'px';
        t.style.top  = (e.clientY - 30) + 'px';
        t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 1600);
    });
}

// Confirm regen modal
function mkConfirmRegen(id, name) {
    document.getElementById('mkRegenUserId').value = id;
    document.getElementById('mkRegenMsg').textContent =
        'Regenerate the master key for "' + name + '"? The old key will be permanently replaced and cannot be recovered.';
    document.getElementById('mkRegenModal').classList.add('open');
}

// Close modal on backdrop click
document.getElementById('mkRegenModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
});
</script>

<?php require_once 'includes/footer.php'; ?>