<?php 

declare(strict_types=1);

namespace SimpleSAML\Module\uab\Auth\Source;

use SimpleSAML\Auth\Source;
use SimpleSAML\Error\AuthSource;
use SimpleSAML\Logger;
use SimpleSAML\Module\ldap\Auth\Ldap;
use SimpleSAML\Module\saml\Auth\Source\SP;


class AuthLDAPSAML extends Source
{
    /**
     * @var \SimpleSAML\Module\ldap\Auth\Ldap
     */
    private $ldapAuth;

    /**
     * @var \SimpleSAML\Module\saml\Auth\Source\SP
     */
    private $samlAuth;

    /**
     * @var array
     */
    private $ldapConfig;

    /**
     * @var array
     */
    private $samlConfig;

    /**
     * @var string
     */
    private $uniqueIdentifier;

    /**
     * AuthLDAPSAML constructor.
     *
     * @param array  $info Information about this authentication source.
     * @param array  $config Configuration.
     * @throws \SimpleSAML\Error\AuthSource
     */
    public function __construct(array $info, array $config)
    {
        // Call the parent constructor
        parent::__construct($info, $config);

        // Get the unique identifier for merging the identities
        $this->uniqueIdentifier = $config['uniqueIdentifier'];

        // Get the configuration for the LDAP authentication source
        $this->ldapConfig = $config['ldapConfig'];
        $this->ldapAuth = new Ldap($this->ldapConfig);

        // Get the configuration for the SAML authentication source
        $this->samlConfig = $config['samlConfig'];
        $this->samlAuth = new SP($this->samlConfig);
    }

    /**
     * Log in using the SAML IdP.
     *
     * @param array &$state Information about the current authentication.
     */
    public function login(&$state)
    {
        $this->samlAuth->login($state);
    }

    /**
     * Log out using the SAML IdP.
     *
     * @param array &$state Information about the current authentication.
     */
    public function logout(&$state)
    {
        $this->samlAuth->logout($state);
    }

    /**
     * Get the authentication source for this authentication source.
     *
     * @return \SimpleSAML\Module\saml\Auth\Source\SP
     */
    public function getAuthSource()
    {
        return $this->samlAuth;
    }

    /**
     * Check if the user is authenticated.
     *
     * @return bool True if the user is authenticated, false otherwise.
     */
    public function isAuthenticated()
    {
        return $this->samlAuth->isAuthenticated();
    }

    /**
     * Get attributes of the current user.
     *
     * @return array Attributes of the current user.
     */
    public function getAttributes()
    {
        $samlAttributes = $this->samlAuth->getAttributes();
        $ldapAttributes = $this->ldapAuth->getAttributes($samlAttributes[$this->uniqueIdentifier][0]);

        // Merge the SAML and LDAP attributes
        return array_merge($samlAttributes, $ldapAttributes);
    }

    /**
     * Retrieve the LDAP authentication source.
     *
     * @return \SimpleSAML\Module\ldap\Auth\Ldap
     */
    public function getLDAPAuth()
    {
        return $this->ldapAuth;
    }

    /**
     * Retrieve the SAML authentication source.
     *
     * @return \SimpleSAML\Module\saml\Auth\Source\SP
     */
    public function getSAMLAuth()
    {
        return $this->samlAuth;
    }
}