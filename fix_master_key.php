<?php
/**
 * Fix Master Key - generates a proper bcrypt hash
 * Delete after use
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';

// Only allow admin access
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'it_staff'])) {
    die("Access denied. You must be logged in as admin or IT staff.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $masterKey = trim($_POST['master_key'] ?? '');
    
    if (empty($masterKey)) {
        die("Error: Master key cannot be empty!");
    }
    
    // Generate proper bcrypt hash
    $hashedKey = password_hash($masterKey, PASSWORD_BCRYPT);
    
    // Update database
    $stmt = $pdo->prepare("UPDATE users SET master_key_hash = ?, is_security_admin = 1 WHERE id = 4 AND role = 'it_staff'");
    $result = $stmt->execute([$hashedKey]);
    
    if ($stmt->rowCount() > 0) {
        echo "<h2 style='color:green;'>✓ Success!</h2>";
        echo "<p>Master key has been set for Alfonso Aninias (ID: 4)</p>";
        echo "<p><strong>Your Master Key (save this securely):</strong></p>";
        echo "<p style='background:#f0f0f0; padding:10px; font-family:monospace; word-break:break-all;'>" . htmlspecialchars($masterKey) . "</p>";
        echo "<p>⚠️ <strong>Delete this file immediately:</strong> rm fix_master_key.php</p>";
    } else {
        die("Error: Could not update user. Make sure user ID 4 exists and is IT staff.");
    }
} else {
    ?>
    <h2>Fix Master Key for Alfonso Aninias</h2>
    <form method="POST">
        <p>Paste your generated master key below (from PowerShell command):</p>
        <input type="text" name="master_key" placeholder="Example: a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6" required style="width:100%; padding:8px; font-family:monospace;">
        <br><br>
        <button type="submit" style="padding:10px 20px; background:green; color:white; border:none; cursor:pointer;">Set Master Key</button>
    </form>
    <hr>
    <h3>How to generate a master key:</h3>
    <ol>
        <li>Open PowerShell</li>
        <li>Run: <code>C:\xampp\php\php.exe -r "echo bin2hex(random_bytes(16));"</code></li>
        <li>Copy the output (it will look like: a1b2c3d4...)</li>
        <li>Paste it above and click "Set Master Key"</li>
    </ol>
    <?php
}
?>
