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

function createPasswordResetToken($userId) {
    global $pdo;
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
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
        <p>Your account recovery request was approved. Please use the link below to reset your password and regain access to the system.</p>
        <p style='margin: 20px 0;'><strong>Note:</strong> This link expires in <strong>24 hours</strong>.</p>",
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

// ============================================================
// EMAIL NOTIFICATIONS & MAINTENANCE REMINDERS
// ============================================================

function queueEmailNotification($userId, $recipientEmail, $notificationType, $subject, $body, $relatedDeviceId = null, $relatedRepairId = null) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO email_notifications (user_id, recipient_email, notification_type, subject, body, related_device_id, related_repair_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$userId, $recipientEmail, $notificationType, $subject, $body, $relatedDeviceId, $relatedRepairId]);
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
    return $pdo->lastInsertId();
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