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

function requireITStaffOnly() {
    requireLogin();
    if (!hasRole('it_staff')) {
        header('Location: dashboard.php');
        exit();
    }
}

// Validate a role is one of the allowed values
function isValidRole($role) {
    $allowed_roles = ['admin', 'it_staff', 'employee'];
    return in_array($role, $allowed_roles);
}

// Get all valid roles
function getAllowedRoles() {
    return ['admin', 'it_staff', 'employee'];
}

//$pdo -  database access abstraction layer that provides a consistent and secure way
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

function filterUniqueEmails(array $recipients) {
    $seen = [];
    $unique = [];
    foreach ($recipients as $recipient) {
        $email = filter_var($recipient['email'] ?? '', FILTER_VALIDATE_EMAIL);
        if (!$email || isset($seen[$email])) {
            continue;
        }
        $seen[$email] = true;
        $recipient['email'] = $email;
        $unique[] = $recipient;
    }
    return $unique;
}

function tableExists($table) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return $stmt->fetchColumn() !== false;
    } catch (PDOException $e) {
        return false;
    }
}

function columnExists($table, $column) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return $stmt->fetchColumn() !== false;
    } catch (PDOException $e) {
        return false;
    }
}

function ensureDeviceSchema() {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    if (!tableExists('devices')) {
        return;
    }

    try {
        if (!columnExists('devices', 'pc_name')) {
            $GLOBALS['pdo']->exec("ALTER TABLE devices ADD COLUMN pc_name VARCHAR(100) DEFAULT NULL AFTER ip_address");
        }
        if (!columnExists('devices', 'ip_address')) {
            $GLOBALS['pdo']->exec("ALTER TABLE devices ADD COLUMN ip_address VARCHAR(50) DEFAULT NULL AFTER serial_number");
        }
    } catch (PDOException $e) {
        // If ALTER TABLE fails, allow the app to continue; device listing may still fail.
    }
}

function addNotification($userId, $type, $title, $message, $relatedId = null) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, related_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $type, $title, $message, $relatedId]);
    return $pdo->lastInsertId();
}

function addNotificationIfNotExists($userId, $type, $title, $message, $relatedId = null) {
    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT id FROM notifications WHERE user_id = ? AND type = ? AND title = ? AND message = ? " .
        "AND ((related_id = ? ) OR (related_id IS NULL AND ? IS NULL)) LIMIT 1"
    );
    $stmt->execute([$userId, $type, $title, $message, $relatedId, $relatedId]);
    $existingId = $stmt->fetchColumn();
    if ($existingId) {
        return $existingId;
    }
    return addNotification($userId, $type, $title, $message, $relatedId);
}

function notifyITStaff($type, $title, $message, $relatedId = null) {
    global $pdo;
    $stmt = $pdo->query("SELECT id FROM users WHERE role IN ('admin','it_staff') AND status = 'active'");
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($staff as $member) {
        addNotificationIfNotExists($member['id'], $type, $title, $message, $relatedId);
    }
}

function getNotificationUrl(array $notification) {
    $type = $notification['type'] ?? '';
    $relatedId = !empty($notification['related_id']) ? intval($notification['related_id']) : null;
    $role = $_SESSION['role'] ?? '';

    switch ($type) {
        case 'device_request':
            return 'requests.php' . ($relatedId ? '?id=' . $relatedId : '');
        case 'request_approved':
        case 'request_rejected':
            return 'requests.php' . ($relatedId ? '?id=' . $relatedId : '');
        case 'repair_needed':
        case 'repair_completed':
            return 'repairs.php' . ($relatedId ? '?repair_id=' . $relatedId : '');
        case 'device_deployed':
        case 'device_returned':
        case 'warranty_expiring':
            return $relatedId ? 'view_device.php?id=' . $relatedId : 'devices.php';
        case 'low_stock':
            return 'devices.php';
        case 'maintenance_assigned':
        case 'maintenance_due':
            return $relatedId ? 'maintenance_reminders.php?device_id=' . $relatedId : 'maintenance_reminders.php';
        case 'user_creation_request':
            return 'security_control.php' . ($relatedId ? '#request-' . $relatedId : '');
        case 'user_creation_approved':
        case 'user_creation_rejected':
            return 'notifications.php';
        case 'user_clearance_required':
            return 'it_clearance.php';
        case 'user_clearance_completed':
            if ($role === 'employee') {
                return $relatedId ? 'view_device.php?id=' . $relatedId : 'user_asset_dashboard.php';
            }
            return 'it_clearance.php';
        case 'voluntary_return_requested':
            if (in_array($role, ['admin', 'it_staff'], true)) {
                return 'it_clearance.php';
            }
            return $relatedId ? 'view_device.php?id=' . $relatedId : 'user_asset_dashboard.php';
        case 'lifespan_monitor':
        case 'lifespan_replace_soon':
        case 'lifespan_overdue':
        case 'lifespan_replaced':
        case 'lifespan_extended':
            // Employees see device details view, IT staff see full lifespan dashboard
            if ($role === 'employee') {
                return $relatedId ? 'view_device.php?id=' . $relatedId : 'devices.php';
            }
            return $relatedId ? 'device_lifespan.php?device_id=' . $relatedId : 'device_lifespan.php';
        case 'audit_reminder':
            return 'users.php#recovery';
        default:
            return 'notifications.php';
    }
}

function createPasswordResetToken($userId) {
    global $pdo;
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$userId]);
    $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $token, $expires]);
    return $token;
}

function getPasswordResetLink($token) {
    if (defined('BASE_URL') && BASE_URL !== '') {
        return rtrim(BASE_URL, '/') . '/reset_password.php?token=' . $token;
    }
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = dirname($_SERVER['PHP_SELF']);
    $basePath = $basePath === '/' ? '' : $basePath;
    return $protocol . '://' . $host . $basePath . '/reset_password.php?token=' . $token;
}

function sendPasswordResetEmail($userEmail, $fullName, $resetLink) {
    $emailBody = emailTemplate(
        'Password Reset Link',
        "<p>Hello <strong>" . sanitize($fullName) . "</strong>,</p>
        <p>Your account recovery request was approved. Please use the link below to reset your password.</p>
        <p style='margin: 30px 0;'><strong>Note:</strong> This link expires in <strong>1 hour</strong>.</p>",
        'Reset Password',
        $resetLink
    );
    return sendEmail($userEmail, 'Account Recovery Approved - Reset Your Password', $emailBody);
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
    $prefix = 'KBM-IT-';
    $stmt = $pdo->query("SELECT COUNT(*) FROM devices");
    $count = $stmt->fetchColumn() + 1;
    return $prefix . str_pad($count, 5, '0', STR_PAD_LEFT);
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

function redirect($url) {
    header('Location: ' . $url);
    exit();
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string) $token);
}

function csrfInputField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">';
}

function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function getAssignedAssets($userId) {
    global $pdo;
    $stmt = $pdo->prepare(
        "SELECT d.asset_tag,
                d.pc_name,
                d.ip_address,
                CONCAT(d.brand, ' ', d.model) AS name,
                dt.type_name AS category,
                d.status,
                da.assigned_date AS assigned_at
         FROM device_assignments da
         JOIN devices d ON da.device_id = d.id
         LEFT JOIN device_types dt ON d.device_type_id = dt.id
         WHERE da.employee_id = ? AND da.status = 'active'
         ORDER BY da.assigned_date DESC"
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function toggleUserStatus($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $status = $stmt->fetchColumn();

    if ($status === false) {
        return false;
    }

    $newStatus = $status === 'active' ? 'inactive' : 'active';
    $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $userId]);
    return $newStatus;
}

function deleteUserById($userId) {
    global $pdo;
    try {
        $pdo->beginTransaction();

        // Return any active assignments for this user back to stock
        $stmt = $pdo->prepare("SELECT id, device_id FROM device_assignments WHERE employee_id = ? AND status = 'active'");
        $stmt->execute([$userId]);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($assignments as $a) {
            $pdo->prepare("UPDATE device_assignments SET status = 'returned', returned_date = CURDATE() WHERE id = ?")->execute([$a['id']]);
            $pdo->prepare("UPDATE devices SET status = 'in_stock', location = 'IT Stock Room' WHERE id = ?")->execute([$a['device_id']]);

            // Notify admins about the automatic return
            $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($admins as $admin) {
                if (!empty($admin['id'])) {
                    addNotification($admin['id'], 'device_returned', 'Device Returned', "Device with ID {$a['device_id']} has been returned to stock due to user removal.", $a['device_id']);
                }
            }

            // Audit log
            if (session_status() == PHP_SESSION_NONE) {
                @session_start();
            }
            $currentUserId = $_SESSION['user_id'] ?? null;
            logAudit($currentUserId, 'AutoReturn', 'device_assignments', $a['id']);
        }

        // Finally delete the user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $res = $stmt->execute([$userId]);

        $pdo->commit();
        return $res;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return false;
    }
}

function processRecoveryRequest($recoveryId, $action, $adminId) {
    global $pdo;
    $validActions = ['approve' => 'approved', 'reject' => 'rejected'];
    if (!isset($validActions[$action])) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT user_id FROM account_recovery_requests WHERE id = ?");
    $stmt->execute([$recoveryId]);
    $userId = $stmt->fetchColumn();

    if (!$userId) {
        return false;
    }

    $pdo->prepare("UPDATE account_recovery_requests SET status = ?, resolved_at = NOW(), resolved_by = ? WHERE id = ?")
        ->execute([$validActions[$action], $adminId, $recoveryId]);

    if ($action === 'approve') {
        $pdo->prepare("UPDATE users SET status = 'active', failed_logins = 0, locked_until = NULL WHERE id = ?")
            ->execute([$userId]);
        addNotification($userId, 'request_approved', 'Account Recovered', 'Your account has been reactivated. You can now log in.', $recoveryId);

        $user = getUserInfo($userId);
        if ($user && !empty($user['email']) && isEmailConfigured()) {
            $token = createPasswordResetToken($userId);
            $resetLink = getPasswordResetLink($token);
            sendPasswordResetEmail($user['email'], $user['full_name'], $resetLink);
        }
    } else {
        addNotification($userId, 'request_rejected', 'Account Recovery Rejected', 'Your account recovery request was rejected. Contact admin for more info.', $recoveryId);
    }

    return true;
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
// MASTER KEY & SECURITY FUNCTIONS

// Check if user is a security IT approver (IT staff with security approval privileges)
function isSecurityAdmin($userId = null) {
    global $pdo;
    if ($userId === null) {
        $userId = $_SESSION['user_id'] ?? null;
    }
    if (!$userId) return false;
    
    $stmt = $pdo->prepare("SELECT role, is_security_admin FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result && $result['is_security_admin'] == 1 && $result['role'] === 'it_staff';
}

function setSecurityITApprover($userId, $enabled = true) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET is_security_admin = ? WHERE id = ? AND role = 'it_staff'");
    return $stmt->execute([$enabled ? 1 : 0, $userId]);
}

// Generate master security key for an admin
function generateMasterKey($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

// Set master key for security IT approver
function setMasterKey($userId, $masterKey) {
    global $pdo;
    $hashedKey = password_hash($masterKey, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("UPDATE users SET master_key_hash = ?, is_security_admin = 1 WHERE id = ? AND role = 'it_staff'");
    $stmt->execute([$hashedKey, $userId]);
    return $stmt->rowCount() > 0;
}

// Verify master key
function verifyMasterKey($userId, $masterKey) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT master_key_hash FROM users WHERE id = ? AND is_security_admin = 1");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    
    if ($result && password_verify($masterKey, $result['master_key_hash'])) {
        // Log successful verification
        logSecurityKeyUsage($userId, 'Key Verified', true);
        $_SESSION['master_key_verified'] = true;
        $_SESSION['master_key_verified_at'] = time();
        return true;
    }
    
    // Log failed verification
    logSecurityKeyUsage($userId, 'Key Verification Failed', false);
    return false;
}

// Log master key usage
function logSecurityKeyUsage($userId, $action, $success = true) {
    global $pdo;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt = $pdo->prepare("INSERT INTO security_key_logs (user_id, action, success, attempt_ip) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $success ? 1 : 0, $ipAddress]);
}

// Check if master key is currently verified (within session timeout)
function isMasterKeyVerified($timeoutMinutes = 30) {
    if (empty($_SESSION['master_key_verified']) || empty($_SESSION['master_key_verified_at'])) {
        return false;
    }
    
    $elapsed = (time() - $_SESSION['master_key_verified_at']) / 60;
    if ($elapsed > $timeoutMinutes) {
        unset($_SESSION['master_key_verified']);
        unset($_SESSION['master_key_verified_at']);
        return false;
    }
    
    return true;
}

// Request approval for IT/Admin user creation
function createUserApprovalRequest($requestedByUserId, $fullName, $email, $requestedRole, $employeeId = '', $department = '', $position = '', $phone = '', $passwordHash = '', $reason = '') {
    global $pdo;
    
    if (!in_array($requestedRole, ['it_staff', 'admin'])) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_approval_requests 
            (requested_by, employee_id, full_name, email, requested_role, department, position, phone, password_hash, reason, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        return $stmt->execute([
            $requestedByUserId,
            $employeeId,
            $fullName,
            $email,
            $requestedRole,
            $department,
            $position,
            $phone,
            $passwordHash,
            $reason
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

// Get pending user approval requests
function getPendingUserApprovals() {
    global $pdo;
    $stmt = $pdo->query("
        SELECT ar.*, u.full_name as requested_by_name 
        FROM user_approval_requests ar
        LEFT JOIN users u ON ar.requested_by = u.id
        WHERE ar.status = 'pending'
        ORDER BY ar.created_at DESC
    ");
    return $stmt->fetchAll();
}

// Approve user creation request
function approveUserCreation($approvalId, $approvedByUserId, $masterKey = null) {
    global $pdo;
    
    // Verify master key if required
    if ($masterKey && !verifyMasterKey($approvedByUserId, $masterKey)) {
        return ['success' => false, 'message' => 'Invalid master security key'];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM user_approval_requests WHERE id = ? AND status = 'pending'");
        $stmt->execute([$approvalId]);
        $request = $stmt->fetch();
        
        if (!$request) {
            return ['success' => false, 'message' => 'Request not found or already processed'];
        }
        
        // Create the user
        $insertStmt = $pdo->prepare("
            INSERT INTO users (employee_id, full_name, email, password, role, department, position, phone, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        
        $insertStmt->execute([
            $request['employee_id'],
            $request['full_name'],
            $request['email'],
            $request['password_hash'],
            $request['requested_role'],
            $request['department'],
            $request['position'],
            $request['phone']
        ]);
        
        $newUserId = $pdo->lastInsertId();

        addNotification(
            $newUserId,
            'user_creation_approved',
            'Account Approved',
            'Your IT/Admin account has been approved. You can now log in.',
            null
        );
        
        // Update approval request
        $updateStmt = $pdo->prepare("
            UPDATE user_approval_requests 
            SET status = 'approved', approved_by = ?, approved_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$approvedByUserId, $approvalId]);
        
        // Log audit
        logAudit($approvedByUserId, 'Approve User Creation', 'user_approval_requests', $approvalId, null, 
            "New {$request['requested_role']} user created: {$request['full_name']}");
        
        return ['success' => true, 'message' => 'User approved and created successfully', 'userId' => $newUserId];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Reject user creation request
function rejectUserCreation($approvalId, $rejectedByUserId, $rejectionReason = '') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE user_approval_requests 
            SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ? 
            WHERE id = ? AND status = 'pending'
        ");
        
        $result = $stmt->execute([$rejectedByUserId, $rejectionReason, $approvalId]);
        
        if ($result) {
            logAudit($rejectedByUserId, 'Reject User Creation', 'user_approval_requests', $approvalId);
        }
        
        return $result;
    } catch (PDOException $e) {
        return false;
    }
}

// Log master key audit
function logMasterKeyAudit($userId, $action, $details = null) {
    global $pdo;
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $stmt = $pdo->prepare("
        INSERT INTO master_key_audit (user_id, action, details, ip_address, user_agent) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $action, $details, $ipAddress, $userAgent]);
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

// ============================================================
// EMAIL NOTIFICATIONS & MAINTENANCE REMINDERS
// ============================================================

function queueEmailNotification($userId, $recipientEmail, $notificationType, $subject, $body, $relatedDeviceId = null, $relatedRepairId = null) {
    global $pdo;
    $recipientEmail = filter_var($recipientEmail, FILTER_VALIDATE_EMAIL);
    if (!$recipientEmail) {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT id FROM email_notifications WHERE recipient_email = ? AND notification_type = ? " .
        "AND ((related_device_id = ? OR (related_device_id IS NULL AND ? IS NULL)) " .
        "AND (related_repair_id = ? OR (related_repair_id IS NULL AND ? IS NULL))) " .
        "AND status = 'pending'"
    );
    $stmt->execute([$recipientEmail, $notificationType, $relatedDeviceId, $relatedDeviceId, $relatedRepairId, $relatedRepairId]);
    $existingId = $stmt->fetchColumn();
    if ($existingId) {
        return $existingId;
    }

    $insert = $pdo->prepare("INSERT INTO email_notifications (user_id, recipient_email, notification_type, subject, body, related_device_id, related_repair_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
    $insert->execute([$userId, $recipientEmail, $notificationType, $subject, $body, $relatedDeviceId, $relatedRepairId]);
    return $pdo->lastInsertId();
}

function sendPendingEmailNotifications() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM email_notifications WHERE status = 'pending' AND retry_count < 3 ORDER BY created_at ASC LIMIT 10");
    $stmt->execute();
    $notifications = $stmt->fetchAll();
    
    foreach ($notifications as $notif) {
        if (isEmailConfigured()) {
            $result = sendEmail($notif['recipient_email'], $notif['subject'], $notif['body']);
            if ($result['success']) {
                $pdo->prepare("UPDATE email_notifications SET status = 'sent', sent_at = NOW() WHERE id = ?")->execute([$notif['id']]);
            } else {
                $pdo->prepare("UPDATE email_notifications SET status = 'failed', failure_reason = ?, retry_count = retry_count + 1 WHERE id = ?")->execute([$result['message'], $notif['id']]);
            }
        }
    }
}

function createMaintenanceSchedule($deviceId, $maintenanceType, $description, $scheduledDate, $assignedTo = null) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO maintenance_schedules (device_id, maintenance_type, description, scheduled_date, next_due_date, assigned_to) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$deviceId, $maintenanceType, $description, $scheduledDate, $scheduledDate, $assignedTo]);
    $scheduleId = $pdo->lastInsertId();

    $deviceStmt = $pdo->prepare("SELECT asset_tag FROM devices WHERE id = ?");
    $deviceStmt->execute([$deviceId]);
    $device = $deviceStmt->fetch();
    $assetTag = $device ? $device['asset_tag'] : 'device';
    $dueDate = date('M d, Y', strtotime($scheduledDate));

    if ($assignedTo) {
        addNotification($assignedTo, 'maintenance_assigned', 'Maintenance Assigned', "You have been assigned maintenance for {$assetTag} due {$dueDate}.", $deviceId);
    } else {
        $staffStmt = $pdo->query("SELECT id FROM users WHERE role IN ('admin','it_staff') AND status = 'active'");
        $staffMembers = $staffStmt->fetchAll();
        foreach ($staffMembers as $member) {
            addNotification($member['id'], 'maintenance_assigned', 'Maintenance Task Pending', "Maintenance for {$assetTag} is scheduled for {$dueDate} and needs IT assignment.", $deviceId);
        }
    }

    return $scheduleId;
}

function getUpcomingMaintenanceReminders($daysAhead = 7) {
    global $pdo;
    $futureDate = date('Y-m-d', strtotime("+$daysAhead days"));
    $stmt = $pdo->prepare("
        SELECT ms.*, d.asset_tag, d.model, u.email, u.full_name
        FROM maintenance_schedules ms
        JOIN devices d ON ms.device_id = d.id
        LEFT JOIN users u ON ms.assigned_to = u.id
        WHERE ms.next_due_date <= ? AND ms.next_due_date > NOW()
        ORDER BY ms.next_due_date ASC
    ");
    $stmt->execute([$futureDate]);
    return $stmt->fetchAll();
}

function markMaintenanceCompleted($maintenanceId) {
    global $pdo;
    $pdo->prepare("UPDATE maintenance_schedules SET last_performed_date = NOW(), next_due_date = DATE_ADD(NOW(), INTERVAL 6 MONTH) WHERE id = ?")->execute([$maintenanceId]);
}

// ============================================================
// SERIAL NUMBER SEARCH
// ============================================================

function searchDeviceBySerialNumber($serialNumber) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT d.*, dt.type_name, u.full_name as created_by_name
        FROM devices d
        JOIN device_types dt ON d.device_type_id = dt.id
        LEFT JOIN users u ON d.created_by = u.id
        WHERE d.serial_number LIKE ? OR d.asset_tag LIKE ?
        LIMIT 1
    ");
    $search = "%$serialNumber%";
    $stmt->execute([$search, $search]);
    return $stmt->fetch();
}

function searchDevicesBySerialOrAsset($searchTerm) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT d.*, dt.type_name
        FROM devices d
        JOIN device_types dt ON d.device_type_id = dt.id
        WHERE d.serial_number LIKE ? OR d.asset_tag LIKE ? OR d.brand LIKE ? OR d.model LIKE ?
        ORDER BY d.updated_at DESC
        LIMIT 20
    ");
    $search = "%$searchTerm%";
    $stmt->execute([$search, $search, $search, $search]);
    return $stmt->fetchAll();
}

// ============================================================
// STATUS COLOR & DISPLAY
// ============================================================

function getStatusColor($status) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT color_code, icon_class, display_label FROM device_status_colors WHERE status = ?");
    $stmt->execute([$status]);
    $result = $stmt->fetch();
    if (!$result) {
        return ['color_code' => '#95a5a6', 'icon_class' => 'fas fa-question', 'display_label' => ucfirst(str_replace('_', ' ', $status))];
    }
    return $result;
}

function getStatusBadgeHtml($status) {
    $statusInfo = getStatusColor($status);
    return '<span class="status-badge" style="background-color: ' . $statusInfo['color_code'] . '20; color: ' . $statusInfo['color_code'] . '; border: 1px solid ' . $statusInfo['color_code'] . '; padding: 5px 10px; border-radius: 4px; font-size: 12px; display: inline-flex; align-items: center; gap: 5px;">
        <i class="' . $statusInfo['icon_class'] . '"></i> ' . $statusInfo['display_label'] . '
    </span>';
}

// ============================================================
// USER ASSET DASHBOARD
// ============================================================

function getEmployeeAssignedDevices($employeeId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT d.*, dt.type_name, da.assigned_date, da.purpose, 
               (SELECT COUNT(*) FROM device_repairs WHERE device_id = d.id AND repair_status IN ('pending', 'under_repair')) as pending_repairs,
               (SELECT COUNT(*) FROM maintenance_schedules WHERE device_id = d.id AND next_due_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)) as upcoming_maintenance
        FROM device_assignments da
        JOIN devices d ON da.device_id = d.id
        JOIN device_types dt ON d.device_type_id = dt.id
        WHERE da.employee_id = ? AND da.status = 'active' AND d.status = 'deployed'
        ORDER BY da.assigned_date DESC
    ");
    $stmt->execute([$employeeId]);
    return $stmt->fetchAll();
}

function getEmployeeDeviceStats($employeeId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT da.device_id) as total_devices,
            SUM(CASE WHEN d.status = 'deployed' THEN 1 ELSE 0 END) as active_devices,
            SUM(CASE WHEN d.status = 'under_repair' THEN 1 ELSE 0 END) as devices_under_repair,
            SUM(CASE WHEN dr.repair_status IN ('pending', 'under_repair') THEN 1 ELSE 0 END) as pending_repairs
        FROM device_assignments da
        LEFT JOIN devices d ON da.device_id = d.id
        LEFT JOIN device_repairs dr ON d.id = dr.device_id AND dr.repair_status IN ('pending', 'under_repair')
        WHERE da.employee_id = ? AND da.status = 'active'
    ");
    $stmt->execute([$employeeId]);
    return $stmt->fetch();
}

function getDeviceAssignmentHistory($deviceId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT da.*, u.full_name, u.employee_id
        FROM device_assignments da
        JOIN users u ON da.employee_id = u.id
        WHERE da.device_id = ?
        ORDER BY da.assigned_date DESC
        LIMIT 5
    ");
    $stmt->execute([$deviceId]);
    return $stmt->fetchAll();
}

// ============================================================
// REPAIR COMPLETION & NOTIFICATION
// ============================================================

function markRepairAsCompleted($repairId, $completionNotes = '') {
    global $pdo;
    
    // Get repair details
    $stmt = $pdo->prepare("
        SELECT dr.*, d.asset_tag, d.model, u.email, u.full_name as reporter_name, u.id as reported_by_id
        FROM device_repairs dr
        JOIN devices d ON dr.device_id = d.id
        JOIN users u ON dr.reported_by = u.id
        WHERE dr.id = ?
    ");
    $stmt->execute([$repairId]);
    $repair = $stmt->fetch();
    
    if (!$repair) {
        return ['success' => false, 'message' => 'Repair not found'];
    }
    
    // Update repair status
    $pdo->prepare("
        UPDATE device_repairs 
        SET repair_status = 'completed', completed_date = NOW(), repair_notes = ?
        WHERE id = ?
    ")->execute([$completionNotes, $repairId]);
    
    // Update device status back to deployed if it was under repair
    $pdo->prepare("UPDATE devices SET status = 'deployed' WHERE id = ? AND status = 'under_repair'")->execute([$repair['device_id']]);
    
    // Create system notification for employee
    addNotification(
        $repair['reported_by_id'],
        'repair_completed',
        'Device Repair Completed',
        'Your repair request for ' . $repair['asset_tag'] . ' has been completed. The device is now ready for use.',
        $repairId
    );
    
    // Send email to employee
    $subject = 'Device Repair Completed - ' . $repair['asset_tag'];
    $emailBody = emailTemplate(
        'Your Device Repair is Complete',
        "<p>Hello <strong>" . sanitize($repair['reporter_name']) . "</strong>,</p>
        <p>We're pleased to inform you that your device repair request has been completed.</p>
        <ul style='margin-left: 20px;'>
            <li><strong>Device:</strong> " . sanitize($repair['asset_tag']) . " (" . sanitize($repair['model']) . ")</li>
            <li><strong>Original Issue:</strong> " . sanitize(substr($repair['issue_description'], 0, 100)) . "...</li>
            <li><strong>Repair Completed:</strong> " . date('M d, Y h:i A') . "</li>
            <li><strong>Status:</strong> Ready for pickup/use</li>
        </ul>
        " . (!empty($completionNotes) ? "<p><strong>Repair Notes:</strong><br>" . sanitize($completionNotes) . "</p>" : "") . "
        <p>If you have any questions about the repair, please contact the IT department.</p>",
        'View Device Details',
        (defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'])) . '/view_device.php?id=' . $repair['device_id']
    );
    
    queueEmailNotification(
        $repair['reported_by_id'],
        $repair['email'],
        'repair_completed',
        $subject,
        $emailBody,
        $repair['device_id'],
        $repairId
    );
    
    return ['success' => true, 'message' => 'Repair marked as completed. Employee has been notified.'];
}

function getPendingRepairs() {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT dr.*, d.asset_tag, d.model, u.full_name as reporter_name, u.email, 
               DATEDIFF(NOW(), dr.started_date) as days_in_repair
        FROM device_repairs dr
        JOIN devices d ON dr.device_id = d.id
        JOIN users u ON dr.reported_by = u.id
        WHERE dr.repair_status IN ('pending', 'under_repair')
        ORDER BY dr.severity DESC, dr.started_date ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getCompletedRepairs($limit = 10) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT dr.*, d.asset_tag, d.model, u.full_name as reporter_name,
               DATEDIFF(dr.completed_date, dr.started_date) as days_to_repair
        FROM device_repairs dr
        JOIN devices d ON dr.device_id = d.id
        JOIN users u ON dr.reported_by = u.id
        WHERE dr.repair_status = 'completed'
        ORDER BY dr.completed_date DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

// ============================================================
// DEVICE DEPLOYMENT CONSISTENCY FUNCTIONS

/**
 * Ensure device status matches deployment status
 * Fixes orphaned 'deployed' devices with no active assignments
 * @return array Results of consistency check
 */
function fixDeploymentStatusConsistency() {
    global $pdo;
    $results = [
        'fixed_deployed' => 0,
        'fixed_unassigned' => 0,
        'errors' => []
    ];
    
    try {
        // Close orphaned active assignments (those with no active user) first
        $stmt = $pdo->prepare("            
            UPDATE device_assignments da
            LEFT JOIN users u ON da.employee_id = u.id AND u.status = 'active'
            SET da.status = 'returned', da.returned_date = CURDATE(), da.notes = CONCAT(COALESCE(da.notes, ''), ?)
            WHERE da.status = 'active' AND (da.employee_id IS NULL OR u.id IS NULL)
        ");
        $note = '\nAuto-returned by consistency cleanup on ' . date('Y-m-d H:i:s');
        $stmt->execute([$note]);
        $results['fixed_orphan_assignments'] = $stmt->rowCount();

        // Find deployed devices with NO valid active assignments and mark as in_stock
        $stmt = $pdo->prepare("            
            UPDATE devices d
            SET d.status = 'in_stock', d.location = 'IT Stock Room'
            WHERE d.status = 'deployed'
            AND d.id NOT IN (
                SELECT DISTINCT da.device_id
                FROM device_assignments da
                JOIN users u ON da.employee_id = u.id AND u.status = 'active'
                WHERE da.status = 'active'
            )
        ");
        $stmt->execute();
        $results['fixed_deployed'] = $stmt->rowCount();
        
        // Find valid active assignments where device is NOT deployed - update device to deployed
        $stmt = $pdo->prepare("            
            UPDATE devices d
            SET d.status = 'deployed'
            WHERE d.id IN (
                SELECT DISTINCT da.device_id
                FROM device_assignments da
                JOIN users u ON da.employee_id = u.id AND u.status = 'active'
                WHERE da.status = 'active'
            )
            AND d.status != 'deployed'
        ");
        $stmt->execute();
        $results['fixed_unassigned'] = $stmt->rowCount();
        
    } catch (PDOException $e) {
        $results['errors'][] = $e->getMessage();
    }
    
    return $results;
}

/**
 * Check if a device has any active deployment assignment
 * @param int $deviceId Device ID to check
 * @return bool True if device has active assignment, false otherwise
 */
function hasActiveDeployment($deviceId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM device_assignments WHERE device_id = ? AND status = 'active'");
    $stmt->execute([$deviceId]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Get the active assignment for a device
 * @param int $deviceId Device ID
 * @return array|null Assignment details or null if none
 */
function getActiveDeviceAssignment($deviceId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT da.*, u.full_name, u.email, u.department 
        FROM device_assignments da
        JOIN users u ON da.employee_id = u.id
        WHERE da.device_id = ? AND da.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$deviceId]);
    return $stmt->fetch();
}