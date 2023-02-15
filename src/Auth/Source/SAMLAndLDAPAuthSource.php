<?php

namespace SimpleSAML\Module\uab\Auth\Source;

use SimpleSAML\Auth\Source;
use SimpleSAML\Error\AuthSource;
use SimpleSAML\Logger;
use SimpleSAML\Module\ldap\Auth\Ldap;
use SimpleSAML\Module\saml\Auth\Source\SP;

class SAMLAndLDAPAuthSource implements Source {
    /**
     * @var \SimpleSAML\Module\saml\Auth\Source\SP
     */
    private $samlAuth;

    /**
     * @var \SimpleSAML\Module\ldap\Auth\Ldap
     */
    private $ldapAuth;

    /**
     * @var \PDO
     */
    private $db;

    /**
     * @var string
     */
    private $uniqueIdentifier;

    /**
     * SAMLAndLDAPAuthSource constructor.
     *
     * @param array $config
     * @param \SimpleSAML\Module\saml\Auth\Source\SP $samlAuth
     * @param \SimpleSAML\Module\ldap\Auth\Ldap $ldapAuth
     * @param \PDO $db
     */
    public function __construct($config, $samlAuth, $ldapAuth, $db){
        $this->samlAuth = $samlAuth;
        $this->ldapAuth = $ldapAuth;
        $this->db = $db;
        $this->uniqueIdentifier = $config['uniqueIdentifier'];
    }

    /**
     * Initiate login.
     *
     * This method never returns.
     */
    public function login($returnTo = null, $errorURL = null)
    {
        $this->samlAuth->login($returnTo, $errorURL);
    }

    /**
     * Check if the user is authenticated.
     *
     * @return bool True if the user is authenticated.
     */
    public function isAuthenticated(){
        if (!$this->samlAuth->isAuthenticated()) {
            return $this->ldapAuth->isAuthenticated();
        }

        // Check if the SAML identity is associated with an LDAP account
        $samlAttributes = $this->samlAuth->getAttributes();
        $samlIdentity = $samlAttributes[$this->uniqueIdentifier][0];
        $stmt = $this->db->prepare('SELECT * FROM saml_ldap_mapping WHERE saml_id = ?');
        $stmt->execute([$samlIdentity]);
        $mapping = $stmt->fetch();

        if ($mapping === false){
            // SAML identity is not associated with an LDAP account
            // Present the LDAP authentication form
            $this->ldapAuth->login($returnTo, $errorURL);
            $ldapAttributes = $this->ldapAuth->getAttributes();
            $ldapIdentity = $ldapAttributes[$this->uniqueIdentifier][0];

            // Store the mapping in the database
            $stmt = $this->db->prepare('INSERT INTO saml_ldap_mapping (saml_id, ldap_id) VALUES (?,?);');
            $stmt->execute([$samlIdentity, $ldapIdentity]);
        } else {
            // SAML identity is already associated with an LDAP account
            // Use the mapped LDAP identity for authentication
            $this->ldapAuth->login($returnTo, $errorURL, $mapping['ldap_id']);
        }

        return true;
    }

    /**
     * Complete logout.
     *
     * This method never returns.
     */
    public function logout($returnTo = null)
    {
        $this->samlAuth->logout($returnTo);
        $this->ldapAuth->logout($returnTo);
    }

    /**
     * Get the user's attributes.
     *
     * @return array The user's attributes.
     */
    public function getAttributes()
    {
        return $this->samlAuth->isAuthenticated() ?
            $this->samlAuth->getAttributes() : $this->ldapAuth->getAttributes();
    }
}
