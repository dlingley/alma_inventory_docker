<?php
/**
 * SAML 2.0 Settings configuration builder for onelogin/php-saml.
 * All configuration parameters are retrieved from environment variables (getenv()).
 */

function resolveFilePath($path) {
    if (empty($path)) return '';
    if (file_exists($path)) return $path;
    $relative = __DIR__ . '/' . ltrim($path, '/');
    if (file_exists($relative)) return $relative;
    $basename = __DIR__ . '/keys/' . basename($path);
    if (file_exists($basename)) return $basename;
    return '';
}

function cleanCertString($certStr) {
    if (empty($certStr)) return '';
    // Strip headers, footers, newlines, carriage returns, and spaces
    $cleaned = preg_replace('/(-----(BEGIN|END) (CERTIFICATE|RSA PRIVATE KEY|PRIVATE KEY)-----|\s+)/', '', $certStr);
    return trim($cleaned);
}

function getSamlSettings() {
    // Handle reverse proxy TLS termination (e.g. Traefik in Kubernetes)
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SERVER_PORT'] = 443;
    }
    if (class_exists('OneLogin\Saml2\Utils')) {
        \OneLogin\Saml2\Utils::setProxyVars(true);
    }

    $spEntityId = getenv('SAML_SP_ENTITY_ID') ?: 'https://localhost:8443/saml/metadata';
    $spAcsUrl   = getenv('SAML_SP_ACS_URL') ?: 'https://localhost:8443/saml/acs';
    
    $idpEntityId = getenv('SAML_IDP_ENTITY_ID') ?: 'https://idp.purdue.edu/entity';
    $idpSsoUrl   = getenv('SAML_IDP_SSO_URL') ?: 'https://login.openathens.net/saml/2/sso/purdue.edu';
    
    $certEnvPath = getenv('SAML_IDP_CERT_PATH') ?: '/srv/app/keys/idp.crt';
    $certPath = resolveFilePath($certEnvPath);
    $idpCert = '';
    if (!empty($certPath) && file_exists($certPath)) {
        $idpCert = cleanCertString(file_get_contents($certPath));
    }

    $spCertEnvPath = getenv('SAML_SP_CERT_PATH') ?: '/srv/app/keys/sp.crt';
    $spKeyEnvPath  = getenv('SAML_SP_KEY_PATH') ?: '/srv/app/keys/sp.key';
    
    $spCertPath = resolveFilePath($spCertEnvPath);
    $spKeyPath  = resolveFilePath($spKeyEnvPath);

    $spCert = (!empty($spCertPath) && file_exists($spCertPath)) ? cleanCertString(file_get_contents($spCertPath)) : '';
    $spKey  = (!empty($spKeyPath) && file_exists($spKeyPath))   ? file_get_contents($spKeyPath)  : '';

    return [
        'strict' => true,
        'debug'  => true,
        'sp' => [
            'entityId' => $spEntityId,
            'assertionConsumerService' => [
                'url' => $spAcsUrl,
                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
            ],
            'singleLogoutService' => [
                'url' => preg_replace('#/acs/?$#', '/logout', $spAcsUrl),
                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            ],
            'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',
            'x509cert' => $spCert,
            'privateKey' => $spKey,
        ],
        'idp' => [
            'entityId' => $idpEntityId,
            'singleSignOnService' => [
                'url' => $idpSsoUrl,
                'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            ],
            'x509cert' => $idpCert,
        ],
        'security' => [
            'nameIdEncrypted' => false,
            'authnRequestsSigned' => !empty($spKey),
            'logoutRequestSigned' => false,
            'logoutResponseSigned' => false,
            'signMetadata' => false,
            // These validate the IdP's response signature against idp.crt.
            // They are independent of whether the SP has its own signing key.
            'wantMessagesSigned' => true,
            'wantAssertionsSigned' => true,
            'wantNameId' => true,
            'wantNameIdEncrypted' => false,
            'requestedAuthnContext' => false,
        ],
    ];
}
