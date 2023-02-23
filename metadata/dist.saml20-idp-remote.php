<?php

/**
 * SAML 2.0 remote IdP metadata for SimpleSAMLphp.
 *
 * Remember to remove the IdPs you don't use from this file.
 *
 * See: https://simplesamlphp.org/docs/stable/simplesamlphp-reference-idp-remote
 */
// $metadata['https://login.uab.pt'] = [
//     'SingleSignOnService'  => 'https://login.uab.pt/auth/v1/saml2/idp/SSOService.php',
//     'SingleLogoutService'  => 'https://login.uab.pt/auth/v1/saml2/idp/SingleLogoutService.php',
//     'certificate'          => 'login.uab.pt.crt',
// ];

$metadata['https://autenticacao.cartaodecidadao.pt'] = [
    'SingleSignOnService'  => [
        // [
        //     'index' => 1,
        //     'isDefault' => true,
        //     'Location' => 'https://autenticacao.gov.pt/fa/Default.aspx',
        //     'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
        // ],
        [
            'index' => 2,
            'isDefault' => false,
            'Location' => 'https://preprod.autenticacao.gov.pt/fa/Default.aspx',
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
        ],
    ],//'http://localhost:8000/',
    'SingleLogoutService'  => [
        // [
        //     'index' => 1,
        //     'isDefault' => true,
        //     'Location' => 'https://autenticacao.gov.pt/fa/Default.aspx',
        //     'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
        // ],
        [
            'index' => 2,
            'isDefault' => false,
            'Location' => 'https://preprod.autenticacao.gov.pt/fa/Default.aspx',
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
        ],
    ],
    'certificate'          => 'idp.saml.preprod.autenticacao.gov.pt.crt',
];


$metadata['https://localhost'] = [
    'SingleSignOnService'  => [
        [
            'index' => 1,
            'isDefault' => true,
            'Location' => 'http://localhost:8000',
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
        ],
    ],
    'SingleLogoutService'  => [
        [
            'index' => 1,
            'isDefault' => true,
            'Location' => 'http://localhost:8000',
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
        ],
    ],
    'certificate'          => 'localhost.cer',
];