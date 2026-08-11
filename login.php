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
$loggedInUser = '';
if (!empty($_SESSION['samlUserAttributes'])) {
    $attrs = $_SESSION['samlUserAttributes'];
    $preferredKeys = [
        'displayName', 'mail', 'email', 'userPrincipalName', 'eduPersonPrincipalName',
        'cn', 'name', 'uid',
        'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
        'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name',
        'urn:oid:0.9.2342.19200300.100.1.3', // mail OID
        'urn:oid:2.16.840.1.113730.3.1.241'  // displayName OID
    ];
    foreach ($preferredKeys as $key) {
        if (!empty($attrs[$key])) {
            $val = is_array($attrs[$key]) ? $attrs[$key][0] : $attrs[$key];
            if (!empty($val) && strlen($val) < 60) {
                $loggedInUser = trim($val);
                break;
            }
        }
    }
    // Scan all attribute values for any valid email string if preferred keys didn't match
    if (empty($loggedInUser)) {
        foreach ($attrs as $k => $v) {
            $vals = is_array($v) ? $v : [$v];
            foreach ($vals as $val) {
                if (is_string($val) && strpos($val, '@') !== false && strlen($val) < 60) {
                    $loggedInUser = trim($val);
                    break 2;
                }
            }
        }
    }
}

// Fallback: if samlUser is short (e.g. username), use it; if long opaque hash, default to 'Authenticated User'
if (empty($loggedInUser) && !empty($_SESSION['samlUser'])) {
    $rawUser = trim($_SESSION['samlUser']);
    if (strlen($rawUser) <= 30) {
        $loggedInUser = $rawUser;
    } else {
        $loggedInUser = 'Authenticated User';
    }
}
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



