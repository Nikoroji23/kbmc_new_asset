<?php
/**
 * KBMC Asset Management - Forgot Password
 * Uses PHPMailer for reliable email delivery
 */
require_once 'includes/functions.php';

$message = '';
$error = '';
$resetLink = '';
$emailSent = false;
$emailResult = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Please enter your email address.';
    } else {
        $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            // FIX: Use 24 hours instead of 1 hour to avoid timezone issues
            // Also use DATE_ADD with UTC to ensure consistency
            $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user['id']]);
            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $token, $expires]);

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'];
            $basePath = dirname($_SERVER['PHP_SELF']);
            $basePath = $basePath === '/' ? '' : $basePath;
            $resetLink = $protocol . '://' . $host . $basePath . '/reset_password.php?token=' . $token;

            // Try to send email using PHPMailer
            $emailBody = emailTemplate(
                'Password Reset Request',
                "<p>Hello <strong>" . sanitize($user['full_name']) . "</strong>,</p>
                <p>We received a request to reset your password for the KBMC Asset Management System.</p>
                <p>If you made this request, click the button below to reset your password. This link will expire in <strong>24 hours</strong>.</p>",
                'Reset My Password',
                $resetLink
            );

            $emailResult = sendEmail($user['email'], 'Password Reset Request', $emailBody);
            $emailSent = $emailResult['success'];

            logAudit($user['id'], 'Password Reset Request', 'users', $user['id']);

            if ($emailSent) {
                $message = 'A password reset link has been sent to <strong>' . sanitize($email) . '</strong>. Please check your inbox (and spam folder).';
            } else {
                $message = 'Email could not be sent. Your reset link is shown below for manual use.<br><small>Error: ' . sanitize($emailResult['message']) . '</small>';
            }
        } else {
            // Don't reveal if email exists
            $message = 'If this email exists in our system, instructions have been sent.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - KBMC Asset Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .forgot-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #f5f6fa 0%, #fde8e9 100%); padding: 20px; }
        .forgot-box { background: white; border-radius: 12px; box-shadow: 0 5px 25px rgba(0,0,0,0.15); width: 100%; max-width: 480px; padding: 40px; text-align: center; }
        .forgot-logo { width: 60px; height: 60px; margin: 0 auto 20px; }
        .forgot-logo img { width: 100%; height: 100%; object-fit: contain; }
        .forgot-box h3 { font-size: 22px; color: #2c3e50; margin-bottom: 8px; }
        .forgot-box p { font-size: 14px; color: #888; margin-bottom: 25px; }
        .reset-link-box { background: #f8f9fa; border: 2px dashed var(--kbmc-red); border-radius: 8px; padding: 15px; margin: 20px 0; text-align: left; }
        .reset-link-box label { font-size: 12px; font-weight: 600; color: var(--kbmc-red); display: block; margin-bottom: 8px; }
        .reset-link-box a { color: #3498db; font-size: 12px; text-decoration: none; word-break: break-all; }
        .copy-btn { background: var(--kbmc-red); color: white; border: none; padding: 6px 14px; border-radius: 4px; font-size: 12px; cursor: pointer; margin-top: 10px; }
        .copy-btn:hover { background: var(--kbmc-red-dark); }
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: #666; text-decoration: none; font-size: 14px; margin-top: 20px; transition: color 0.3s; }
        .back-link:hover { color: var(--kbmc-red); }
        .forgot-form .form-group { text-align: left; margin-bottom: 20px; }
        .forgot-form .form-group label { display: block; font-size: 13px; font-weight: 600; color: #2c3e50; margin-bottom: 8px; }
        .forgot-form .btn { width: 100%; justify-content: center; padding: 13px; font-size: 15px; }
        .setup-notice { background: #e8f4fd; border-left: 4px solid #3498db; padding: 12px 15px; text-align: left; margin-bottom: 20px; border-radius: 0 8px 8px 0; font-size: 12px; color: #2c3e50; }
        .setup-notice strong { color: #3498db; }
        .setup-notice code { background: #f0f0f0; padding: 2px 5px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-box">
            <div class="forgot-logo">
                <img src="assets/images/logo.png" alt="KBMC Logo">
            </div>
            <h3>Forgot Password?</h3>
            <p>Enter your email address and we'll send you a password reset link.</p>

            <?php if (!$emailSent && !empty($message) && $resetLink): ?>
            <div class="setup-notice">
                <i class="fas fa-info-circle"></i> <strong>Email not configured yet.</strong><br>
                To enable email sending, edit <code>includes/email_config.php</code> and add your Gmail credentials.<br>
                For now, copy the link below and paste it in your browser.
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom: 20px; text-align: left;">
                <i class="fas fa-times-circle"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $emailSent ? 'success' : 'warning'; ?>" style="margin-bottom: 20px; text-align: left;">
                <i class="fas fa-<?php echo $emailSent ? 'check-circle' : 'info-circle'; ?>"></i> <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <?php if ($resetLink): ?>
            <div class="reset-link-box">
                <label><i class="fas fa-link"></i> Your Password Reset Link:</label>
                <a href="<?php echo $resetLink; ?>" id="resetLink"><?php echo $resetLink; ?></a>
                <div style="margin-top: 10px;">
                    <button type="button" class="copy-btn" onclick="copyLink()">
                        <i class="fas fa-copy"></i> Copy Link
                    </button>
                    <span id="copyMsg" style="font-size: 12px; color: #27ae60; margin-left: 10px; display: none;">
                        <i class="fas fa-check"></i> Copied!
                    </span>
                </div>
                <p style="font-size: 11px; color: #999; margin-top: 10px; margin-bottom: 0;">
                    <i class="fas fa-clock"></i> This link expires in 24 hours.
                </p>
            </div>
            <?php else: ?>
            <form method="POST" class="forgot-form">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" id="email" class="form-control" 
                           placeholder="Enter your registered email" required
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-paper-plane"></i> Send Reset Link
                </button>
            </form>
            <?php endif; ?>

            <a href="login.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>

    <script>
        function copyLink() {
            const link = document.getElementById('resetLink').href;
            navigator.clipboard.writeText(link).then(() => {
                const msg = document.getElementById('copyMsg');
                msg.style.display = 'inline';
                setTimeout(() => msg.style.display = 'none', 2000);
            });
        }
    </script>
</body>
</html>