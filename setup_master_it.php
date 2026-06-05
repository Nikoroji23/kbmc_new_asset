<?php
/**
 * KBMC Asset Management - Master IT User Setup
 * Admin-only page to initialize the master IT user
 */
$pageTitle = 'Master IT Setup';
require_once 'includes/header.php';

// Check admin access
requireAdmin();

// Default values
$masterEmail = 'alfonsoaninias0527@gmail.com';
$masterSecret = 'laehcimosnoflaeicalgellageiokin';
$setupStatus = '';
$statusMessage = '';

// Handle setup request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $secret = trim($_POST['secret'] ?? '');

    if (!$email || !preg_match('/^[a-zA-Z0-9._\-+()[\]@]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $email) || !$secret || strlen($secret) < 8) {
        $setupStatus = 'error';
        $statusMessage = 'Please provide a valid email and a secret of at least 8 characters.';
    } else {
        // Check if user with this email exists and is IT staff
        $stmt = $pdo->prepare("SELECT id, full_name, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $setupStatus = 'error';
            $statusMessage = "User with email '{$email}' not found. Please create an IT staff user first.";
        } elseif ($user['role'] !== 'it_staff') {
            $setupStatus = 'error';
            $statusMessage = "User '{$user['full_name']}' is not an IT staff member. Only IT staff can be master IT administrators.";
        } else {
            // Initialize the master IT user
            $result = initializeMasterITUser($email, $secret);
            if ($result) {
                $setupStatus = 'success';
                $statusMessage = "✓ Master IT user has been successfully configured: {$user['full_name']} ({$email})";
                logAudit($_SESSION['user_id'], 'Master IT User Initialized', 'users', $result, null, json_encode(['email' => $email]), 'System Setup');
            } else {
                $setupStatus = 'error';
                $statusMessage = "User '{$email}' does not exist or is not IT staff. Could not set as master IT.";
            }
        }
    }
}

// Get current master IT user if configured
$masterItUser = null;
$masterStmt = $pdo->query("
    SELECT id, full_name, email FROM users 
    WHERE role = 'it_staff' 
    AND master_key_hash IS NOT NULL
    AND email = 'alfonsoaninias0527@gmail.com'
    LIMIT 1
");
$masterItUser = $masterStmt->fetch();
?>

<style>
.master-it-container {
    max-width: 900px;
    margin: 0 auto;
}

.setup-hero {
    background: linear-gradient(135deg, #16a085 0%, #138d75 100%);
    color: white;
    padding: 40px 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    text-align: center;
    box-shadow: 0 4px 15px rgba(22, 160, 133, 0.3);
}

.setup-hero h1 {
    margin: 0 0 10px 0;
    font-size: 32px;
}

.setup-hero p {
    margin: 0;
    opacity: 0.95;
    font-size: 15px;
}

.setup-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    margin-bottom: 30px;
}

.setup-form-section {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.setup-info-section {
    background: #f8f9fa;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.setup-form-section h2,
.setup-info-section h2 {
    margin: 0 0 20px 0;
    font-size: 20px;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #333;
    font-size: 14px;
}

.form-group input {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-group input:focus {
    border-color: #16a085;
    box-shadow: 0 0 0 3px rgba(22, 160, 133, 0.1);
    outline: none;
}

.form-group small {
    display: block;
    color: #666;
    margin-top: 6px;
    font-size: 12px;
}

.form-actions {
    display: flex;
    gap: 10px;
    margin-top: 25px;
}

.form-actions button,
.form-actions a {
    flex: 1;
}

.status-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.status-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.info-card {
    background: white;
    border-left: 4px solid #16a085;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 15px;
}

.info-card h4 {
    margin: 0 0 8px 0;
    color: #16a085;
    font-size: 14px;
}

.info-card p {
    margin: 0;
    color: #555;
    font-size: 13px;
    line-height: 1.6;
}

.step-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.step-list li {
    display: flex;
    gap: 12px;
    margin-bottom: 12px;
    padding: 10px;
    background: white;
    border-radius: 6px;
    font-size: 13px;
}

.step-list li:before {
    content: attr(data-step);
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    width: 24px;
    height: 24px;
    background: #16a085;
    color: white;
    border-radius: 50%;
    font-weight: bold;
    font-size: 12px;
}

.current-config {
    background: #e8f5e9;
    border-left: 4px solid #4caf50;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.current-config h3 {
    margin: 0 0 15px 0;
    color: #2e7d32;
    display: flex;
    align-items: center;
    gap: 10px;
}

.config-item {
    background: white;
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.config-item strong {
    color: #333;
    min-width: 150px;
}

.config-item span {
    color: #16a085;
    font-weight: 600;
    word-break: break-all;
}

@media (max-width: 768px) {
    .setup-section {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="master-it-container">
    <!-- Hero Section -->
    <div class="setup-hero">
        <h1><i class="fas fa-crown"></i> Master IT Administrator Setup</h1>
        <p>Configure the master IT user who will validate and approve new IT staff accounts</p>
    </div>

    <!-- Status Messages -->
    <?php if ($setupStatus === 'success'): ?>
    <div class="status-success">
        <i class="fas fa-check-circle" style="font-size: 18px;"></i>
        <div>
            <strong>Configuration Successful!</strong>
            <p style="margin: 5px 0 0 0;"><?php echo htmlspecialchars($statusMessage); ?></p>
        </div>
    </div>
    <?php elseif ($setupStatus === 'error'): ?>
    <div class="status-error">
        <i class="fas fa-exclamation-circle" style="font-size: 18px;"></i>
        <div>
            <strong>Error</strong>
            <p style="margin: 5px 0 0 0;"><?php echo htmlspecialchars($statusMessage); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Current Configuration (if already set) -->
    <?php if ($masterItUser): ?>
    <div class="current-config">
        <h3><i class="fas fa-check-circle"></i> Current Master IT Administrator</h3>
        <div class="config-item">
            <strong>Name:</strong>
            <span><?php echo htmlspecialchars($masterItUser['full_name']); ?></span>
        </div>
        <div class="config-item">
            <strong>Email:</strong>
            <span><?php echo htmlspecialchars($masterItUser['email']); ?></span>
        </div>
        <div class="config-item">
            <strong>Status:</strong>
            <span><i class="fas fa-shield-alt"></i> Active</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Setup Section -->
    <div class="setup-section">
        <!-- Left: Configuration Form -->
        <div class="setup-form-section">
            <h2><i class="fas fa-cogs"></i> Configure Settings</h2>
            
            <form method="POST">
                <div class="form-group">
                    <label for="email">
                        <i class="fas fa-envelope"></i> Master IT User Email
                        <span style="color: red;">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="email" 
                        name="email" 
                        class="form-control" 
                        value="<?php echo htmlspecialchars($masterEmail); ?>"
                        placeholder="alfonsoaninias0527@gmail.com"
                        required>
                    <small>
                        Must be an existing IT staff user in the system. This email receives special approval privileges.
                    </small>
                </div>

                <div class="form-group">
                    <label for="secret">
                        <i class="fas fa-key"></i> Master Key Secret
                        <span style="color: red;">*</span>
                    </label>
                    <input 
                        type="password" 
                        id="secret" 
                        name="secret" 
                        class="form-control" 
                        value="<?php echo htmlspecialchars($masterSecret); ?>"
                        placeholder="Enter a secure secret (min 8 characters)"
                        minlength="8"
                        required>
                    <small>
                        Minimum 8 characters. Will be securely hashed using bcrypt. This secret is used to validate IT user approvals.
                    </small>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 10px;">
                    <label style="display: flex; align-items: center; gap: 8px; margin: 0; cursor: pointer; font-weight: normal;">
                        <input type="checkbox" id="show-secret" onchange="toggleSecretVisibility()">
                        <span style="font-size: 13px;">Show secret</span>
                    </label>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-success" style="font-size: 16px; padding: 12px 20px;">
                        <i class="fas fa-save"></i> Save Configuration
                    </button>
                    <a href="it_dashboard.php" class="btn btn-outline" style="font-size: 16px; padding: 12px 20px;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>

        <!-- Right: Information & Guide -->
        <div class="setup-info-section">
            <h2><i class="fas fa-info-circle"></i> What is This?</h2>
            
            <div class="info-card">
                <h4><i class="fas fa-question-circle"></i> Master IT Administrator Role</h4>
                <p>
                    The Master IT Administrator is the designated IT staff member responsible for validating and approving new IT user accounts. When an admin creates an IT staff user, they cannot access the system until the Master IT Administrator approves them.
                </p>
            </div>

            <h2 style="margin-top: 25px;"><i class="fas fa-tasks"></i> Approval Workflow</h2>
            
            <ol class="step-list" style="counter-reset: step-counter;">
                <li data-step="1"><strong>Admin Creates IT User:</strong> Creates a new IT staff account (starts as INACTIVE)</li>
                <li data-step="2"><strong>Master IT Notified:</strong> Receives notification about pending approval</li>
                <li data-step="3"><strong>Master IT Reviews:</strong> Goes to "Approve IT Users" page</li>
                <li data-step="4"><strong>Master IT Validates:</strong> Enters master key secret to authenticate</li>
                <li data-step="5"><strong>User Activated:</strong> IT user becomes ACTIVE and can login</li>
                <li data-step="6"><strong>Audit Logged:</strong> All actions recorded in IT Audit Log</li>
                <li data-step="7"><strong>Email Sent:</strong> IT user receives approval notification</li>
            </ol>

            <h2 style="margin-top: 25px;"><i class="fas fa-lock"></i> Security</h2>
            
            <div class="info-card" style="border-left-color: #e74c3c;">
                <h4 style="color: #e74c3c;"><i class="fas fa-shield-alt"></i> Important Security Notes</h4>
                <p>
                    ✓ Secret is hashed with bcrypt (never stored in plain text)<br>
                    ✓ Only one Master IT at a time<br>
                    ✓ All approvals require secret validation<br>
                    ✓ Complete audit trail of all actions<br>
                    ✓ Email notifications logged
                </p>
            </div>
        </div>
    </div>
</div>

<script>
function toggleSecretVisibility() {
    const secretInput = document.getElementById('secret');
    const checkbox = document.getElementById('show-secret');
    
    if (checkbox.checked) {
        secretInput.type = 'text';
    } else {
        secretInput.type = 'password';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
