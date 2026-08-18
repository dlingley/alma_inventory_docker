<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../saml_settings.php';

use OneLogin\Saml2\Auth;

try {
    // Auto-capture OpenAthens IdP certificate from SAMLResponse XML in local dev mode
    if (isset($_POST['SAMLResponse']) && getenv('SAML_WANT_ASSERTIONS_SIGNED') === 'false') {
        $rawXml = base64_decode($_POST['SAMLResponse']);
        if (preg_match('/<(?:\w+:)?X509Certificate>([^<]+)<\/(?:\w+:)?X509Certificate>/i', $rawXml, $m)) {
            $extractedCert = trim($m[1]);
            if (!empty($extractedCert)) {
                $formattedCert = "-----BEGIN CERTIFICATE-----\n" . chunk_split($extractedCert, 64, "\n") . "-----END CERTIFICATE-----\n";
                $idpCertFile = __DIR__ . '/../keys/idp.crt';
                @mkdir(dirname($idpCertFile), 0775, true);
                if (!file_exists($idpCertFile) || filesize($idpCertFile) < 100 || strpos(file_get_contents($idpCertFile), 'localhost') !== false) {
                    file_put_contents($idpCertFile, $formattedCert);
                }
            }
        }
    }

    $settings = getSamlSettings();
    $auth = new Auth($settings);
    
    $auth->processResponse();
    
    $errors = $auth->getErrors();
    $lastError = $auth->getLastErrorReason();

    error_log('SAML ACS Debug - errors: ' . json_encode($errors) . ' | lastError: ' . $lastError . ' | authenticated: ' . ($auth->isAuthenticated() ? 'true' : 'false'));

    $nameId = $auth->getNameId();
    $attributes = $auth->getAttributes() ?: $auth->getAttributesWithFriendlyName() ?: [];

    error_log('SAML ACS Success - NameID: ' . $nameId . ' | Attributes: ' . json_encode($attributes));

    require_once __DIR__ . '/../user_manager.php';

    // Candidate identifiers to match
    $candidates = [];
    if (!empty($nameId)) {
        $candidates[] = strtolower(trim($nameId));
    }
    if (is_array($attributes)) {
        foreach ($attributes as $attrKey => $attrValues) {
            if (is_array($attrValues)) {
                foreach ($attrValues as $val) {
                    if (is_string($val) && !empty(trim($val))) {
                        $candidates[] = strtolower(trim($val));
                    }
                }
            } elseif (is_string($attrValues) && !empty(trim($attrValues))) {
                $candidates[] = strtolower(trim($attrValues));
            }
        }
    }

    // In local dev mode with EXTRA_ALLOWED_USERS, include those identifiers if authenticated
    $extraDevUsers = getenv('EXTRA_ALLOWED_USERS');
    if (!empty($extraDevUsers) && $auth->isAuthenticated()) {
        foreach (explode(',', $extraDevUsers) as $devUser) {
            $parts = explode(':', trim($devUser));
            if (!empty($parts[0])) {
                $candidates[] = strtolower(trim($parts[0]));
            }
        }
    }

    $candidates = array_unique($candidates);
    error_log('SAML ACS - Final candidates evaluated: ' . implode(', ', $candidates));

    $isAuthorized = isUserAllowed($candidates);

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
        $displayUser = getDisplayUserFromSaml($nameId, $attributes);
        if (empty($displayUser) && !empty($nameId)) {
            $displayUser = $nameId;
        }
        error_log('SAML Auth Authorization Failed. NameID: ' . $nameId . ' | DisplayUser: ' . $displayUser . ' | Checked candidates: ' . implode(', ', array_unique($candidates)) . ' | Attributes: ' . json_encode($attributes));
        $_SESSION['auth_status'] = false;
        $_SESSION['denied_user'] = $displayUser;
        $_SESSION['denied_attributes'] = $attributes;
        header('Location: ../noaccess.php');
        exit;
    }
} catch (Exception $e) {
    error_log('SAML ACS Exception: ' . $e->getMessage());
    $_SESSION['auth_status'] = false;
    header('Location: ../noaccess.php');
    exit;
}
