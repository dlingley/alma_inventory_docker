<?php
/**
 * SAML 2.0 Settings configuration builder for onelogin/php-saml.
 * All configuration parameters are retrieved from environment variables (getenv()).
 */

function getSamlSettings() {
    $spEntityId = getenv('SAML_SP_ENTITY_ID') ?: 'http://localhost:8080/saml/metadata';
    $spAcsUrl   = getenv('SAML_SP_ACS_URL') ?: 'http://localhost:8080/saml/acs';
    
    $idpEntityId = getenv('SAML_IDP_ENTITY_ID') ?: 'https://idp.purdue.edu/entity';
    $idpSsoUrl   = getenv('SAML_IDP_SSO_URL') ?: 'https://login.openathens.net/saml/2/sso/purdue.edu';
    
    $certPath = getenv('SAML_IDP_CERT_PATH') ?: '/srv/app/keys/idp.crt';
    $idpCert = '';
    if (file_exists($certPath)) {
        $idpCert = file_get_contents($certPath);
    }

    $spCertPath = getenv('SAML_SP_CERT_PATH') ?: '/srv/app/keys/sp.crt';
    $spKeyPath  = getenv('SAML_SP_KEY_PATH') ?: '/srv/app/keys/sp.key';
    
    $spCert = file_exists($spCertPath) ? file_get_contents($spCertPath) : '';
    $spKey  = file_exists($spKeyPath)  ? file_get_contents($spKeyPath)  : '';

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
            'wantMessagesSigned' => true,
            'wantAssertionsSigned' => true,
            'wantNameId' => true,
            'wantNameIdEncrypted' => false,
            'requestedAuthnContext' => false,
        ],
    ];
}
