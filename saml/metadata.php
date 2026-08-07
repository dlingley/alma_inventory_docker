<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../saml_settings.php';

use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Error;

try {
    $settings = getSamlSettings();
    $auth = new Auth($settings);
    $samlSettings = $auth->getSettings();
    $metadata = $samlSettings->getSPMetadata();
    $errors = $samlSettings->validateMetadata($metadata);

    if (empty($errors)) {
        header('Content-Type: application/xml; charset=UTF-8');
        echo $metadata;
    } else {
        throw new Error(
            'Invalid SP metadata: ' . implode(', ', $errors),
            Error::METADATA_INVALID
        );
    }
} catch (Exception $e) {
    http_response_code(500);
    echo 'Error generating SAML metadata: ' . htmlspecialchars($e->getMessage());
}
