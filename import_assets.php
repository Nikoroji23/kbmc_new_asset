<?php
/**
 * KBMC Asset Management - Bulk Import Assets from CSV or Excel
 * Imports employees and their assigned assets from CSV or XLSX files
 */

$pageTitle = 'Import Assets from CSV or Excel';
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
        
        if (!file_exists($tmpFile)) {
            throw new Exception('Temporary file not found');
        }
        
        $fileName = $file['name'] ?? '';
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['csv', 'xlsx'];
        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception('Unsupported file type. Upload a CSV or XLSX file.');
        }

        $rows = readImportRows($tmpFile, $extension);
        if (empty($rows)) {
            throw new Exception('Uploaded file contains no data.');
        }

        $header = array_shift($rows);
        if (!is_array($header) || empty($header)) {
            throw new Exception('Cannot read spreadsheet header');
        }
        
        // Normalize headers and build column index map
        $headerMap = [];
        foreach ($header as $index => $columnName) {
            $cleanName = preg_replace('/^\xEF\xBB\xBF/', '', $columnName);
            $cleanName = strtoupper(trim($cleanName));
            $cleanName = preg_replace('/\s+/', ' ', $cleanName);
            if ($cleanName === 'IP ADRESS') {
                $cleanName = 'IP ADDRESS';
            }
            $headerMap[$cleanName] = $index;
        }

        $requiredHeaders = ['NAME', 'DEPARTMENT', 'PC NAME', 'IP ADDRESS'];
        foreach ($requiredHeaders as $requiredHeader) {
            if (!array_key_exists($requiredHeader, $headerMap)) {
                throw new Exception('Missing required CSV column: ' . $requiredHeader);
            }
        }

        // Device type mapping
        $deviceTypes = getDeviceTypeMap();

        $assetColumns = [
            ['name' => 'MONITOR 1', 'type' => 'monitor'],
            ['name' => 'MONITOR 2', 'type' => 'monitor'],
            ['name' => 'MOUSE', 'type' => 'mouse'],
            ['name' => 'KEYBOARD', 'type' => 'keyboard'],
            ['name' => 'SYSTEM UNIT', 'type' => 'system unit'],
            ['name' => 'UPS', 'type' => 'ups'],
            ['name' => 'LAPTOP', 'type' => 'laptop'],
            ['name' => 'CHARGER', 'type' => 'charger'],
            ['name' => 'MOUSE 1', 'type' => 'mouse'],
            ['name' => 'PRINTER 1', 'type' => 'printer'],
            ['name' => 'PRINTER 2', 'type' => 'printer'],
            ['name' => 'STORAGE', 'type' => 'storage'],
            ['name' => 'SWITCH', 'type' => 'switch'],
        ];

        foreach ($assetColumns as &$asset) {
            $asset['column_index'] = isset($headerMap[$asset['name']]) ? $headerMap[$asset['name']] : null;
        }
        unset($asset);
        
        $usersCreated = 0;
        $devicesCreated = 0;
        $assignmentsCreated = 0;
        $errors = [];
        $lineNumber = 1;
        
        // Get current admin
        $adminId = $_SESSION['user_id'];
        // Verify the admin user still exists
$adminCheck = $pdo->prepare("SELECT id FROM users WHERE id = ?");
$adminCheck->execute([$adminId]);
if (!$adminCheck->fetch()) {
    throw new Exception('Your session user no longer exists in the database. Please log out and log back in.');
}
        foreach ($rows as $row) {
            $lineNumber++;
            
            try {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }
                
                $name = trim($row[$headerMap['NAME']] ?? '');
                $department = trim($row[$headerMap['DEPARTMENT']] ?? '');
                $pcName = trim($row[$headerMap['PC NAME']] ?? '');
                $ipAddress = trim($row[$headerMap['IP ADDRESS']] ?? '');
                
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
                foreach ($assetColumns as $asset) {
                    if ($asset['column_index'] === null) {
                        continue;
                    }
                    
                    $assetTagRaw = trim($row[$asset['column_index']] ?? '');
                    if (empty($assetTagRaw) || strcasecmp($assetTagRaw, 'N/A') === 0 || strcasecmp($assetTagRaw, 'KBM-IT-00') === 0) {
                        continue;
                    }
                    
                    $typeId = $deviceTypes[$asset['type']] ?? null;
                    if (!$typeId) {
                        continue;
                    }
                    
                    $assetTag = normalizeImportAssetTag($assetTagRaw, $typeId);
                    
                    // Check if device exists
                    $deviceCheck = $pdo->prepare("SELECT id, pc_name FROM devices WHERE asset_tag = ?");
                    $deviceCheck->execute([$assetTag]);
                    $existingDevice = $deviceCheck->fetch();
                    
                    if ($existingDevice) {
                        $deviceId = $existingDevice['id'];
                        if (!empty($pcName) && empty($existingDevice['pc_name'])) {
                            $updatePcName = $pdo->prepare("UPDATE devices SET pc_name = ? WHERE id = ?");
                            $updatePcName->execute([$pcName, $deviceId]);
                        }
                    } else {
                        // Create device
                        $stmt = $pdo->prepare("INSERT INTO devices (asset_tag, device_type_id, serial_number, ip_address, pc_name, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $assetTag,
                            $typeId,
                            $assetTag,
                            (!empty($ipAddress) && $asset['name'] === 'SYSTEM UNIT') ? $ipAddress : null,
                            $pcName ?: null,
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

function readImportRows($filePath, $extension) {
    if ($extension === 'csv') {
        return readCsvRows($filePath);
    }
    if ($extension === 'xlsx') {
        return readXlsxRows($filePath);
    }
    throw new Exception('Unsupported import file type.');
}

function readCsvRows($filePath) {
    ini_set('auto_detect_line_endings', '1');
    $rows = [];
    if (($handle = fopen($filePath, 'r')) === false) {
        throw new Exception('Cannot open CSV file');
    }
    while (($row = fgetcsv($handle)) !== false) {
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

function readXlsxRows($filePath) {
    if (!class_exists('ZipArchive')) {
        throw new Exception('Excel import requires the PHP Zip extension.');
    }

    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new Exception('Cannot open XLSX file');
    }

    $sharedStrings = [];
    if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
        $sharedStrings = parseXlsxSharedStrings($xml);
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        $zip->close();
        throw new Exception('Cannot locate worksheet data in XLSX file');
    }

    $rows = parseXlsxSheet($sheetXml, $sharedStrings);
    $zip->close();
    return $rows;
}

function parseXlsxSharedStrings($xml) {
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    $strings = [];

    foreach ($dom->getElementsByTagName('si') as $si) {
        $text = '';
        foreach ($si->getElementsByTagName('t') as $t) {
            $text .= $t->nodeValue;
        }
        $strings[] = $text;
    }

    return $strings;
}

function parseXlsxSheet($xml, $sharedStrings) {
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    $rows = [];

    foreach ($dom->getElementsByTagName('row') as $rowNode) {
        $row = [];

        foreach ($rowNode->getElementsByTagName('c') as $cell) {
            $cellRef = $cell->getAttribute('r');
            $columnIndex = xlsxColumnIndexFromReference($cellRef);
            $valueNode = $cell->getElementsByTagName('v')->item(0);
            $value = $valueNode ? $valueNode->nodeValue : '';
            if ($cell->getAttribute('t') === 's' && $value !== '') {
                $value = $sharedStrings[intval($value)] ?? $value;
            }
            $row[$columnIndex] = $value;
        }

        if (!empty($row)) {
            ksort($row);
            $rows[] = array_values($row);
        }
    }

    return $rows;
}

function xlsxColumnIndexFromReference($reference) {
    if (!preg_match('/^([A-Z]+)\d+$/', $reference, $matches)) {
        return 0;
    }

    $letters = $matches[1];
    $index = 0;
    foreach (str_split($letters) as $char) {
        $index = $index * 26 + (ord($char) - ord('A') + 1);
    }

    return $index - 1;
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

function isValidAssetTag($value) {
    return preg_match('/^[A-Z0-9]+(?:-[A-Z0-9]+)*-\d+$/i', trim($value));
}

function normalizeImportAssetTag($assetTagRaw, $deviceTypeId) {
    $assetTagRaw = trim($assetTagRaw);
    if (isValidAssetTag($assetTagRaw)) {
        return strtoupper($assetTagRaw);
    }
    return generateAssetTag($deviceTypeId);
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
                <label for="csv_file"><i class="fas fa-file-csv"></i> Select CSV or Excel File</label>
                <input type="file" id="csv_file" name="csv_file" class="form-control" accept=".csv,.xlsx" required>
                <small style="color: #7f8c8d;">Upload a CSV or XLSX file. Header order is flexible and the importer normalizes header names.</small>
            </div>
            <div style="margin-top: 20px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Import Assets</button>
            </div>
        </form>
        
        <div style="margin-top: 30px; padding: 15px; background: #ecf0f1; border-radius: 5px;">
            <h4><i class="fas fa-info-circle"></i> Import Instructions</h4>
            <ul style="font-size: 13px; line-height: 1.8;">
                <li><strong>Upload Format:</strong> The file can be a CSV or Excel (.xlsx) workbook</li>
                <li><strong>User Creation:</strong> Each unique NAME will create a user account with:
                    <ul>
                        <li>Default password: <code>password</code></li>
                        <li>Generated email: firstname.lastname@kbmc.com</li>
                        <li>Generated Employee ID: Auto-incremented 5-digit number</li>
                    </ul>
                </li>
                <li><strong>Device Creation:</strong> All asset columns will create device records and link them to the user</li>
                <li><strong>Asset Tag Generation:</strong> If a cell does not contain a standard asset tag, the system generates a valid KBM-IT asset tag for that device type</li>
                <li><strong>Valid Assets:</strong> Assets marked as "N/A" or "KBM-IT-00" will be skipped</li>
                <li><strong>Device Status:</strong> All imported devices are set to "deployed" status</li>
                <li><strong>Duplicate Prevention:</strong> Existing users and devices will not be duplicated</li>
            </ul>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
