<?php
/**
 * Authentication and Authorization Guard
 * Included by application entry points (index.php, process_barcodes.php, API scripts)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isAuthenticated = isset($_SESSION['auth_status']) && $_SESSION['auth_status'] === true && !empty($_SESSION['samlUser']);

if (!$isAuthenticated) {
    $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $isApiRequest = in_array($scriptName, ['almaLocationsAPI.php', 'almaBarcodeAPI.php', 'getProgress.php'], true)
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
