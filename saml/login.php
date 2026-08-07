<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../saml_settings.php';

use OneLogin\Saml2\Auth;

try {
    $settings = getSamlSettings();
    $auth = new Auth($settings);
    
    $returnTo = null;
    if (isset($_GET['returnTo']) && !empty($_GET['returnTo'])) {
        $returnTo = $_GET['returnTo'];
    } elseif (isset($_REQUEST['RelayState']) && !empty($_REQUEST['RelayState'])) {
        $returnTo = $_REQUEST['RelayState'];
    }
    
    $auth->login($returnTo);
} catch (Exception $e) {
    http_response_code(500);
    echo 'SAML Login Error: ' . htmlspecialchars($e->getMessage());
}
