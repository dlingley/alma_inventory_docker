<?php
/**
 * Authentication and Authorization Guard
 * Included by application entry points (index.php, process_barcodes.php, API scripts)
 */

require_once __DIR__ . '/user_manager.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAuthenticated = isset($_SESSION['auth_status']) && $_SESSION['auth_status'] === true && !empty($_SESSION['samlUser']);

if (!$isAuthenticated) {
    $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $isApiRequest = in_array($scriptName, ['almaLocationsAPI.php', 'almaBarcodeAPI.php', 'getProgress.php', 'adminUsersAPI.php', 'runHistoryAPI.php', 'adminCacheAPI.php'], true)
        || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
        || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

    if ($isApiRequest) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Unauthorized',
            'message' => 'Authentication required'
        ]);
        exit;
    } else {
        $returnTo = $_SERVER['REQUEST_URI'] ?? '/index.php';
        header('Location: /saml/login?returnTo=' . urlencode($returnTo));
        exit;
    }
}

// User is authenticated and authorized; execution continues cleanly.
$loggedInUser = getDisplayUserFromSaml($_SESSION['samlUser'] ?? '', $_SESSION['samlUserAttributes'] ?? []);
if (empty($loggedInUser)) {
    $loggedInUser = 'Authenticated User';
}

// Compute Superadmin status for session
$candidates = [$_SESSION['samlUser'] ?? ''];
if (!empty($_SESSION['samlUserAttributes']) && is_array($_SESSION['samlUserAttributes'])) {
    foreach ($_SESSION['samlUserAttributes'] as $attrKey => $attrValues) {
        if (is_array($attrValues)) {
            foreach ($attrValues as $val) {
                $candidates[] = $val;
            }
        } elseif (is_string($attrValues)) {
            $candidates[] = $attrValues;
        }
    }
}
$isSuperAdmin = isUserAdmin($candidates);

// Release PHP session lock so concurrent AJAX requests (like getProgress.php) aren't blocked by long-running processes
session_write_close();



