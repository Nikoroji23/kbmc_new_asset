<?php
/**
 * KBMC Asset Management - Bulk Import Assets from CSV
 * Imports employees and their assigned assets from Excel CSV file
 */

$pageTitle = 'Import Assets from CSV';
require_once 'includes/header.php';

// Only admin and IT staff can access this
requireITStaff();

$importStatus = [];
$importSummary = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['csv_file'])) {
    try {
        $file = $_FILES['csv_file'];
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed');
        }
        
        if ($file['size'] > 5 * 1024 * 1024) { // 5MB max
            throw new Exception('File size exceeds 5MB limit');
        }
        
        $tmpFile = $file['tmp_name'];
        
        // Open CSV file
        if (!file_exists($tmpFile)) {
            throw new Exception('Temporary file not found');
        }
        
        $handle = fopen($tmpFile, 'r');
        if (!$handle) {
            throw new Exception('Cannot open CSV file');
        }
        
        // Read header row
        $header = fgetcsv($handle);
        if (!$header) {
            throw new Exception('Cannot read CSV header');
        }
        
        // Define column mappings
        $columnMap = [
            'NAME' => 0,
            'DEPARTMENT' => 1,
            'PC NAME' => 2,
            'IP ADRESS' => 3,
            'MONITOR 1' => 4,
            'MONITOR 2' => 5,
            'MOUSE' => 6,
            'KEYBOARD' => 7,
            'SYSTEM UNIT' => 8,
            'UPS' => 9,
            'LAPTOP' => 10,
            'CHARGER' => 11,
            'MOUSE 1' => 12,
            'PRINTER 1' => 13,
            'PRINTER 2' => 14,
            'STORAGE' => 15,
            'SWITCH' => 16,
            'REMARKS' => 17
        ];
        
        // Device type mapping
        $deviceTypes = getDeviceTypeMap();
        
        $usersCreated = 0;
        $devicesCreated = 0;
        $assignmentsCreated = 0;
        $errors = [];
        $lineNumber = 1;
        
        // Get current admin
        $adminId = $_SESSION['user_id'];
        
        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;
            
            try {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }
                
                $name = trim($row[$columnMap['NAME']] ?? '');
                $department = trim($row[$columnMap['DEPARTMENT']] ?? '');
                $pcName = trim($row[$columnMap['PC NAME']] ?? '');
                $ipAddress = trim($row[$columnMap['IP ADRESS']] ?? '');
                
                if (empty($name)) {
                    continue;
                }
                
                // Create user account if not exists
                $email = generateEmail($name);
                $employeeId = generateEmployeeId($name);
                
                $userCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $userCheck->execute([$email]);
                $existingUser = $userCheck->fetch();
                
                if ($existingUser) {
                    $userId = $existingUser['id'];
                } else {
                    $stmt = $pdo->prepare("INSERT INTO users (employee_id, full_name, email, password, role, department, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $employeeId,
                        $name,
                        $email,
                        password_hash('password', PASSWORD_BCRYPT),
                        'employee',
                        $department,
                        'active'
                    ]);
                    $userId = $pdo->lastInsertId();
                    $usersCreated++;
                }
                
                // Process devices assigned to this user
                $assetColumns = [
                    ['name' => 'MONITOR 1', 'type' => 'monitor', 'column' => 4],
                    ['name' => 'MONITOR 2', 'type' => 'monitor', 'column' => 5],
                    ['name' => 'MOUSE', 'type' => 'mouse', 'column' => 6],
                    ['name' => 'KEYBOARD', 'type' => 'keyboard', 'column' => 7],
                    ['name' => 'SYSTEM UNIT', 'type' => 'system unit', 'column' => 8],
                    ['name' => 'UPS', 'type' => 'ups', 'column' => 9],
                    ['name' => 'LAPTOP', 'type' => 'laptop', 'column' => 10],
                    ['name' => 'CHARGER', 'type' => 'charger', 'column' => 11],
                    ['name' => 'MOUSE 1', 'type' => 'mouse', 'column' => 12],
                    ['name' => 'PRINTER 1', 'type' => 'printer', 'column' => 13],
                    ['name' => 'PRINTER 2', 'type' => 'printer', 'column' => 14],
                    ['name' => 'STORAGE', 'type' => 'storage', 'column' => 15],
                    ['name' => 'SWITCH', 'type' => 'switch', 'column' => 16],
                ];
                
                foreach ($assetColumns as $asset) {
                    $assetTag = trim($row[$asset['column']] ?? '');
                    
                    if (empty($assetTag) || $assetTag === 'N/A' || $assetTag === 'KBM-IT-00') {
                        continue;
                    }
                    
                    // Get device type ID
                    $typeId = $deviceTypes[strtolower($asset['type'])] ?? null;
                    if (!$typeId) {
                        continue;
                    }
                    
                    // Check if device exists
                    $deviceCheck = $pdo->prepare("SELECT id FROM devices WHERE asset_tag = ?");
                    $deviceCheck->execute([$assetTag]);
                    $existingDevice = $deviceCheck->fetch();
                    
                    if ($existingDevice) {
                        $deviceId = $existingDevice['id'];
                    } else {
                        // Create device
                        $stmt = $pdo->prepare("INSERT INTO devices (asset_tag, device_type_id, serial_number, ip_address, status, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $assetTag,
                            $typeId,
                            $assetTag,
                            (!empty($ipAddress) && $asset['name'] === 'SYSTEM UNIT') ? $ipAddress : null,
                            'deployed',
                            $adminId
                        ]);
                        $deviceId = $pdo->lastInsertId();
                        $devicesCreated++;
                    }
                    
                    // Check if assignment exists
                    $assignmentCheck = $pdo->prepare("SELECT id FROM device_assignments WHERE device_id = ? AND employee_id = ? AND status = 'active'");
                    $assignmentCheck->execute([$deviceId, $userId]);
                    $existingAssignment = $assignmentCheck->fetch();
                    
                    if (!$existingAssignment) {
                        // Create assignment
                        $stmt = $pdo->prepare("INSERT INTO device_assignments (device_id, employee_id, assigned_by, assigned_date, status) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $deviceId,
                            $userId,
                            $adminId,
                            date('Y-m-d'),
                            'active'
                        ]);
                        $assignmentsCreated++;
                    }
                }
                
            } catch (Exception $e) {
                $errors[] = "Line $lineNumber: " . $e->getMessage();
            }
        }
        
        fclose($handle);
        
        $importSummary = [
            'users_created' => $usersCreated,
            'devices_created' => $devicesCreated,
            'assignments_created' => $assignmentsCreated,
            'errors' => $errors
        ];
        
        setFlashMessage('success', "Import completed! Created $usersCreated users, $devicesCreated devices, $assignmentsCreated assignments.");
        
    } catch (Exception $e) {
        setFlashMessage('error', 'Import failed: ' . $e->getMessage());
    }
}

function generateEmail($fullName) {
    $nameParts = explode(' ', $fullName);
    $firstName = strtolower($nameParts[0] ?? '');
    $lastName = strtolower($nameParts[count($nameParts) - 1] ?? '');
    $baseEmail = $firstName . '.' . $lastName . '@kbmc.com';
    
    global $pdo;
    $counter = 1;
    $email = $baseEmail;
    
    while (true) {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if (!$check->fetch()) {
            return $email;
        }
        $email = $firstName . '.' . $lastName . $counter . '@kbmc.com';
        $counter++;
    }
}

function generateEmployeeId($fullName) {
    global $pdo;
    
    // Find max employee ID
    $stmt = $pdo->query("SELECT MAX(CAST(employee_id AS SIGNED)) as max_id FROM users WHERE employee_id REGEXP '^[0-9]+$'");
    $result = $stmt->fetch();
    $maxId = $result['max_id'] ?? 0;
    
    return str_pad($maxId + 1, 5, '0', STR_PAD_LEFT);
}

function getDeviceTypeMap() {
    global $pdo;
    
    $types = [
        'monitor' => 'Monitor',
        'mouse' => 'Mouse',
        'keyboard' => 'Keyboard',
        'system unit' => 'System Unit',
        'ups' => 'UPS',
        'laptop' => 'Laptop',
        'charger' => 'Charger',
        'printer' => 'Printer',
        'storage' => 'Storage Device',
        'switch' => 'Network Switch'
    ];
    
    $typeMap = [];
    
    foreach ($types as $key => $typeName) {
        $stmt = $pdo->prepare("SELECT id FROM device_types WHERE LOWER(type_name) = LOWER(?)");
        $stmt->execute([$typeName]);
        $result = $stmt->fetch();
        
        if ($result) {
            $typeMap[$key] = $result['id'];
        } else {
            // Create device type if it doesn't exist
            $stmt = $pdo->prepare("INSERT INTO device_types (type_name) VALUES (?)");
            $stmt->execute([$typeName]);
            $typeMap[$key] = $pdo->lastInsertId();
        }
    }
    
    return $typeMap;
}

?>

<div class="page-header">
    <h1><i class="fas fa-file-import"></i> Import Assets from CSV</h1>
</div>

<?php if (!empty($importSummary)): ?>
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header" style="background: #27ae60; color: white;">
        <h3><i class="fas fa-check-circle"></i> Import Summary</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
            <div style="background: #ecf0f1; padding: 15px; border-radius: 5px; text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: #3498db;"><?php echo $importSummary['users_created']; ?></div>
                <div style="font-size: 12px; color: #7f8c8d;">Users Created</div>
            </div>
            <div style="background: #ecf0f1; padding: 15px; border-radius: 5px; text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: #27ae60;"><?php echo $importSummary['devices_created']; ?></div>
                <div style="font-size: 12px; color: #7f8c8d;">Devices Created</div>
            </div>
            <div style="background: #ecf0f1; padding: 15px; border-radius: 5px; text-align: center;">
                <div style="font-size: 24px; font-weight: bold; color: #e74c3c;"><?php echo $importSummary['assignments_created']; ?></div>
                <div style="font-size: 12px; color: #7f8c8d;">Assignments Created</div>
            </div>
        </div>
        
        <?php if (!empty($importSummary['errors'])): ?>
        <div style="margin-top: 20px;">
            <h4 style="color: #e74c3c;"><i class="fas fa-exclamation-triangle"></i> Errors Encountered:</h4>
            <ul style="color: #7f8c8d; font-size: 12px;">
                <?php foreach ($importSummary['errors'] as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3>Upload CSV File</h3>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="csv_file"><i class="fas fa-file-csv"></i> Select CSV File</label>
                <input type="file" id="csv_file" name="csv_file" class="form-control" accept=".csv" required>
                <small style="color: #7f8c8d;">CSV file should contain columns: NAME, DEPARTMENT, PC NAME, IP ADDRESS, MONITOR 1, MONITOR 2, MOUSE, KEYBOARD, SYSTEM UNIT, UPS, LAPTOP, CHARGER, MOUSE 1, PRINTER 1, PRINTER 2, STORAGE, SWITCH, REMARKS</small>
            </div>
            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Import Assets</button>
            </div>
        </form>
        
        <div style="margin-top: 30px; padding: 15px; background: #ecf0f1; border-radius: 5px;">
            <h4><i class="fas fa-info-circle"></i> Import Instructions</h4>
            <ul style="font-size: 13px; line-height: 1.8;">
                <li><strong>CSV Format:</strong> The file should be in CSV (Comma-Separated Values) format</li>
                <li><strong>User Creation:</strong> Each unique NAME will create a user account with:
                    <ul>
                        <li>Default password: <code>password</code></li>
                        <li>Generated email: firstname.lastname@kbmc.com</li>
                        <li>Generated Employee ID: Auto-incremented 5-digit number</li>
                    </ul>
                </li>
                <li><strong>Device Creation:</strong> All asset columns will create device records and link them to the user</li>
                <li><strong>Valid Assets:</strong> Assets marked as "N/A" or "KBM-IT-00" will be skipped</li>
                <li><strong>Device Status:</strong> All imported devices are set to "deployed" status</li>
                <li><strong>Duplicate Prevention:</strong> Existing users and devices will not be duplicated</li>
            </ul>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
