<?php

$config = [
    /*
     * When multiple authentication sources are defined, you can specify one to use by default
     * in order to authenticate users. In order to do that, you just need to name it "default"
     * here. That authentication source will be used by default then when a user reaches the
     * SimpleSAMLphp installation from the web browser, without passing through the API.
     *
     * If you already have named your auth source with a different name, you don't need to change
     * it in order to use it as a default. Just create an alias by the end of this file:
     *
     * $config['default'] = &$config['your_auth_source'];
     */

     'UAb-multi' => [
        'uab:MultiAuth',
  
        /*
         * The available authentication sources.
         * They must be defined in this authsources.php file.
         */
        'sources' => [
            'uab-ldap' => [
                'text' => [
                    'en' => 'UAb Credentials',
                    'pt' => 'Credenciais UAb',
                ],
                'help' => [
                    'en' => 'Standard authentication for Universidade Aberta user accounts',
                    'pt' => 'Método de autenticação predefinido para início de sessão com contas de utilizador da Universidade Aberta',
                ],
                'css-class' => 'uab-auth',
            ],
            // 'autenticacao-gov-pt' => [
            //     'text' => [
            //         'en' => 'Autenticação.Gov',
            //         'pt' => 'Autenticação.Gov',
            //     ],
            //     'help' => [
            //         'en' => 'Uses the Autenticação.gov identity provider, external site, to authenticate in Universidade Aberta with the corresponding user account',
            //         'pt' => 'Utiliza o serviço Autenticação.gov, site externo, para autenticar o utilizador na Universidade Aberta com recurso ao cartão de cidadão, chave móvel e outros métodos de autenticação aplicáveis',
            //     ],
            //     'css-class' => 'gov-auth',
            // ],
            'localhost' => [
                'text' => [
                    'en' => 'localhost',
                    'pt' => 'localhost',
                ],
                'css-class' => 'localhost',
            ],
        ],
        'preselect' => 'uab-ldap',

        'uab.profile.edit.source' => 'uab-ldap',
        'uab.profile.edit.enabled' => false,
        'uab.profile.attributes' => [
            'sAMAccountName'=>[
                'label'=>[
                    'en' => 'Username',
                    'pt' => 'Nome de utilizador',
                ],
            ],
            'password'=>[
                'label'=>[
                    'en' => 'Password',
                    'pt' => 'Palavra-passe',
                ],
                'edit'=>[
                    'allow'=>true,
                    'required'=>false,
                    'key'=>'password',
                    'type' => 'password',
                    'htmlType' => 'password',
                    'classes' => '',
                    'htmlAttributes' => [],
                    'requireAuthForUpdate' => true,
                    'serverInputValidation'=>[
                        'filter' => FILTER_CALLBACK,
                        'flags' => FILTER_REQUIRE_ARRAY, 
                        'options'   => (function(int $minLength=8){
                            return function($value) use ($minLength){ 
                                $results = [];
                                // The password must have alphanumeric characters, symbols and with a minimum length of 8 chars
                                if(\preg_match("/(?=(?'alphanum'.*[A-Za-z0-9]))(?=(?'symbols'.*[^A-Za-z0-9]))(?=(?'length'.{".\preg_quote($minLength).",}))/u", $value, $results)):
                                    return $value;
                                endif;

                                $matchGroupsErrors = [
                                    'alphanum'=>[
                                        'en'=>'No alphanumeric characters where provided',
                                        'pt'=>'Não foram fornecidos carateres alfa-numéricos',
                                    ],
                                    'symbols'=>[
                                        'en'=>'No symbol characters where provided',
                                        'pt'=>'Não foram fornecidos carateres especiais',
                                    ],
                                    'length'=>[
                                        'en'=>'The minimum password length was not achieved',
                                        'pt'=>'O tamanho mínimo da palavra-passe não foi atingido',
                                    ]
                                ];
                                $errors = array_diff_key($matchGroupsErrors, $results);

                                if(!empty($errors)):
                                    throw new class($errors) extends \Exception{
                                        protected $errors = [];
                                        public function __construct(array $errors, $message='', $code = 0, \Exception $previous = null) {
                                            $this->errors = $errors;
                                            parent::__construct($message, $code, $previous);
                                        }

                                        public function getErrors():array{
                                            return $this->errors;
                                        }
                                    };
                                endif;

                                // Should not happen, but probably it will ;-)
                                return $value;
                            };
                        })(8),
                    ],
                    'minLength' => 8,
                ],
                'view'=>[
                    'allow'=>false,
                ],
            ],
            'mail'=>[
                'label'=>[
                    'en' => 'Email',
                    'pt' => 'E-mail',
                ],
            ], 
            'givenName'=>[
                'label'=>[
                    'en' => 'First Name',
                    'pt' => 'Primeiro nome',
                ],
            ], 
            'sn'=>[
                'label'=>[
                    'en' => 'Last Name',
                    'pt' => 'Último nome',
                ],
            ], 
            'displayName'=>[
                'label'=>[
                    'en' => 'Display Name',
                    'pt' => 'Nome de apresentação',
                ],
            ], 
            'AccountRecoveryEmail'=>[
                'label'=>[
                    'en' => 'Recovery Email',
                    'pt' => 'E-mail de recuperação',
                ],
                'edit'=>[
                    'allow'=>true,
                    'required'=>false,
                    'key'=>'extensionAttribute5',
                    'type' => 'text',
                    'htmlType' => 'email',
                    'classes' => '',
                    'htmlAttributes' => [],
                    'requireAuthForUpdate' => true,
                    'serverInputValidation'=>[
                        'filter' => FILTER_VALIDATE_EMAIL,
                        'flags' => FILTER_REQUIRE_ARRAY, 
                        'options'   => [
                            'default' => '',
                        ],
                    ],
                ],
            ], 
            'jpegPhoto'=>[
                'label'=>[
                    'en' => 'Photo',
                    'pt' => 'Foto',
                ],
                'edit'=>[
                    'allow'=>false,
                    'key'=>'jpegPhoto',
                ],
                'view'=>[
                    'allow'=>false,
                ],
            ],
        ],
        'uab.admin.links' => [
            'portal'=>[
                'label'=>[
                    'en' => 'Portal',
                    'pt' => 'Portal',
                ],
                'icon'=>'fa-university',
                'href'=>[
                    'en' => 'https://portal.uab.pt/?lang=en',
                    'pt' => 'https://portal.uab.pt/?lang=pt',
                ],
                'target'=>'_blank',
                'rel'=>'noopener',
                'accesskey'=>'p',
            ],
            'intranet'=>[
                'label'=>[
                    'en' => 'Intranet',
                    'pt' => 'Intranet',
                ],
                'icon'=>'fa-network-wired',
                'href'=>[
                    'en' => 'https://intranet.uab.pt',
                    'pt' => 'https://intranet.uab.pt',
                ],
                'target'=>'_blank',
                'rel'=>'noopener',
                'accesskey'=>'i',
            ],
            'help'=>[
                'label'=>[
                    'en' => 'Help',
                    'pt' => 'Ajuda',
                ],
                'icon'=>'fa-life-ring',
                'href'=>[
                    'en' => 'https://portal.uab.pt/si/',
                    'pt' => 'https://portal.uab.pt/si/',
                ],
                'target'=>'_blank',
                'rel'=>'noopener',
                'accesskey'=>'i',
            ],
        ],

        'uid.attribute' => 'sAMAccountName',
        'uid.name' => function($attributes){ // Compose a name for the user using 'givenName', 'sn' and 'displayName' (as a fallback)
            $attributes = array_map(function($attribute){
                return is_array($attribute)?implode(',', $attribute):(is_scalar($attribute)?$attribute:null);
            }, $attributes);

            $names = [];
            foreach(['givenName', 'sn', 'displayName'] as $nameKey):
                if((!empty($attributes[$nameKey]) and $nameKey!=='displayName') or empty($names)):
                    $names[]=$attributes[$nameKey];
                endif;
            endforeach;
            return implode(' ', $names);
        },
    ],

    // This is a authentication source which handles admin authentication.
    'admin' => [
        // The default is to use core:AdminPassword, but it can be replaced with
        // any authentication source.

        'core:AdminPassword',
    ],

    // // An authentication source which can authenticate against SAML 2.0 IdPs.
    // 'autenticacao-gov-pt' => [
    //     //'saml:SP',
    //     'uab:SP',

    //     // The entity ID of this SP.
    //     'entityID' => 'https://login.uab.pt',

    //     // The entity ID of the IdP this SP should contact.
    //     // Can be NULL/unset, in which case the user will be shown a list of available IdPs.
    //     'idp' => 'https://autenticacao.cartaodecidadao.pt',

    //     // The URL to the discovery service.
    //     // Can be NULL/unset, in which case a builtin discovery service will be used.
    //     'discoURL' => null,

    //     /*
    //      * If SP behind the SimpleSAMLphp in IdP/SP proxy mode requests
    //      * AuthnContextClassRef, decide whether the AuthnContextClassRef will be
    //      * processed by the IdP/SP proxy or if it will be passed to the original
    //      * IdP in front of the IdP/SP proxy.
    //      */
    //     'proxymode.passAuthnContextClassRef' => false,

    //     'name' => [
    //         'en' => '(PT) Citizen Card',
    //         'pt' => 'Cartão do Cidadão',
    //     ],
    //     'OrganizationName' => [
    //         'en' => 'Universidade Aberta',
    //         'no' => 'Universidade Aberta',
    //     ],
    //     'OrganizationDisplayName' => [
    //         'en' => 'Universidade Aberta',
    //         'no' => 'Universidade Aberta',
    //     ],
    //     'OrganizationURL' => 'https://www.uab.pt',

    //     'acs.Bindings' => [
    //         'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
    //         'urn:oasis:names:tc:SAML:1.0:profiles:browser-post',
    //     ],

    //     'ForceAuthn' => true,
    //     'IsPassive' => false,
    //     'ProviderName' => 'Universidade Aberta',
    //     'certificate' => 'login.uab.pt.cer',
    //     'privatekey' => 'login.uab.pt.key',
    //     //'privatekey_pass' => '',
    //     'sign.authnrequest' => true,
    //     'redirect.sign' => true,
    //     'redirect.validate' => true,
    //     'WantAssertionsSigned' => true,


    //     'AllowCreate' => false,
        
    //     /*
    //      * The attributes parameter must contain an array of desired attributes by the SP.
    //      * The attributes can be expressed as an array of names or as an associative array
    //      * in the form of 'friendlyName' => 'name'. This feature requires 'name' to be set.
    //      * The metadata will then be created as follows:
    //      * <md:RequestedAttribute FriendlyName="friendlyName" Name="name" />
    //      */

    //     //'saml:Extensions' => $ext,

    //     'RequestAttributes' => [
    //         'namespace'=>'http://autenticacao.cartaodecidadao.pt/atributos',
    //         'attributes'=>[
    //             'NIF' => [
    //                 'Name' => 'http://interop.gov.pt/MDC/Cidadao/NIF',
    //                 'NameFormat' => 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
    //                 'Required' => true,
    //             ],
    //             'NomeCompleto' => [
    //                 'Name' => 'http://interop.gov.pt/MDC/Cidadao/NomeCompleto',
    //                 'NameFormat' => 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
    //                 'Required' => true,
    //             ],
    //             // 'NIC' => [
    //             //     'Name' => 'http://interop.gov.pt/MDC/Cidadao/NIC',
    //             //     'NameFormat' => 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
    //             //     'Required' => false,
    //             // ],
    //             'Foto' => [
    //                 'Name' => 'http://interop.gov.pt/MDC/Cidadao/Foto',
    //                 'NameFormat' => 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
    //                 'Required' => false,
    //             ],
    //             // 'Morada' => [
    //             //     'Name' => 'http://interop.gov.pt/MDC/Cidadao/Morada',
    //             //     'NameFormat' => 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
    //             //     'Required' => false,
    //             // ],
    //         ]
    //     ],

    //     'FAAALevel'=>[
    //         'namespace'=>'http://autenticacao.cartaodecidadao.pt/atributos',
    //         'value'=>3,
    //     ],

    //     'AssertionConsumerService' => 'https://preprod.autenticacao.gov.pt/fa/Default.aspx',
    //     'SingleLogoutService' => 'https://preprod.autenticacao.gov.pt/fa/Default.aspx',

    //     'authproc' => [

    //         10=> [
    //             'class' => 'core:AttributeMap',
    //             'http://interop.gov.pt/MDC/Cidadao/NIF' => 'NIF',
    //             'http://interop.gov.pt/MDC/Cidadao/NIC' => 'NIC',
    //             'http://interop.gov.pt/MDC/Cidadao/NomeCompleto' => 'fullname',
    //             'http://interop.gov.pt/MDC/Cidadao/Morada' => 'address',
    //             'http://interop.gov.pt/MDC/Cidadao/Foto' => 'photo',
    //         ],

    //         20=> [
    //             'class' => 'uab:UserMatch',
    //             'config'=>[
    //                 'auth_source_primary_provider_name'=>'UAb',
    //                 'auth_source_primary'=>'uab-ldap',
    //                 'auth_source_primary_match_field'=>'sAMAccountName',
    //                 'auth_source_primary_match_value'=>'',
    //                 'auth_source_secondary'=>'autenticacao-gov-pt',
    //                 'auth_source_secondary_match_field'=>'NIF',
    //                 'auth_source_secondary_match_value'=>'',

    //                 'mapping_table'=>'uab_user_attributes_matching__tbl',
    //             ]
    //         ],
    //     ],
    // ],

    'localhost' => [
        //'saml:SP',
        'uab:SP',

        // The entity ID of this SP.
        'entityID' => 'https://localhost',

        // The entity ID of the IdP this SP should contact.
        // Can be NULL/unset, in which case the user will be shown a list of available IdPs.
        'idp' => 'https://localhost:8000',

        // The URL to the discovery service.
        // Can be NULL/unset, in which case a builtin discovery service will be used.
        'discoURL' => null,

        /*
         * If SP behind the SimpleSAMLphp in IdP/SP proxy mode requests
         * AuthnContextClassRef, decide whether the AuthnContextClassRef will be
         * processed by the IdP/SP proxy or if it will be passed to the original
         * IdP in front of the IdP/SP proxy.
         */
        'proxymode.passAuthnContextClassRef' => false,

        'name' => [
            'en' => '(PT) Citizen Card',
            'pt' => 'Cartão do Cidadão',
        ],
        'OrganizationName' => [
            'en' => 'Universidade Aberta',
            'no' => 'Universidade Aberta',
        ],
        'OrganizationDisplayName' => [
            'en' => 'Universidade Aberta',
            'no' => 'Universidade Aberta',
        ],
        'OrganizationURL' => 'https://www.uab.pt',

        'acs.Bindings' => [
            'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
            'urn:oasis:names:tc:SAML:1.0:profiles:browser-post',
        ],

        'ForceAuthn' => true,
        'IsPassive' => false,
        'ProviderName' => 'Universidade Aberta',
        'certificate' => 'localhost.cer',
        'privatekey' => 'localhost.key',
        //'privatekey_pass' => '',
        'sign.authnrequest' => true,
        'redirect.sign' => true,
        'redirect.validate' => true,
        'WantAssertionsSigned' => true,


        'AllowCreate' => false,
        
        /*
         * The attributes parameter must contain an array of desired attributes by the SP.
         * The attributes can be expressed as an array of names or as an associative array
         * in the form of 'friendlyName' => 'name'. This feature requires 'name' to be set.
         * The metadata will then be created as follows:
         * <md:RequestedAttribute FriendlyName="friendlyName" Name="name" />
         */

        //'saml:Extensions' => $ext,

        'RequestAttributes' => [
            'namespace'=>'http://autenticacao.cartaodecidadao.pt/atributos',
            'attributes'=>[
                'NIF' => [
                    'Name' => 'http://interop.gov.pt/MDC/Cidadao/NIF',
                    'NameFormat' => 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
                    'Required' => true,
                ],
                'NomeCompleto' => [
                    'Name' => 'http://interop.gov.pt/MDC/Cidadao/NomeCompleto',
                    'NameFormat' => 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
                    'Required' => true,
                ],
                // 'NIC' => [
                //     'Name' => 'http://interop.gov.pt/MDC/Cidadao/NIC',
                //     'NameFormat' => 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
                //     'Required' => false,
                // ],
                'Foto' => [
                    'Name' => 'http://interop.gov.pt/MDC/Cidadao/Foto',
                    'NameFormat' => 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
                    'Required' => false,
                ],
                // 'Morada' => [
                //     'Name' => 'http://interop.gov.pt/MDC/Cidadao/Morada',
                //     'NameFormat' => 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
                //     'Required' => false,
                // ],
            ]
        ],

        'FAAALevel'=>[
            'namespace'=>'http://autenticacao.cartaodecidadao.pt/atributos',
            'value'=>3,
        ],

        'AssertionConsumerService' => 'https://preprod.autenticacao.gov.pt/fa/Default.aspx',
        'SingleLogoutService' => 'https://preprod.autenticacao.gov.pt/fa/Default.aspx',

    ],
    
    // Example of a LDAP authentication source.
    'uab-ldap' => [
        'uab:Ldap',

        /**
         * The connection string for the LDAP-server.
         * You can add multiple by separating them with a space.
         */
        'connection_string' => '[ldap://127.0.0.1]',

        /**
         * Whether SSL/TLS should be used when contacting the LDAP server.
         * Possible values are 'ssl', 'tls' or 'none'
         */
        'encryption' => 'none',

        /**
         * The LDAP version to use when interfacing the LDAP-server.
         * Defaults to 3
         */
        'version' => 3,

        /**
         * Set to TRUE to enable LDAP debug level. Passed to the LDAP connector class.
         *
         * Default: FALSE
         * Required: No
         */
        'ldap.debug' => true,

        /**
         * The LDAP-options to pass when setting up a connection
         * See [Symfony documentation][1]
         */
        'options' => [
            /**
             * Set whether to follow referrals.
             * AD Controllers may require 0x00 to function.
             * Possible values are 0x00 (NEVER), 0x01 (SEARCHING),
             *   0x02 (FINDING) or 0x03 (ALWAYS).
             */
            'referrals' => 0x00,
            'timeout' => 10,
        ],

        /**
         * The connector to use.
         * Defaults to '\SimpleSAML\Module\ldap\Connector\Ldap', but can be set
         * to '\SimpleSAML\Module\ldap\Connector\ActiveDirectory' when
         * authenticating against Microsoft Active Directory. This will
         * provide you with more specific error messages.
         */
        'connector' => '\SimpleSAML\Module\ldap\Connector\ActiveDirectory',

        /**
         * Which attributes should be retrieved from the LDAP server.
         * This can be an array of attribute names, or NULL, in which case
         * all attributes are fetched.
         */
        'attributes' => [
            'sAMAccountName', 'mail', 'givenName', 'sn', 'displayName', 'extensionAttribute5', 'jpegPhoto', 'userAccountControl', 'accountExpires', 
            //'distinguishedName', 'groups', 'member', 'memberOf', 'name','objectClass',
        ],

        /**
         * Which attributes should be base64 encoded after retrieval from
         * the LDAP server.
         */
        'attributes.binary' => [
            'jpegPhoto',
            'objectGUID',
            'objectSid',
            'mS-DS-ConsistencyGuid'
        ],

        /**
         * The pattern which should be used to create the user's DN given
         * the username. %username% in this pattern will be replaced with
         * the user's username.
         *
         * This option is not used if the search.enable option is set to TRUE.
         */
        'dnpattern' => 'uid=%username%,DC=univ-ab,DC=local',
        //'dnpattern' => '(&(objectClass=user)(objectCategory=person)(|(sAMAccountName=%username%)))',

        /**
         * As an alternative to specifying a pattern for the users DN, it is
         * possible to search for the username in a set of attributes. This is
         * enabled by this option.
         */
        'search.enable' => true,

        /**
         * An array on DNs which will be used as a base for the search. In
         * case of multiple strings, they will be searched in the order given.
         */
        'search.base' => [
            'DC=univ-ab,DC=local',
        ],

        /**
         * The scope of the search. Valid values are 'sub' and 'one' and
         * 'base', first one being the default if no value is set.
         */
        'search.scope' => 'sub',

        /**
         * The attribute(s) the username should match against.
         *
         * This is an array with one or more attribute names. Any of the
         * attributes in the array may match the value the username.
         */
        'search.attributes' => ['sAMAccountName', 'mail', /*'uid', */ ],

        /**
         * Additional filters that must match for the entire LDAP search to
         * be true.
         *
         * This should be a single string conforming to [RFC 1960][2]
         * and [RFC 2544][3]. The string is appended to the search attributes
         */
        //'search.filter' => '(&(objectClass=Person)(|(sn=Doe)(cn=John *)))',
        'search.filter' => '(&(objectClass=user)(objectCategory=person))',
        //(&(objectClass=user)(objectCategory=person)(|(sAMAccountName=%username%)))

        /**
         * The username & password where SimpleSAMLphp should bind to before
         * searching. If this is left NULL, no bind will be performed before
         * searching.
         */
        'search.username' => 'univ-ab\[user]',
        'search.password' => '[password]',


        // 'uab:loginpage_links' => [],

        // 'authproc' => [
            
        //     40 => [
        //         'class' => 'core:AttributeMap',
        //         'extensionAttribute5' => 'AccountRecoveryEmail',
        //     ],

        //     50 => [
        //         'class' => 'uab:AttributeAddUsersGroups',
        //         'authsource' => 'uab-ldap',

        //         /**
        //          * The base DNs used to search LDAP. May not be needed if searching
        //          * LDAP using the standard method, meaning that no Product is specified.
        //          *
        //          * Default: []
        //          * Required: No
        //          * AuthSource: search.base
        //          */
        //         // 'ldap.basedn' => [
        //         //     'OU=Staff,DC=example,DC=org',
        //         //     'OU=Students,DC=example,DC=org'
        //         // ],


        //         /**
        //          * Set to TRUE to enable LDAP debug level. Passed to
        //          * the LDAP connection class.
        //          *
        //          * Default: FALSE
        //          * Required: No
        //          * AuthSource: debug
        //          */
        //         'ldap.debug' => true,


        //         /**
        //          * Set to TRUE to force the LDAP connection to use TLS.
        //          *
        //          * Note: If ldaps:// is specified in the hostname then it
        //          *       will automatically use TLS.
        //          *
        //          * Default: FALSE
        //          * Required: No
        //          * AuthSource: enable_tls
        //          */
        //         'ldap.enable_tls' => false,


        //         'ldap.product' => 'ActiveDirectory',
        //         'ldap.timeout' => 30,
        //         'timeout' => 30,


        //         /**
        //          * The following attribute.* and type.* configuration options
        //          * define the LDAP schema and should only be defined/modified
        //          * if the schema has been modified or the LDAP product used
        //          * uses other attribute names. By default, the schema is setup
        //          * for ActiveDirectory.
        //          *
        //          * Defaults: Listed Below
        //          * Required: No
        //          */
        //         'attribute.dn' => 'distinguishedName',
        //         'attribute.groups' => 'groups',
        //         // Also noted above
        //         'attribute.member' => 'member',
        //         'attribute.memberOf' => 'memberOf',
        //         'attribute.groupname' => 'name',
        //         'attribute.return' => 'distinguishedName',
        //         'attribute.type' => 'objectClass',
        //         'attribute.username' => 'sAMAccountName',


        //         /**
        //          * As mentioned above, these can be changed if the LDAP schema
        //          * has been modified. These list the Object/Entry Type for a given
        //          * DN, in relation to the 'attribute.type' config option above.
        //          * These are used to determine the type of entry.
        //          *
        //          * Defaults: Listed Below
        //          * Required: No
        //          */
        //         'type.group' => 'group',
        //         'type.user' => 'user',


        //         /**
        //          * LDAP search filters to be added to the base filters for this
        //          * authproc-filter. It's an array of key => value pairs that will
        //          * be translated to (key=value) in the ldap query.
        //          */
        //         'additional_filters' => [],
        //     ]
        // ],
    ],

];
