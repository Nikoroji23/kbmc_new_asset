<?php
/**
 * KBMC Asset Management - IT User Approval API
 * REST API for master IT validation and approval
 */
require_once 'includes/functions.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Check authentication
if (!isLoggedIn() || !hasRole('it_staff')) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if user is master IT
if (!isMasterITUser($_SESSION['user_id'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Only master IT administrator can approve users']);
    exit();
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? '';
$itUserId = (int)($input['it_user_id'] ?? 0);
$masterKeySecret = $input['master_key_secret'] ?? '';
$reason = trim($input['reason'] ?? '');

header('Content-Type: application/json');

if (!$action || !$itUserId || !$masterKeySecret) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

try {
    if ($action === 'approve') {
        $result = approveITUserWithMasterKey($itUserId, $masterKeySecret);
        if ($result['success']) {
            http_response_code(200);
        } else {
            http_response_code(400);
        }
        echo json_encode($result);
    } elseif ($action === 'reject') {
        $result = rejectITUserApproval($itUserId, $reason);
        if ($result['success']) {
            http_response_code(200);
        } else {
            http_response_code(400);
        }
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
