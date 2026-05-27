<?php
/**
 * KBMC Asset Management - Set Master Key for Security IT Approver
 * Admin-only helper: sets a master key (hashed) and grants Security IT privileges to an IT staff user.
 * IMPORTANT: Remove this file after use.
 */

$pageTitle = 'Set Master Key';
require_once 'includes/functions.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($csrf)) {
        setFlashMessage('error', 'Invalid request token.');
        header('Location: set_master_key.php');
        exit();
    }

    $target = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
    $key = trim($_POST['master_key'] ?? '');

    if ($target <= 0 || $key === '') {
        setFlashMessage('error', 'Please select a user and provide a master key.');
        header('Location: set_master_key.php');
        exit();
    }

    // Set the master key (this hashes the key and marks user as security admin)
    $ok = setMasterKey($target, $key);
    if ($ok) {
        // Ensure the user is marked as Security IT approver
        setSecurityITApprover($target, true);
        logAudit($_SESSION['user_id'], 'Set Master Key & Grant Security IT', 'users', $target, null, 'grant_security_it');
        setFlashMessage('success', 'Master key set and Security IT privileges granted. Remember to securely communicate the master key to the approver and delete this helper file.');
    } else {
        setFlashMessage('error', 'Failed to set master key. Confirm the target is an IT staff user.');
    }

    header('Location: set_master_key.php');
    exit();
}

// Load IT staff list
$itStaff = $pdo->query("SELECT id, employee_id, full_name, email FROM users WHERE role = 'it_staff' ORDER BY full_name")->fetchAll();
require_once 'includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-key"></i> Set Master Key for Security IT</h1>
    <p style="color:#666;margin-top:6px;">This page hashes the provided master key and grants Security IT approval to the selected IT staff. Delete this file after use.</p>
</div>

<div class="card">
    <div class="card-body">
        <?php $flash = getFlashMessage(); if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?>">
            <?php echo sanitize($flash['message']); ?>
            <button class="alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
        <?php endif; ?>

        <form method="POST" style="max-width:720px;">
            <?php echo csrfInputField(); ?>
            <div class="form-group">
                <label>Select IT Staff</label>
                <select name="user_id" class="form-control" required>
                    <option value="">-- Select IT staff --</option>
                    <?php foreach ($itStaff as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo sanitize($s['full_name'] . ' (' . ($s['employee_id'] ?: $s['email']) . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Master Key (32 characters)</label>
                <input type="text" name="master_key" class="form-control" maxlength="64" placeholder="Enter generated master key here" required>
                <small style="color:#999;">Generate a secure 32-character key locally (do not reuse). Keep it secret.</small>
            </div>
            <div style="display:flex;gap:8px;">
                <button type="submit" class="btn btn-success">Set Master Key & Grant Security IT</button>
                <a href="admin_dashboard.php" class="btn btn-light">Back</a>
            </div>
        </form>

        <hr style="margin:18px 0;">
        <h4>Generate a secure key (example)</h4>
        <p style="color:#666;">On the server or your admin machine (XAMPP), run:</p>
        <pre style="background:#f6f6f6;padding:10px;border-radius:6px;">C:\xampp\php\php.exe -r "echo bin2hex(random_bytes(16)).PHP_EOL;"</pre>
        <p style="color:#666;">Then copy the generated key into the Master Key field above and submit. After success, delete this helper:</p>
        <pre style="background:#fff8e6;padding:10px;border-radius:6px;color:#8a6d3b;">rm set_master_key.php</pre>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
