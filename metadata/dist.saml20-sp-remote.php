<?php
/**
 * SAML 2.0 remote SP metadata for SimpleSAMLphp.
 *
 * See: https://simplesamlphp.org/docs/stable/simplesamlphp-reference-sp-remote
 */

$metadata['https://github.com/orgs/universidade-aberta'] = [
    'entityid' => 'https://login.uab.pt',
    'audience' => ['https://github.com/orgs/universidade-aberta'],
    'AssertionConsumerService' => [
        [
            'index' => 1,
            'isDefault' => true,
            'Location' =>
                'https://github.com/orgs/universidade-aberta/saml/consume',
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
        ],
    ],

    'NameIDFormat' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent',
    'authproc' => [
        1 => [
            'class' => 'saml:AttributeNameID',
            'identifyingAttribute' => 'sAMAccountName',
            'Format' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent',
        ],
        50 => [
            'class' => 'core:AttributeMap',
            'mail' => 'emails',
            'displayName' => 'full_name',
        ],
    ],
    'attributes' => ['sAMAccountName', 'emails', 'full_name'],
    'simplesaml.attributes' => false,
    'signature.certificate' => 'login.uab.pt.cer',
];

/**
 * Moodle with the SAML plugin (https://moodle.org/plugins/auth_saml2).
 */
$metadata['https://localhost:4443/auth/saml2/sp/metadata.php'] = [
    'entityid' => 'https://login.uab.pt',
    'AssertionConsumerService' => [
        [
            'index' => 1,
            'isDefault' => true,
            'Location' =>
                'https://localhost:4443/auth/saml2/sp/saml2-acs.php/localhost',
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
        ],
    ],
    'SingleLogoutService' => [
        [
            'index' => 1,
            'isDefault' => true,
            'Location' =>
                'https://localhost:4443/auth/saml2/sp/saml2-logout.php/localhost',
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
        ],
    ],

    'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',
    'authproc' => [
        1 => [
            'class' => 'saml:AttributeNameID',
            'identifyingAttribute' => 'sAMAccountName',
            'Format' => 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent',
        ],
    ],
    'attributes' => [
        'sAMAccountName',
        'mail',
        'displayName',
        'givenName',
        'sn',
    ],
    'simplesaml.attributes' => true,
    'signature.certificate' => 'login.uab.pt.cer',
    'certificate' => 'localhost_saml.crt',
];
