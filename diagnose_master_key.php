<?php
/**
 * Diagnostic script to check master key setup
 * Delete after use
 */

require_once 'includes/config.php';

echo "<h2>Master Key Diagnostic Report</h2>";

// Check if tables exist
echo "<h3>1. Database Tables Check</h3>";
$tables = ['users', 'master_key_audit', 'security_key_logs', 'user_approval_requests'];
foreach ($tables as $table) {
    $result = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
    echo "Table '$table': " . ($result ? "✓ EXISTS" : "✗ MISSING") . "<br>";
}

// Check user 4 details
echo "<h3>2. Alfonso Aninias (User ID 4) Details</h3>";
$user = $pdo->query("SELECT id, full_name, email, role, is_security_admin, master_key_hash, security_key_verified FROM users WHERE id = 4")->fetch();
if ($user) {
    echo "<pre>" . print_r($user, true) . "</pre>";
    echo "Role is 'it_staff': " . ($user['role'] === 'it_staff' ? "✓ YES" : "✗ NO (PROBLEM!)") . "<br>";
    echo "Master key hash set: " . ($user['master_key_hash'] ? "✓ YES" : "✗ NO") . "<br>";
    echo "Is security admin: " . ($user['is_security_admin'] ? "✓ YES (1)" : "✗ NO (0)") . "<br>";
} else {
    echo "✗ User ID 4 not found in database!<br>";
}

// Check users table columns
echo "<h3>3. Users Table Columns</h3>";
$columns = $pdo->query("DESCRIBE users")->fetchAll();
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th></tr>";
foreach ($columns as $col) {
    echo "<tr><td>" . $col['Field'] . "</td><td>" . $col['Type'] . "</td></tr>";
}
echo "</table>";

// Test setMasterKey function
echo "<h3>4. Test Master Key Function</h3>";
if (function_exists('setMasterKey')) {
    echo "✓ setMasterKey() function exists<br>";
    $testKey = 'test_key_' . bin2hex(random_bytes(8));
    echo "Testing with key: $testKey<br>";
    $result = setMasterKey(4, $testKey);
    echo "Function returned: " . ($result ? "true (SUCCESS)" : "false (FAILED)") . "<br>";
    
    // Check if it was actually set
    $check = $pdo->query("SELECT master_key_hash FROM users WHERE id = 4")->fetch();
    if ($check['master_key_hash']) {
        echo "✓ Hash is now in database: " . substr($check['master_key_hash'], 0, 20) . "...<br>";
    } else {
        echo "✗ Hash NOT set in database<br>";
    }
} else {
    echo "✗ setMasterKey() function NOT FOUND<br>";
}

echo "<h3>5. Manual SQL Test</h3>";
$testHashKey = bin2hex(random_bytes(16));
$testHash = password_hash($testHashKey, PASSWORD_BCRYPT);
echo "Generated test key: $testHashKey<br>";
echo "Bcrypt hash: " . substr($testHash, 0, 30) . "...<br>";

$stmt = $pdo->prepare("UPDATE users SET master_key_hash = ?, is_security_admin = 1 WHERE id = 4 AND role = 'it_staff'");
$stmt->execute([$testHash]);
echo "Rows affected: " . $stmt->rowCount() . " (should be 1)<br>";

// Verify
$verify = $pdo->query("SELECT master_key_hash FROM users WHERE id = 4")->fetch();
if (password_verify($testHashKey, $verify['master_key_hash'])) {
    echo "✓ Password verification works!<br>";
} else {
    echo "✗ Password verification FAILED<br>";
}

echo "<hr>";
echo "<strong>Summary:</strong> Check items marked with ✗. If all are ✓, the master key is properly configured.<br>";
echo "<p style='color:red;'><strong>Delete this file after reviewing:</strong> rm diagnose_master_key.php</p>";
?>
