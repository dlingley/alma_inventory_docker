<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../saml_settings.php';

use OneLogin\Saml2\Auth;

try {
    $settings = getSamlSettings();
    $auth = new Auth($settings);
    
    $auth->processResponse();
    
    $errors = $auth->getErrors();
    if (!empty($errors)) {
        error_log('SAML ACS Errors: ' . implode(', ', $errors));
        error_log('SAML Last Error Reason: ' . $auth->getLastErrorReason());
        header('Location: ../noaccess.php');
        exit;
    }

    if (!$auth->isAuthenticated()) {
        error_log('SAML ACS: Not authenticated.');
        header('Location: ../noaccess.php');
        exit;
    }

    $nameId = $auth->getNameId();
    $attributes = $auth->getAttributes();

    // Read allow-list file (Fail Closed)
    $allowListPath = getenv('ALLOWED_USERS_FILE') ?: '/srv/app/allowed_users.txt';
    $isAuthorized = false;

    if (file_exists($allowListPath) && is_readable($allowListPath)) {
        $lines = file($allowListPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false && count($lines) > 0) {
            $allowedUsers = array_map(function($line) {
                return strtolower(trim($line));
            }, $lines);

            // Candidate identifiers to match
            $candidates = [strtolower(trim($nameId))];

            // Add attributes (email, mail, eduPersonPrincipalName, uid, employeeID, etc.)
            foreach ($attributes as $attrKey => $attrValues) {
                if (is_array($attrValues)) {
                    foreach ($attrValues as $val) {
                        $candidates[] = strtolower(trim($val));
                    }
                } elseif (is_string($attrValues)) {
                    $candidates[] = strtolower(trim($attrValues));
                }
            }

            foreach ($candidates as $candidate) {
                if (!empty($candidate) && in_array($candidate, $allowedUsers, true)) {
                    $isAuthorized = true;
                    break;
                }
            }
        } else {
            error_log('SAML Auth Authorization: Allow-list file is empty. Failing closed.');
        }
    } else {
        error_log('SAML Auth Authorization: Allow-list file missing or unreadable at ' . $allowListPath . '. Failing closed.');
    }

    if ($isAuthorized) {
        $_SESSION['samlUser'] = $nameId;
        $_SESSION['samlUserAttributes'] = $attributes;
        $_SESSION['auth_status'] = true;

        $redirectTo = '../index.php';
        if (isset($_POST['RelayState']) && !empty($_POST['RelayState'])) {
            $relay = $_POST['RelayState'];
            // Validate RelayState to avoid open redirect vulnerabilities
            // Only allow relative paths (starting with /) to stay on the same host
            if (preg_match('#^/([a-zA-Z0-9_/-]*\.?[a-zA-Z0-9_-]*)?(\?.*)?$#', $relay)) {
                $redirectTo = $relay;
            }
        }

        header('Location: ' . $redirectTo);
        exit;
    } else {
        error_log('SAML Auth Authorization Failed. NameID: ' . $nameId . ' | Checked candidates: ' . implode(', ', array_unique($candidates)) . ' | Attributes: ' . json_encode($attributes));
        $_SESSION['auth_status'] = false;
        $_SESSION['denied_user'] = $nameId;
        header('Location: ../noaccess.php');
        exit;
    }
} catch (Exception $e) {
    error_log('SAML ACS Exception: ' . $e->getMessage());
    header('Location: ../noaccess.php');
    exit;
}
