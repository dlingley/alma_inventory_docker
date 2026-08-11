<?php
/**
 * Admin API Endpoint for managing allowed users list
 * Protected by login.php + Superadmin authorization check.
 */

require_once __DIR__ . '/login.php';

header('Content-Type: application/json');

if (!isset($isSuperAdmin) || $isSuperAdmin !== true) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Forbidden',
        'message' => 'Superadmin privileges required.'
    ]);
    exit;
}

$action = $_REQUEST['action'] ?? 'list';

switch ($action) {
    case 'list':
        $usersMap = getAllowedUsersMap();
        $usersList = array_values($usersMap);
        echo json_encode([
            'success' => true,
            'users' => $usersList
        ]);
        break;

    case 'add':
        $identifier = trim($_POST['identifier'] ?? '');
        $role = trim($_POST['role'] ?? 'user');

        if (empty($identifier)) {
            echo json_encode(['success' => false, 'error' => 'User identifier is required.']);
            exit;
        }

        if (addUserToOverlay($identifier, $role)) {
            echo json_encode([
                'success' => true,
                'message' => "User {$identifier} added successfully.",
                'users' => array_values(getAllowedUsersMap())
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to add user.']);
        }
        break;

    case 'remove':
        $identifier = trim($_POST['identifier'] ?? '');

        if (empty($identifier)) {
            echo json_encode(['success' => false, 'error' => 'User identifier is required.']);
            exit;
        }

        if (removeUserFromOverlay($identifier)) {
            echo json_encode([
                'success' => true,
                'message' => "User {$identifier} removed successfully.",
                'users' => array_values(getAllowedUsersMap())
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to remove user.']);
        }
        break;

    case 'reset':
        if (resetUsersOverlay()) {
            echo json_encode([
                'success' => true,
                'message' => 'Reset to ConfigMap defaults successfully.',
                'users' => array_values(getAllowedUsersMap())
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to reset users.']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action.']);
        break;
}
