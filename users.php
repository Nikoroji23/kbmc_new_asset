<?php
/**
 * KBMC Asset Management - Manage Users
 * Includes: User Management + Recovery Requests tabs
 */
$pageTitle = 'Manage Users';
require_once 'includes/header.php';
requireITStaff();
$canManageUsers = hasRole('admin');

if (isset($_GET['view_user']) && isAjaxRequest()) {
    $uid = (int) $_GET['view_user'];
    $user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user->execute([$uid]);
    $user = $user->fetch(PDO::FETCH_ASSOC);
    $assets = getAssignedAssets($uid);

    if ($user) {
        unset($user['password'], $user['failed_logins'], $user['locked_until']);
    }

    header('Content-Type: application/json');
    echo json_encode(['user' => $user, 'assets' => $assets]);
    exit();
}

// Get all users and pending recovery requests
$users = $pdo->query("SELECT id, employee_id, full_name, email, role, department, position, phone, status, created_at FROM users ORDER BY created_at DESC")->fetchAll();
$recoveryRequests = getPendingRecoveryRequests();
?>

<div class="page-header">
    <h1><i class="fas fa-users-cog"></i> Manage Users</h1>
    <?php if ($canManageUsers): ?>
    <button class="btn btn-primary" data-modal="addUserModal"><i class="fas fa-plus"></i> Add User</button>
    <?php else: ?>
    <span style="display:inline-flex;align-items:center;margin-left:20px;background:#3498db;color:#ffffff;padding:8px 12px;border-radius:999px;font-size:14px;font-weight:600;">IT Staff View Only</span>
    <?php endif; ?>
</div>

<?php if (!$canManageUsers): ?>
<div style="margin-bottom:18px;padding:14px 18px;background:#ecf6ff;border:1px solid #b3d8ff;border-radius:8px;color:#225b9d;">
    <strong>IT Staff</strong> can inspect employee accounts and assigned assets. Administrative actions such as add, activate/deactivate, delete, and recovery approval are reserved for admin only.
</div>
<?php endif; ?>

<!-- Tabs Navigation -->
<div class="tabs">
    <button class="tab-btn active" onclick="switchTab('users-tab', this)">
        <i class="fas fa-users"></i> All Users
    </button>
    <button class="tab-btn" onclick="switchTab('recovery-tab', this)">
        <i class="fas fa-user-shield"></i> Recovery Requests
        <?php if (count($recoveryRequests) > 0): ?>
        <span class="nav-badge" style="margin-left: 8px;"><?php echo count($recoveryRequests); ?></span>
        <?php endif; ?>
    </button>
</div>

<!-- Users Tab -->
<div id="users-tab" class="tab-content active">
    <div class="card">
        <div class="card-body">
            <div class="data-table-wrapper">
                <table class="data-table">
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
                            <td>
                                <span class="status-badge" style="background: <?php echo $u['status'] == 'active' ? '#27AE6020' : '#E74C3C20'; ?>; color: <?php echo $u['status'] == 'active' ? '#27AE60' : '#E74C3C'; ?>; border: 1px solid <?php echo $u['status'] == 'active' ? '#27AE60' : '#E74C3C'; ?>;">
                                    <?php echo ucfirst($u['status']); ?>
                                </span>
                            </td>
                            <td><?php echo formatDate($u['created_at']); ?></td>
                            <td style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                                <button class="action-btn view view-user-btn"
                                        data-id="<?php echo $u['id']; ?>"
                                        title="View User & Assets">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if (!$canManageUsers): ?>
                                <span style="display:inline-flex;align-items:center;margin-left:6px;padding:4px 9px;background:#3498db;color:#fff;border-radius:12px;font-size:12px;font-weight:600;">View only</span>
                                <?php endif; ?>
                                <?php if ($canManageUsers): ?>
                                <form method="POST" action="user_actions.php" style="display:inline-block;margin:0;">
                                    <?php echo csrfInputField(); ?>
                                    <input type="hidden" name="action" value="toggle_user">
                                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="btn btn-sm <?php echo $u['status'] == 'active' ? 'btn-danger' : 'btn-success'; ?>"
                                        onclick="return confirm('<?php echo $u['status'] == 'active' ? 'Deactivate' : 'Activate'; ?> this user?')">
                                        <?php echo $u['status'] == 'active' ? '<i class="fas fa-ban"></i> Deactivate' : '<i class="fas fa-check"></i> Activate'; ?>
                                    </button>
                                </form>
                                <form method="POST" action="user_actions.php" style="display:inline-block;margin:0;">
                                    <?php echo csrfInputField(); ?>
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"
                                        onclick="return confirm('Are you sure you want to permanently delete <?php echo sanitize($u['full_name']); ?>? This cannot be undone.')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
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
</div>

<!-- Recovery Requests Tab -->
<div id="recovery-tab" class="tab-content">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-shield"></i> Account Recovery Requests</h3>
        </div>
        <div class="card-body">
            <?php if (empty($recoveryRequests)): ?>
            <div class="empty-state">
                <i class="fas fa-check-circle" style="font-size: 40px; color: #27AE60;"></i>
                <h4>No pending recovery requests</h4>
                <p>All accounts are active or recovery requests have been processed.</p>
            </div>
            <?php else: ?>
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Reason</th>
                            <th>Requested</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recoveryRequests as $req): ?>
                        <tr>
                            <td><strong>#<?php echo $req['id']; ?></strong></td>
                            <td><?php echo sanitize($req['full_name']); ?></td>
                            <td><?php echo sanitize($req['email']); ?></td>
                            <td><?php echo sanitize($req['department'] ?: 'N/A'); ?></td>
                            <td><?php echo sanitize(substr($req['request_reason'], 0, 50)) . (strlen($req['request_reason']) > 50 ? '...' : ''); ?></td>
                            <td><?php echo formatDate($req['requested_at'], 'M d, Y h:i A'); ?></td>
                            <td>
                                <span class="status-badge" style="background: #F39C1220; color: #F39C12; border: 1px solid #F39C12;">
                                    Pending
                                </span>
                            </td>
                            <td>
                                <div class="action-btns">
                                    <?php if ($canManageUsers): ?>
                                    <form method="POST" action="user_actions.php" style="display:inline-block;margin:0;">
                                        <?php echo csrfInputField(); ?>
                                        <input type="hidden" name="action" value="process_recovery">
                                        <input type="hidden" name="recovery_id" value="<?php echo $req['id']; ?>">
                                        <input type="hidden" name="approval_action" value="approve">
                                        <button type="submit" class="action-btn assign" title="Approve Recovery"
                                            onclick="return confirm('Approve account recovery for <?php echo sanitize($req['full_name']); ?>? This will reactivate their account.')">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="user_actions.php" style="display:inline-block;margin:0;">
                                        <?php echo csrfInputField(); ?>
                                        <input type="hidden" name="action" value="process_recovery">
                                        <input type="hidden" name="recovery_id" value="<?php echo $req['id']; ?>">
                                        <input type="hidden" name="approval_action" value="reject">
                                        <button type="submit" class="action-btn delete" title="Reject Recovery"
                                            onclick="return confirm('Reject account recovery for <?php echo sanitize($req['full_name']); ?>?')">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
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
        <form method="POST" action="user_actions.php" id="addUserForm">
            <?php echo csrfInputField(); ?>
            <input type="hidden" name="action" value="add_user">
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
                        <div id="phoneWrapper" class="phone-picker">
                            <button type="button" id="flagBtn" class="phone-picker-btn" onclick="togglePhonePicker()">
                                <span id="flagDisplay">🇵🇭</span>
                                <span id="codeDisplay">+63</span>
                                <span id="chevron">▼</span>
                            </button>
                            <div id="phoneDropdown" class="phone-dropdown">
                                <div class="phone-dropdown-search">
                                    <input type="text" id="countrySearch" placeholder="Search..." oninput="filterPhoneCountries()" class="form-control">
                                </div>
                                <div id="countryListItems"></div>
                            </div>
                            <input type="tel" name="phone" id="phoneNumberInput" class="form-control phone-picker-input"
                                placeholder="9XX XXX XXXX" maxlength="11"
                                oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,window._phoneMaxLen||11)">
                        </div>
                        <input type="hidden" name="phone_full" id="phoneFullInput">
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

<!-- View User Modal -->
<div id="viewUserModal" class="modal-overlay">
    <div class="modal-box" style="max-width: 700px;">
        <div class="modal-header">
            <h3><i class="fas fa-user-circle"></i> User Details</h3>
            <button class="modal-close" onclick="closeViewUserModal()">&times;</button>
        </div>
        <div class="modal-body" id="viewUserBody">
            <p style="text-align:center;color:#999;padding:30px;">Loading...</p>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
// Tab Switching Function
function switchTab(tabId, btn) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    // Remove active from all buttons
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('active');
    });
    // Show selected tab
    document.getElementById(tabId).classList.add('active');
    // Activate button
    btn.classList.add('active');

    // Update URL hash for direct linking
    if (tabId === 'recovery-tab') {
        window.location.hash = 'recovery';
    } else {
        history.pushState('', document.title, window.location.pathname + window.location.search);
    }
}

// Check URL hash on page load
window.addEventListener('DOMContentLoaded', function() {
    if (window.location.hash === '#recovery') {
        const recoveryBtn = document.querySelectorAll('.tab-btn')[1];
        if (recoveryBtn) {
            switchTab('recovery-tab', recoveryBtn);
        }
    }
});

// Phone picker scripts
const phoneCountries = [
    {flag: '🇵🇭', name: 'Philippines', code: '+63', maxLen: 10, placeholder: '9XX XXX XXXX'},
    {flag: '🇺🇸', name: 'United States', code: '+1', maxLen: 10, placeholder: 'XXX XXX XXXX'},
    {flag: '🇬🇧', name: 'United Kingdom', code: '+44', maxLen: 10, placeholder: 'XXXX XXX XXXX'},
    {flag: '🇦🇺', name: 'Australia', code: '+61', maxLen: 9, placeholder: 'XXX XXX XXX'},
    {flag: '🇯🇵', name: 'Japan', code: '+81', maxLen: 10, placeholder: 'XX XXXX XXXX'},
    {flag: '🇸🇬', name: 'Singapore', code: '+65', maxLen: 8, placeholder: 'XXXX XXXX'},
    {flag: '🇰🇷', name: 'South Korea', code: '+82', maxLen: 10, placeholder: 'XX XXXX XXXX'},
    {flag: '🇦🇪', name: 'UAE', code: '+971', maxLen: 9, placeholder: 'XX XXX XXXX'},
];
let selectedCountry = phoneCountries[0];
window._phoneMaxLen = selectedCountry.maxLen;

function renderPhoneList(list) {
    const items = list.map((country, idx) => {
        return `<div class="phone-country-item" data-index="${idx}">
            <span>${country.flag}</span>
            <span>${country.name}</span>
            <span>${country.code}</span>
        </div>`;
    }).join('');

    document.getElementById('countryListItems').innerHTML = items;
}

function togglePhonePicker() {
    const dropdown = document.getElementById('phoneDropdown');
    dropdown.classList.toggle('show');
    if (dropdown.classList.contains('show')) {
        document.getElementById('countrySearch').focus();
    }
}

function selectPhoneCountry(idx) {
    const country = phoneCountries[idx];
    if (!country) {
        return;
    }

    selectedCountry = country;
    window._phoneMaxLen = selectedCountry.maxLen;
    document.getElementById('flagDisplay').textContent = selectedCountry.flag;
    document.getElementById('codeDisplay').textContent = selectedCountry.code;
    document.getElementById('phoneNumberInput').maxLength = selectedCountry.maxLen;
    document.getElementById('phoneNumberInput').placeholder = selectedCountry.placeholder;
    document.getElementById('phoneNumberInput').value = '';
    document.getElementById('phoneDropdown').classList.remove('show');
    document.getElementById('phoneNumberInput').focus();
}

function filterPhoneCountries() {
    const query = document.getElementById('countrySearch').value.toLowerCase();
    renderPhoneList(phoneCountries.filter(c => c.name.toLowerCase().includes(query) || c.code.includes(query)));
}

renderPhoneList(phoneCountries);

document.addEventListener('click', function (event) {
    const wrapper = document.getElementById('phoneWrapper');
    const dropdown = document.getElementById('phoneDropdown');
    if (wrapper && dropdown && !wrapper.contains(event.target)) {
        dropdown.classList.remove('show');
    }
});

document.getElementById('countryListItems').addEventListener('click', function (event) {
    const item = event.target.closest('.phone-country-item');
    if (item) {
        selectPhoneCountry(parseInt(item.dataset.index, 10));
    }
});

document.getElementById('addUserForm').addEventListener('submit', function () {
    const num = document.getElementById('phoneNumberInput').value;
    document.getElementById('phoneFullInput').value = selectedCountry.code + num;
});

// ── View User & Assigned Assets ──────────────────────────────
document.querySelectorAll('.view-user-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var userId = this.dataset.id;
        var modal  = document.getElementById('viewUserModal');
        var body   = document.getElementById('viewUserBody');

        body.innerHTML = '<p style="text-align:center;color:#999;padding:30px;"><i class="fas fa-spinner fa-spin"></i> Loading...</p>';
        modal.classList.add('show');

        fetch('users.php?view_user=' + userId, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            var u      = data.user;
            var assets = data.assets;

            var assetsHtml = assets.length === 0
                ? '<p style="color:#999;text-align:center;padding:20px 0;"><i class="fas fa-box-open" style="font-size:28px;display:block;margin-bottom:8px;"></i>No assets currently assigned to this user.</p>'
                : '<div class="data-table-wrapper"><table class="data-table"><thead><tr>'
                    + '<th>Asset Tag</th><th>Name</th><th>Category</th><th>Status</th><th>Assigned At</th>'
                    + '</tr></thead><tbody>'
                    + assets.map(function(a) {
                        return '<tr>'
                            + '<td><strong>' + (a.asset_tag || 'N/A') + '</strong></td>'
                            + '<td>' + (a.name || 'N/A') + '</td>'
                            + '<td>' + (a.category || 'N/A') + '</td>'
                            + '<td><span class="status-badge" style="background:#27AE6020;color:#27AE60;border:1px solid #27AE60;">' + (a.status || 'N/A') + '</span></td>'
                            + '<td>' + (a.assigned_at || 'N/A') + '</td>'
                            + '</tr>';
                    }).join('')
                    + '</tbody></table></div>';

            body.innerHTML =
                '<div class="form-grid" style="margin-bottom:22px;">'
                    + '<div><span style="font-size:11px;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Employee ID</span><p style="margin-top:5px;font-weight:600;">'  + (u.employee_id  || 'N/A') + '</p></div>'
                    + '<div><span style="font-size:11px;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Full Name</span><p style="margin-top:5px;font-weight:600;">'    + (u.full_name    || 'N/A') + '</p></div>'
                    + '<div><span style="font-size:11px;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Email</span><p style="margin-top:5px;">'                       + (u.email        || 'N/A') + '</p></div>'
                    + '<div><span style="font-size:11px;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Phone</span><p style="margin-top:5px;">'                       + (u.phone        || 'N/A') + '</p></div>'
                    + '<div><span style="font-size:11px;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Department</span><p style="margin-top:5px;">'                  + (u.department   || 'N/A') + '</p></div>'
                    + '<div><span style="font-size:11px;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Position</span><p style="margin-top:5px;">'                    + (u.position     || 'N/A') + '</p></div>'
                    + '<div><span style="font-size:11px;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Role</span><p style="margin-top:5px;">'                        + (u.role         || 'N/A') + '</p></div>'
                    + '<div><span style="font-size:11px;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Status</span><p style="margin-top:5px;">'
                        + '<span class="status-badge" style="background:' + (u.status === 'active' ? '#27AE6020' : '#E74C3C20') + ';color:' + (u.status === 'active' ? '#27AE60' : '#E74C3C') + ';border:1px solid ' + (u.status === 'active' ? '#27AE60' : '#E74C3C') + ';">' + (u.status ? u.status.charAt(0).toUpperCase() + u.status.slice(1) : 'N/A') + '</span>'
                    + '</p></div>'
                    + '<div><span style="font-size:11px;color:#888;font-weight:700;text-transform:uppercase;letter-spacing:.5px;">Date Joined</span><p style="margin-top:5px;">'                 + (u.created_at   || 'N/A') + '</p></div>'
                + '</div>'
                + '<hr style="border:none;border-top:1px solid #eee;margin-bottom:18px;">'
                + '<h4 style="font-size:14px;color:#2c3e50;margin-bottom:14px;"><i class="fas fa-laptop"></i> Assigned Assets</h4>'
                + assetsHtml;
        })
        .catch(function() {
            body.innerHTML = '<p style="color:#e74c3c;text-align:center;padding:30px;"><i class="fas fa-exclamation-circle"></i> Failed to load user data. Please try again.</p>';
        });
    });
});

function closeViewUserModal() {
    document.getElementById('viewUserModal').classList.remove('show');
}

// Close when clicking the overlay background
document.getElementById('viewUserModal').addEventListener('click', function(e) {
    if (e.target === this) closeViewUserModal();
});
</script>
<style>
.phone-picker {
    display: flex;
    align-items: center;
    position: relative;
    border: 1px solid #ccc;
    border-radius: 5px;
    overflow: hidden;
}
.phone-picker-btn {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 0 10px;
    height: 38px;
    border: none;
    border-right: 1px solid #ccc;
    background: #f5f5f5;
    cursor: pointer;
    white-space: nowrap;
    font-size: 14px;
}
.phone-picker-input {
    border: none;
    outline: none;
    flex: 1;
    padding: 0 10px;
    height: 38px;
    font-size: 14px;
}
.phone-dropdown {
    display: none;
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    z-index: 9999;
    width: 240px;
    max-height: 220px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #ccc;
    border-radius: 5px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
}
.phone-dropdown.show {
    display: block;
}
.phone-dropdown-search {
    padding: 8px;
}
.phone-country-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    cursor: pointer;
    font-size: 14px;
}
.phone-country-item span:last-child {
    margin-left: auto;
    color: #888;
    font-size: 12px;
}
.phone-country-item:hover {
    background: #f5f5f5;
}
</style>