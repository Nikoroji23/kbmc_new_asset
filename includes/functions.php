<?php
/**
 * KBMC Asset Management - Helper Functions
 */

require_once __DIR__ . '/config.php';

// Load email config safely - check if file exists first
$emailConfigPath = __DIR__ . '/email_config.php';
if (file_exists($emailConfigPath)) {
    require_once $emailConfigPath;
} else {
    // Fallback email functions if email_config.php is missing
    $email_settings = [
        'from_email' => 'noreply@kbmc.com',
        'from_name'  => 'KBMC Asset Management',
    ];
    function sendEmail($to, $subject, $body, $html = true) {
        return ['success' => false, 'message' => 'Email system not configured. Please set up includes/email_config.php'];
    }
    function isEmailConfigured() { return false; }
    function emailTemplate($title, $content, $buttonText = '', $buttonUrl = '') {
        return "<html><body><h2>{$title}</h2><div>{$content}</div></body></html>";
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireAdmin() {
    requireLogin();
    if (!hasRole('admin')) {
        header('Location: dashboard.php');
        exit();
    }
}

function requireITStaff() {
    requireLogin();
    if (!hasRole('admin') && !hasRole('it_staff')) {
        header('Location: dashboard.php');
        exit();
    }
}

function getUserInfo($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function getUnreadNotificationCount($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

function getNotifications($userId, $limit = 5) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

function addNotification($userId, $type, $title, $message, $relatedId = null) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, related_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $type, $title, $message, $relatedId]);
    return $pdo->lastInsertId();
}

function logAudit($userId, $action, $tableName = null, $recordId = null, $oldValues = null, $newValues = null) {
    global $pdo;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $tableName, $recordId, $oldValues, $newValues, $ipAddress]);
}

function getStatusBadge($status) {
    global $status_colors;
    $color = $status_colors[$status] ?? '#6C757D';
    $label = str_replace('_', ' ', ucwords($status));
    return '<span class="status-badge" style="background-color: ' . $color . '20; color: ' . $color . '; border: 1px solid ' . $color . ';">' . $label . '</span>';
}

function getDeviceCountByStatus($status) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE status = ?");
    $stmt->execute([$status]);
    return $stmt->fetchColumn();
}

function getTotalDeviceCount() {
    global $pdo;
    return $pdo->query("SELECT COUNT(*) FROM devices")->fetchColumn();
}

function getActiveAssignmentCount() {
    global $pdo;
    return $pdo->query("SELECT COUNT(*) FROM device_assignments WHERE status = 'active'")->fetchColumn();
}

function getLowStockTypes() {
    global $pdo;
    $stmt = $pdo->query("SELECT dt.type_name, COUNT(d.id) as count FROM device_types dt LEFT JOIN devices d ON dt.id = d.device_type_id AND d.status = 'in_stock' GROUP BY dt.id HAVING count <= 2");
    return $stmt->fetchAll();
}

function formatDate($date, $format = 'M d, Y') {
    if (!$date) return 'N/A';
    return date($format, strtotime($date));
}

function generateAssetTag($deviceTypeId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT type_name FROM device_types WHERE id = ?");
    $stmt->execute([$deviceTypeId]);
    $type = $stmt->fetch();
    $prefix = 'KBMC-' . strtoupper(substr($type['type_name'], 0, 3)) . '-';
    $stmt = $pdo->query("SELECT COUNT(*) FROM devices");
    $count = $stmt->fetchColumn() + 1;
    return $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
}

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function setFlashMessage($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function downloadCSV($filename, $headers, $data) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// ============================================================
// REMEMBER ME FUNCTIONS
// ============================================================

function generateRememberToken() {
    return bin2hex(random_bytes(32));
}

function setRememberMe($userId) {
    global $pdo;
    $token = generateRememberToken();
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
    $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?")
        ->execute([$token, $userId]);
    setcookie('remember_token', $token, [
        'expires'  => time() + 30 * 24 * 60 * 60,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function clearRememberMe() {
    global $pdo;
    if (isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $pdo->prepare("UPDATE users SET remember_token = NULL WHERE remember_token = ?")
            ->execute([$token]);
        setcookie('remember_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}

function checkRememberMe() {
    global $pdo;
    if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];
        $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token = ? AND status = 'active'");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        if ($user) {
            $newToken = generateRememberToken();
            $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?")
                ->execute([$newToken, $user['id']]);
            setcookie('remember_token', $newToken, [
                'expires'  => time() + 30 * 24 * 60 * 60,
                'path'     => '/',
                'secure'   => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            return true;
        }
    }
    return false;
}

// ============================================================
// ACCOUNT LOCKOUT / FAILED LOGIN
// ============================================================

function recordFailedLogin($email) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET failed_logins = failed_logins + 1 WHERE email = ?");
    $stmt->execute([$email]);
    $stmt = $pdo->prepare("SELECT failed_logins FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $count = $stmt->fetchColumn();
    if ($count >= 5) {
        $lockTime = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        $pdo->prepare("UPDATE users SET locked_until = ? WHERE email = ?")
            ->execute([$lockTime, $email]);
    }
}

function isAccountLocked($email) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT locked_until FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $lockedUntil = $stmt->fetchColumn();
    if ($lockedUntil && strtotime($lockedUntil) > time()) {
        return $lockedUntil;
    }
    return false;
}

function resetFailedLogins($userId) {
    global $pdo;
    $pdo->prepare("UPDATE users SET failed_logins = 0, locked_until = NULL WHERE id = ?")
        ->execute([$userId]);
}

// ============================================================
// ACCOUNT RECOVERY
// ============================================================

function submitAccountRecovery($userId, $reason) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO account_recovery_requests (user_id, request_reason) VALUES (?, ?)");
    $stmt->execute([$userId, $reason]);
    $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
    foreach ($admins as $admin) {
        addNotification($admin['id'], 'audit_reminder', 'Account Recovery Request', 
            'A user has submitted an account recovery request.', $pdo->lastInsertId());
    }
    return $pdo->lastInsertId();
}

function getPendingRecoveryRequests() {
    global $pdo;
    return $pdo->query("
        SELECT ar.*, u.full_name, u.email, u.employee_id, u.department, u.position
        FROM account_recovery_requests ar
        JOIN users u ON ar.user_id = u.id
        WHERE ar.status = 'pending'
        ORDER BY ar.requested_at DESC
    ")->fetchAll();
}