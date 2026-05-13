<?php
/**
 * KBMC Asset Management - User Profile
 */
$pageTitle = 'My Profile';
require_once 'includes/header.php';

$user = getUserInfo($_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    try {
        if ($full_name) {
            $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?")->execute([$full_name, $phone, $_SESSION['user_id']]);
        }

        if ($current_password && $new_password) {
            if (password_verify($current_password, $user['password'])) {
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($new_password, PASSWORD_BCRYPT), $_SESSION['user_id']]);
                setFlashMessage('success', 'Profile updated and password changed.');
            } else {
                setFlashMessage('error', 'Current password is incorrect.');
                header('Location: profile.php');
                exit();
            }
        } else {
            setFlashMessage('success', 'Profile updated successfully.');
        }

        header('Location: profile.php');
        exit();
    } catch (PDOException $e) {
        setFlashMessage('error', 'Error updating profile.');
    }
}

// Get assigned devices
$stmt = $pdo->prepare("SELECT da.*, d.asset_tag, d.model, d.brand, d.status as device_status FROM device_assignments da JOIN devices d ON da.device_id = d.id WHERE da.employee_id = ? AND da.status = 'active'");
$stmt->execute([$_SESSION['user_id']]);
$myDevices = $stmt->fetchAll();
?>

<div class="page-header">
    <h1><i class="fas fa-user-cog"></i> My Profile</h1>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3>Profile Information</h3></div>
        <div class="card-body">
            <div style="text-align: center; margin-bottom: 25px;">
                <div style="width: 100px; height: 100px; background: var(--kbmc-red-light); color: var(--kbmc-red); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 40px; margin: 0 auto 15px;">
                    <i class="fas fa-user"></i>
                </div>
                <h3 style="margin-bottom: 5px;"><?php echo sanitize($user['full_name']); ?></h3>
                <span class="status-badge" style="background: var(--kbmc-red-light); color: var(--kbmc-red); border: 1px solid var(--kbmc-red);"><?php echo $role_names[$user['role']] ?? $user['role']; ?></span>
            </div>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Employee ID</label>
                        <input type="text" class="form-control" value="<?php echo sanitize($user['employee_id'] ?: 'N/A'); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="text" class="form-control" value="<?php echo sanitize($user['email']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" class="form-control" value="<?php echo sanitize($user['department']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Position</label>
                        <input type="text" class="form-control" value="<?php echo sanitize($user['position']); ?>" disabled>
                    </div>
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo sanitize($user['full_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?php echo sanitize($user['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Current Password (to change)</label>
                        <input type="password" name="current_password" class="form-control" placeholder="Enter current password">
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Enter new password">
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Profile</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3>My Assigned Devices</h3></div>
        <div class="card-body">
            <?php if (empty($myDevices)): ?>
            <div class="empty-state"><i class="fas fa-laptop" style="font-size: 40px; color: #ddd;"></i><h4>No devices assigned</h4></div>
            <?php else: ?>
            <?php foreach ($myDevices as $md): ?>
            <div style="display: flex; align-items: center; gap: 15px; padding: 15px; border-bottom: 1px solid #f0f0f0;">
                <div style="width: 50px; height: 50px; background: var(--kbmc-red-light); color: var(--kbmc-red); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                    <i class="fas fa-laptop"></i>
                </div>
                <div style="flex: 1;">
                    <div style="font-weight: 600; font-size: 14px;"><?php echo sanitize($md['asset_tag']); ?> - <?php echo sanitize($md['brand'] . ' ' . $md['model']); ?></div>
                    <div style="font-size: 12px; color: #666; margin-top: 2px;">Assigned: <?php echo formatDate($md['assigned_date']); ?></div>
                    <div style="font-size: 12px; color: #888; margin-top: 1px;">Purpose: <?php echo sanitize($md['purpose']); ?></div>
                </div>
                <a href="view_device.php?id=<?php echo $md['device_id']; ?>" class="btn btn-sm btn-outline">View</a>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
