<?php

/**
 * SAML 2.0 IdP configuration for SimpleSAMLphp.
 *
 * See: https://simplesamlphp.org/docs/stable/simplesamlphp-reference-idp-hosted
 */

$metadata['https://localhost'] = [
    /*
     * The hostname of the server (VHOST) that will use this SAML entity.
     *
     * Can be '__DEFAULT__', to use this entry by default.
     */
    'host' => '__DEFAULT__',

    // X.509 key and certificate. Relative to the cert directory.
    'privatekey' => 'localhost.key',
    'certificate' => 'localhost.cer',

    'contacts' => [
        [
            'contactType'       => 'support',
            'emailAddress'      => 'suporte@uab.pt',
            'company'           => 'Universidade Aberta',
        ],
    ],
    'OrganizationName' => [
        'en' => 'Universidade Aberta',
        'pt' => 'Universidade Aberta',
    ],
    'OrganizationURL' => [
        'en' => 'https://www.uab.pt',
        'pt' => 'https://www.uab.pt',
    ],
    
    /*
     * Authentication source to use. Must be one that is configured in
     * 'config/authsources.php'.
     */
    'auth' => 'UAb-multi',

    'attributes.NameFormat' => 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
    'authproc' => [
        
        // Convert LDAP names to oids.
        100 => ['class' => 'core:AttributeMap', 'name2oid'],

        // 102 => [
        //     'class' => 'webauthn:WebAuthn',
        //     'store'	=> [
        //         'webauthn:Database', 
        //         'database.dsn' => 'mysql:host=localhost;dbname=app_db',
        //         'database.username' => 'app_db_user',
        //         'database.password' => 'app_db_pwd',
        //     ],
        //     'options' => array(
        //         'attestation_conveyance_preference' => 'none',
        //         'authenticator_attachment' => 'cross-platform',
        //         'user_verification' => 'discouraged',
        //         'timeout' => 30000,
        //         'rp_name' => 'My Service',
        //         'rp_id' => 'https://myservice.example.com',

        //         'default_enable'=>true,
        //     ),
    ],
    
    /*
     * Uncomment the following to specify the registration information in the
     * exported metadata. Refer to:
     * http://docs.oasis-open.org/security/saml/Post2.0/saml-metadata-rpi/v1.0/cs01/saml-metadata-rpi-v1.0-cs01.html
     * for more information.
     */
    /*
    'RegistrationInfo' => [
        'authority' => 'urn:mace:example.org',
        'instant' => '2008-01-17T11:28:03Z',
        'policies' => [
            'en' => 'http://example.org/policy',
            'es' => 'http://example.org/politica',
        ],
    ],
    */
];
