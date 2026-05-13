<?php
/**
 * KBMC Asset Management - Account Recovery
 * For deactivated accounts or locked accounts
 */
require_once 'includes/functions.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $reason = trim($_POST['reason'] ?? '');

    if (empty($email) || empty($reason)) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $pdo->prepare("SELECT id, full_name, email, status FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Check if there's already a pending request
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM account_recovery_requests WHERE user_id = ? AND status = 'pending'");
            $stmt->execute([$user['id']]);
            $pending = $stmt->fetchColumn();

            if ($pending > 0) {
                $error = 'You already have a pending recovery request. Please wait for an administrator to review it.';
            } else {
                submitAccountRecovery($user['id'], $reason);

                $message = 'Your account recovery request has been submitted successfully. An administrator will review it shortly.<br><br>
                <strong>Account:</strong> ' . sanitize($user['full_name']) . '<br>
                <strong>Status:</strong> ' . ucfirst($user['status']) . '<br>
                <strong>Request ID:</strong> #' . $pdo->lastInsertId();
            }
        } else {
            $error = 'No account found with this email address.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Recovery - KBMC Asset Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .recovery-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #f5f6fa 0%, #fde8e9 100%); padding: 20px; }
        .recovery-box { background: white; border-radius: 12px; box-shadow: 0 5px 25px rgba(0,0,0,0.15); width: 100%; max-width: 500px; padding: 40px; text-align: center; }
        .recovery-logo { width: 60px; height: 60px; margin: 0 auto 20px; }
        .recovery-logo img { width: 100%; height: 100%; object-fit: contain; }
        .recovery-box h3 { font-size: 22px; color: #2c3e50; margin-bottom: 8px; }
        .recovery-box p { font-size: 14px; color: #888; margin-bottom: 25px; }
        .recovery-info { background: #ebf5fb; border-left: 4px solid #3498db; padding: 15px; text-align: left; margin-bottom: 20px; border-radius: 0 8px 8px 0; }
        .recovery-info h4 { font-size: 13px; color: #2c3e50; margin: 0 0 8px; }
        .recovery-info ul { margin: 0; padding-left: 18px; font-size: 12px; color: #555; }
        .recovery-info li { margin-bottom: 4px; }
        .recovery-form .form-group { text-align: left; margin-bottom: 18px; }
        .recovery-form .form-group label { display: block; font-size: 13px; font-weight: 600; color: #2c3e50; margin-bottom: 8px; }
        .recovery-form .btn { width: 100%; justify-content: center; padding: 13px; font-size: 15px; }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: #666; text-decoration: none; font-size: 14px; margin-top: 20px; transition: color 0.3s; }
        .back-link:hover { color: var(--kbmc-red); }
    </style>
</head>
<body>
    <div class="recovery-container">
        <div class="recovery-box">
            <div class="recovery-logo">
                <img src="assets/images/logo.png" alt="KBMC Logo">
            </div>
            <h3><i class="fas fa-user-shield"></i> Account Recovery</h3>
            <p>Request reactivation of your deactivated or locked account.</p>

            <div class="recovery-info">
                <h4><i class="fas fa-info-circle"></i> When to use this:</h4>
                <ul>
                    <li>Your account was deactivated by an administrator</li>
                    <li>Your account is locked due to too many failed login attempts</li>
                    <li>You believe your account was disabled by mistake</li>
                </ul>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom: 20px; text-align: left;">
                <i class="fas fa-times-circle"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <?php if ($message): ?>
            <div class="alert alert-success" style="margin-bottom: 20px; text-align: left;">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
            <?php else: ?>
            <form method="POST" class="recovery-form">
                <div class="form-group">
                    <label for="email">Registered Email Address</label>
                    <input type="email" name="email" id="email" class="form-control" 
                           placeholder="Enter your registered email" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="reason">Reason for Recovery</label>
                    <textarea name="reason" id="reason" class="form-control" 
                              placeholder="Explain why you need your account reactivated..." required
                              rows="4"><?php echo isset($_POST['reason']) ? htmlspecialchars($_POST['reason']) : ''; ?></textarea>
                </div>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-paper-plane"></i> Submit Recovery Request
                </button>
            </form>
            <?php endif; ?>

            <div style="margin-top: 15px;">
                <a href="forgot_password.php" style="color: #666; text-decoration: none; font-size: 13px; margin-right: 20px;">
                    <i class="fas fa-key"></i> Forgot password?
                </a>
                <a href="login.php" class="back-link" style="margin-top: 0;">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
    </div>
</body>
</html>
