<?php
session_start();
require_once 'config/database.php';
require_once 'config/auth.php';

requireAdmin();

$pdo = getDBConnection();

// --- Filters & Sorting ---
$search      = trim($_GET['search']      ?? '');
$dept_filter = trim($_GET['department']  ?? '');
$name_sort   = $_GET['name_sort']        ?? '';   // 'asc' | 'desc'

// Build WHERE
$where  = "WHERE 1=1";
$params = [];

if ($search !== '') {
    $where   .= " AND (u.name LIKE :search OR u.email LIKE :search OR u.employee_id LIKE :search OR u.department LIKE :search)";
    $params[':search'] = "%$search%";
}
if ($dept_filter !== '') {
    $where   .= " AND u.department = :dept";
    $params[':dept'] = $dept_filter;
}

// ORDER BY
$order = "ORDER BY u.id DESC";
if ($name_sort === 'asc')  $order = "ORDER BY u.name ASC";
if ($name_sort === 'desc') $order = "ORDER BY u.name DESC";

// Fetch distinct departments for dropdown
$deptStmt = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department ASC");
$departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch users
$stmt = $pdo->prepare("SELECT u.*, u.employee_id AS emp_id FROM users u $where $order");
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle add/deactivate/delete actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user') {
        // Insert new user — generate master key automatically
        $masterKey = strtoupper(bin2hex(random_bytes(4))); // e.g. A3F2C1D8
        $hashedPw  = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $ins = $pdo->prepare("INSERT INTO users (employee_id, name, email, role, department, position, password, master_key, status, created_at)
                               VALUES (:eid, :name, :email, :role, :dept, :pos, :pw, :mk, 'active', NOW())");
        $ins->execute([
            ':eid'   => $_POST['employee_id'],
            ':name'  => $_POST['name'],
            ':email' => $_POST['email'],
            ':role'  => $_POST['role'],
            ':dept'  => $_POST['department'],
            ':pos'   => $_POST['position'],
            ':pw'    => $hashedPw,
            ':mk'    => $masterKey,
        ]);
        header("Location: users.php?success=User+added+successfully");
        exit;
    }

    if ($action === 'deactivate') {
        $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = :id")->execute([':id' => $_POST['user_id']]);
        header("Location: users.php?success=User+deactivated");
        exit;
    }

    if ($action === 'activate') {
        $pdo->prepare("UPDATE users SET status = 'active' WHERE id = :id")->execute([':id' => $_POST['user_id']]);
        header("Location: users.php?success=User+activated");
        exit;
    }

    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $_POST['user_id']]);
        header("Location: users.php?success=User+deleted");
        exit;
    }
}

// Recovery requests count
$recoveryCount = $pdo->query("SELECT COUNT(*) FROM recovery_requests WHERE status = 'pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users – KBMC Asset</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ── Reset & Base ── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', sans-serif; background: #f4f6fb; color: #333; display: flex; min-height: 100vh; }

        /* ── Sidebar ── */
        .sidebar {
            width: 255px; min-height: 100vh; background: #c0392b; color: #fff;
            display: flex; flex-direction: column; position: fixed; top: 0; left: 0; z-index: 100;
        }
        .sidebar-brand { display: flex; align-items: center; gap: 10px; padding: 20px 18px; border-bottom: 1px solid rgba(255,255,255,.15); }
        .sidebar-brand .logo-box { background:#fff; border-radius:6px; padding:4px 7px; font-weight:800; color:#c0392b; font-size:13px; line-height:1.2; }
        .sidebar-brand .brand-name { font-size:12px; line-height:1.4; font-weight:600; }
        .sidebar-user { display:flex; align-items:center; gap:10px; padding:14px 18px; border-bottom:1px solid rgba(255,255,255,.15); }
        .sidebar-user .avatar { width:38px; height:38px; border-radius:50%; background:rgba(255,255,255,.25); display:flex; align-items:center; justify-content:center; font-size:16px; }
        .sidebar-user .uname { font-size:13px; font-weight:600; }
        .sidebar-user .urole { font-size:11px; opacity:.75; }
        .sidebar nav { flex:1; padding:10px 0; }
        .sidebar .nav-section { font-size:10px; text-transform:uppercase; letter-spacing:.8px; opacity:.6; padding:14px 18px 4px; }
        .sidebar a { display:flex; align-items:center; gap:10px; padding:10px 18px; color:rgba(255,255,255,.88); text-decoration:none; font-size:13px; transition:.15s; }
        .sidebar a:hover, .sidebar a.active { background:rgba(0,0,0,.18); color:#fff; }
        .sidebar a i { width:16px; text-align:center; }

        /* ── Main ── */
        .main { margin-left:255px; flex:1; display:flex; flex-direction:column; min-height:100vh; }
        .topbar { background:#fff; border-bottom:1px solid #e5e9f0; padding:0 28px; height:56px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:50; }
        .topbar-title { font-size:15px; font-weight:600; }
        .topbar-bell { position:relative; }
        .topbar-bell .badge { position:absolute; top:-4px; right:-6px; background:#c0392b; color:#fff; border-radius:50%; font-size:10px; width:17px; height:17px; display:flex; align-items:center; justify-content:center; }
        .content { padding:28px; flex:1; }

        /* ── Page Header ── */
        .page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:22px; }
        .page-header h1 { font-size:22px; font-weight:700; display:flex; align-items:center; gap:10px; }
        .btn { display:inline-flex; align-items:center; gap:7px; padding:9px 18px; border-radius:7px; border:none; cursor:pointer; font-size:13px; font-weight:600; text-decoration:none; transition:.15s; }
        .btn-primary { background:#c0392b; color:#fff; }
        .btn-primary:hover { background:#a93226; }
        .btn-outline { background:#fff; color:#555; border:1px solid #d5d9e0; }
        .btn-outline:hover { background:#f4f6fb; }
        .btn-sm { padding:6px 12px; font-size:12px; }
        .btn-danger  { background:#c0392b; color:#fff; }
        .btn-danger:hover { background:#a93226; }
        .btn-warning { background:#e67e22; color:#fff; }
        .btn-warning:hover { background:#d35400; }
        .btn-success { background:#27ae60; color:#fff; }
        .btn-success:hover { background:#1e8449; }
        .btn-icon { background:none; border:none; cursor:pointer; color:#555; font-size:15px; padding:5px 7px; border-radius:5px; transition:.15s; }
        .btn-icon:hover { background:#f0f0f0; color:#c0392b; }

        /* ── Alert ── */
        .alert { padding:11px 16px; border-radius:7px; margin-bottom:18px; font-size:13px; display:flex; align-items:center; gap:8px; }
        .alert-success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }

        /* ── Filter Bar ── */
        .filter-bar {
            background:#fff; border:1px solid #e5e9f0; border-radius:10px;
            padding:16px 20px; margin-bottom:20px;
            display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;
        }
        .filter-bar .fg { display:flex; flex-direction:column; gap:5px; flex:1; min-width:180px; }
        .filter-bar label { font-size:11px; font-weight:600; color:#888; text-transform:uppercase; letter-spacing:.5px; }
        .filter-bar input, .filter-bar select {
            padding:9px 12px; border:1px solid #d5d9e0; border-radius:7px; font-size:13px;
            background:#fff; color:#333; outline:none; transition:.15s; width:100%;
        }
        .filter-bar input:focus, .filter-bar select:focus { border-color:#c0392b; box-shadow:0 0 0 3px rgba(192,57,43,.1); }
        .filter-bar .fg-actions { display:flex; gap:8px; align-items:flex-end; }

        /* ── Tabs ── */
        .tabs { display:flex; gap:4px; margin-bottom:18px; border-bottom:2px solid #e5e9f0; }
        .tab { padding:10px 18px; font-size:13px; font-weight:600; color:#888; border:none; background:none; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-2px; display:flex; align-items:center; gap:7px; transition:.15s; }
        .tab.active { color:#c0392b; border-bottom-color:#c0392b; }
        .tab .tab-badge { background:#c0392b; color:#fff; border-radius:50%; font-size:10px; width:18px; height:18px; display:flex; align-items:center; justify-content:center; }

        /* ── Table Card ── */
        .card { background:#fff; border-radius:12px; border:1px solid #e5e9f0; overflow:hidden; }
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        thead th { background:#f8f9fc; color:#555; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; padding:12px 16px; border-bottom:1px solid #e5e9f0; white-space:nowrap; }
        tbody tr { border-bottom:1px solid #f0f2f8; transition:.12s; }
        tbody tr:last-child { border-bottom:none; }
        tbody tr:hover { background:#fafbff; }
        tbody td { padding:13px 16px; vertical-align:middle; }
        .user-name { font-weight:700; color:#222; }
        .user-email { font-size:12px; color:#888; }
        .badge-status { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700; }
        .badge-active   { background:#d4edda; color:#155724; }
        .badge-inactive { background:#f8d7da; color:#721c24; }
        .action-btns { display:flex; gap:6px; flex-wrap:wrap; }

        /* ── Empty State ── */
        .empty-state { text-align:center; padding:60px 20px; color:#aaa; }
        .empty-state i { font-size:40px; margin-bottom:14px; display:block; }
        .empty-state p { font-size:14px; }

        /* ── Modal ── */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:200; align-items:center; justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal { background:#fff; border-radius:12px; width:520px; max-width:95vw; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.2); }
        .modal-header { padding:20px 24px 16px; border-bottom:1px solid #e5e9f0; display:flex; align-items:center; justify-content:space-between; }
        .modal-header h3 { font-size:16px; font-weight:700; }
        .modal-body { padding:24px; display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .modal-body .full { grid-column:1/-1; }
        .form-group { display:flex; flex-direction:column; gap:6px; }
        .form-group label { font-size:12px; font-weight:600; color:#666; }
        .form-group input, .form-group select {
            padding:9px 12px; border:1px solid #d5d9e0; border-radius:7px; font-size:13px; outline:none; transition:.15s;
        }
        .form-group input:focus, .form-group select:focus { border-color:#c0392b; box-shadow:0 0 0 3px rgba(192,57,43,.1); }
        .modal-footer { padding:16px 24px; border-top:1px solid #e5e9f0; display:flex; justify-content:flex-end; gap:10px; }

        /* ── Confirm Modal ── */
        .confirm-body { padding:24px; text-align:center; }
        .confirm-body i { font-size:44px; color:#c0392b; margin-bottom:14px; display:block; }
        .confirm-body h4 { font-size:16px; font-weight:700; margin-bottom:8px; }
        .confirm-body p { font-size:13px; color:#666; }
        .confirm-footer { padding:16px 24px; border-top:1px solid #e5e9f0; display:flex; justify-content:center; gap:10px; }

        /* ── Sort indicator ── */
        .sort-indicator { font-size:10px; margin-left:4px; color:#c0392b; }
    </style>
</head>
<body>

<!-- ═══════════════════════ SIDEBAR ═══════════════════════ -->
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="logo-box">KB<br>MC</div>
        <div class="brand-name">Kitchen Beauty<br>Marketing Corp.</div>
    </div>
    <div class="sidebar-user">
        <div class="avatar"><i class="fa fa-user"></i></div>
        <div>
            <div class="uname"><?= htmlspecialchars($_SESSION['user_name'] ?? 'System Administrator') ?></div>
            <div class="urole"><?= htmlspecialchars($_SESSION['user_role'] ?? 'Administrator') ?></div>
        </div>
    </div>
    <nav>
        <a href="admin_dashboard.php"><i class="fa fa-gauge-high"></i> Admin Dashboard</a>
        <div class="nav-section">Tools &amp; Search</div>
        <a href="search_devices.php"><i class="fa fa-magnifying-glass"></i> Search Devices</a>
        <a href="my_devices.php"><i class="fa fa-laptop"></i> My Devices</a>
        <div class="nav-section">Administration</div>
        <a href="users.php" class="active"><i class="fa fa-users"></i> Manage Users</a>
        <a href="recovery_requests.php"><i class="fa fa-rotate-left"></i> Recovery Requests
            <?php if ($recoveryCount > 0): ?>
                <span class="tab-badge" style="margin-left:auto"><?= $recoveryCount ?></span>
            <?php endif; ?>
        </a>
        <div class="nav-section">Account</div>
        <a href="my_profile.php"><i class="fa fa-id-card"></i> My Profile</a>
        <a href="logout.php"><i class="fa fa-right-from-bracket"></i> Logout</a>
    </nav>
</aside>

<!-- ═══════════════════════ MAIN ═══════════════════════ -->
<div class="main">
    <div class="topbar">
        <div class="topbar-title"><i class="fa fa-bars" style="cursor:pointer;margin-right:10px;color:#888"></i> Manage Users</div>
        <div class="topbar-bell">
            <i class="fa fa-bell" style="font-size:18px;color:#555"></i>
            <span class="badge">29</span>
        </div>
    </div>

    <div class="content">
        <div class="page-header">
            <h1><i class="fa fa-users" style="color:#c0392b"></i> Manage Users</h1>
            <button class="btn btn-primary" onclick="openModal('addUserModal')">
                <i class="fa fa-plus"></i> Add User
            </button>
        </div>

        <?php if (!empty($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fa fa-circle-check"></i> <?= htmlspecialchars($_GET['success']) ?>
        </div>
        <?php endif; ?>

        <!-- ── Filter Bar ── -->
        <form method="GET" action="users.php" id="filterForm">
            <div class="filter-bar">
                <div class="fg" style="flex:2">
                    <label><i class="fa fa-magnifying-glass"></i> Search</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, email, employee ID, department…">
                </div>

                <div class="fg">
                    <label><i class="fa fa-building"></i> Department</label>
                    <select name="department" onchange="this.form.submit()">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>" <?= $dept_filter === $d ? 'selected' : '' ?>>
                                <?= htmlspecialchars($d) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="fg">
                    <label><i class="fa fa-arrow-down-a-z"></i> Sort by Name</label>
                    <select name="name_sort" onchange="this.form.submit()">
                        <option value=""   <?= $name_sort === ''     ? 'selected' : '' ?>>Default</option>
                        <option value="asc"  <?= $name_sort === 'asc'  ? 'selected' : '' ?>>A → Z</option>
                        <option value="desc" <?= $name_sort === 'desc' ? 'selected' : '' ?>>Z → A</option>
                    </select>
                </div>

                <div class="fg-actions">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-magnifying-glass"></i> Search</button>
                    <a href="users.php" class="btn btn-outline btn-sm"><i class="fa fa-rotate-right"></i> Reset</a>
                </div>
            </div>
        </form>

        <!-- ── Tabs ── -->
        <div class="tabs">
            <button class="tab active" onclick="switchTab('all')">
                <i class="fa fa-users"></i> All Users
                <span style="background:#e5e9f0;color:#555;border-radius:20px;padding:2px 8px;font-size:11px;margin-left:4px"><?= count($users) ?></span>
            </button>
            <a href="recovery_requests.php" class="tab" style="text-decoration:none">
                <i class="fa fa-rotate-left"></i> Recovery Requests
                <?php if ($recoveryCount > 0): ?>
                    <span class="tab-badge"><?= $recoveryCount ?></span>
                <?php endif; ?>
            </a>
        </div>

        <!-- ── Results info ── -->
        <?php if ($search || $dept_filter || $name_sort): ?>
        <p style="font-size:12px;color:#888;margin-bottom:12px">
            <i class="fa fa-filter"></i>
            Showing <?= count($users) ?> result<?= count($users) !== 1 ? 's' : '' ?>
            <?= $dept_filter ? "in <strong>$dept_filter</strong>" : '' ?>
            <?= $search ? "matching <strong>\"" . htmlspecialchars($search) . "\"</strong>" : '' ?>
            <?= $name_sort ? "· sorted <strong>" . ($name_sort === 'asc' ? 'A→Z' : 'Z→A') . "</strong>" : '' ?>
            — <a href="users.php" style="color:#c0392b">Clear filters</a>
        </p>
        <?php endif; ?>

        <!-- ── Table ── -->
        <div class="card">
            <div class="table-wrap">
                <?php if (empty($users)): ?>
                <div class="empty-state">
                    <i class="fa fa-users-slash"></i>
                    <p>No users found matching your filters.</p>
                </div>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td style="color:#888;font-size:12px"><?= htmlspecialchars($u['employee_id'] ?? $u['id']) ?></td>
                            <td>
                                <div class="user-name"><?= htmlspecialchars($u['name']) ?></div>
                                <div class="user-email"><?= htmlspecialchars($u['email']) ?></div>
                            </td>
                            <td style="color:#555"><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= htmlspecialchars($u['role'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($u['department'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($u['position'] ?? '—') ?></td>
                            <td>
                                <?php $st = strtolower($u['status'] ?? 'active'); ?>
                                <span class="badge-status badge-<?= $st ?>">
                                    <i class="fa fa-circle" style="font-size:7px"></i>
                                    <?= strtoupper($st) ?>
                                </span>
                            </td>
                            <td style="color:#888;font-size:12px;white-space:nowrap">
                                <?= isset($u['created_at']) ? date('M d, Y', strtotime($u['created_at'])) : '—' ?>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-icon" title="View" onclick="viewUser(<?= htmlspecialchars(json_encode($u)) ?>)">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                    <?php if ($st === 'active'): ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="action" value="deactivate">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-warning">
                                            <i class="fa fa-ban"></i> Deactivate
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="action" value="activate">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success">
                                            <i class="fa fa-check"></i> Activate
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')">
                                        <i class="fa fa-trash"></i> Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div><!-- /content -->
</div><!-- /main -->

<!-- ═══════════════════════ ADD USER MODAL ═══════════════════════ -->
<div class="modal-overlay" id="addUserModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fa fa-user-plus" style="color:#c0392b"></i> Add New User</h3>
            <button class="btn-icon" onclick="closeModal('addUserModal')"><i class="fa fa-xmark"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_user">
            <div class="modal-body">
                <div class="form-group">
                    <label>Employee ID</label>
                    <input type="text" name="employee_id" required placeholder="e.g. KBM-IT-00999">
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" required placeholder="Full name">
                </div>
                <div class="form-group full">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="user@kbmc.com">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" required>
                        <option value="">Select role…</option>
                        <option value="Employee">Employee</option>
                        <option value="IT Staff">IT Staff</option>
                        <option value="Administrator">Administrator</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" name="department" required placeholder="e.g. QC/TECHNICAL">
                </div>
                <div class="form-group">
                    <label>Position</label>
                    <input type="text" name="position" placeholder="e.g. IT Employee">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="Set initial password">
                </div>
                <div class="form-group" style="background:#fff8f0;padding:12px;border-radius:8px;border:1px dashed #e67e22;grid-column:1/-1">
                    <label style="color:#e67e22"><i class="fa fa-key"></i> Master Key</label>
                    <p style="font-size:12px;color:#888;margin-top:4px">A unique master key will be <strong>auto-generated</strong> for this user and will be visible to Super Admin on the dashboard.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addUserModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-user-plus"></i> Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════════════════════ VIEW USER MODAL ═══════════════════════ -->
<div class="modal-overlay" id="viewUserModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fa fa-id-card" style="color:#c0392b"></i> User Details</h3>
            <button class="btn-icon" onclick="closeModal('viewUserModal')"><i class="fa fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="viewUserBody" style="grid-template-columns:1fr 1fr">
            <!-- filled by JS -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeModal('viewUserModal')">Close</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════ DELETE CONFIRM MODAL ═══════════════════════ -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal" style="max-width:400px">
        <div class="confirm-body">
            <i class="fa fa-triangle-exclamation"></i>
            <h4>Delete User?</h4>
            <p id="deleteMsg">This action cannot be undone.</p>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="user_id" id="deleteUserId">
            <div class="confirm-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="btn btn-danger"><i class="fa fa-trash"></i> Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// Close modal on backdrop click
document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if (e.target === o) o.classList.remove('open'); });
});

function confirmDelete(id, name) {
    document.getElementById('deleteUserId').value = id;
    document.getElementById('deleteMsg').textContent = `Are you sure you want to delete "${name}"? This cannot be undone.`;
    openModal('deleteModal');
}

function viewUser(u) {
    const b = document.getElementById('viewUserBody');
    const row = (label, val) => `
        <div class="form-group">
            <label>${label}</label>
            <div style="padding:9px 12px;background:#f8f9fc;border-radius:7px;font-size:13px">${val || '—'}</div>
        </div>`;
    b.innerHTML =
        row('Employee ID', u.employee_id || u.id) +
        row('Full Name', u.name) +
        row('Email', u.email) +
        row('Role', u.role) +
        row('Department', u.department) +
        row('Position', u.position) +
        row('Status', u.status) +
        row('Joined', u.created_at);
    openModal('viewUserModal');
}

function switchTab(t) { /* handled by page navigation */ }
</script>
</body>
</html>