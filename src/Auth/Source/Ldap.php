<?php

declare(strict_types=1);

namespace SimpleSAML\Module\uab\Auth\Source;

use SimpleSAML\Auth\State;
use SimpleSAML\Module;
use SimpleSAML\Assert\Assert;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\Utils\HTTP;
use SimpleSAML\Module\core\Auth\UserPassBase;
use SimpleSAML\Module\uab\ConnectorFactory;
use SimpleSAML\Logger;
use SAML2\Constants;
use Symfony\Component\Ldap\Adapter\ExtLdap\Query;
use Symfony\Component\Ldap\Entry;

/**
 * LDAP authentication source.
 *
 * See the ldap-entry in config-templates/authsources.php for information about
 * configuration of this authentication source.
 */

class Ldap extends \SimpleSAML\Module\ldap\Auth\Source\Ldap{

    /** @var \SimpleSAML\Utils\HTTP */
    /**
     * The string used to identify our states.
     */
    public const STAGEID_UPDATE_PASSWORD = self::class.'.state_pwd';

    protected string $httpUtils = HTTP::class;

    /**
     * @var \SimpleSAML\Auth\State|string
     */
    protected string $authState = State::class;
    
    /**
     * LDAP useraccountcontrol field name
     *
     * @var string
     */
    protected string $useraccountcontrol_fieldname = 'useraccountcontrol';

    /**
     * LDAP pwdlastset field name
     *
     * @var string
     */
    protected string $pwdlastset_fieldname = 'pwdlastset';

    /**
     * Username we should force.
     *
     * A forced username cannot be changed by the user.
     * If this is NULL, we won't force any username.
     *
     * @var string|null
     */
    private ?string $forcedUsername = null;

    /**
     * Constructor for this authentication source.
     *
     * @param array $info  Information about this authentication source.
     * @param array $config  Configuration.
     */
    public function __construct(array $info, array $config){
        // Call the parent constructor first, as required by the interface
        UserPassBase::__construct($info, $config);

        if (isset($config['uab:loginpage_links'])):
            $this->loginLinks = $config['uab:loginpage_links'];
        endif;

        $this->ldapConfig = Configuration::loadFromArray(
            $config,
            'authsources[' . var_export($this->authId, true) . ']'
        );

        $this->pwdlastset_fieldname = strtolower($this->ldapConfig->getOptionalString('name.pwdLastSet', $this->pwdlastset_fieldname));
        $this->useraccountcontrol_fieldname = strtolower($this->ldapConfig->getOptionalString('name.userAccountControl', $this->useraccountcontrol_fieldname));

        $this->connector = ConnectorFactory::fromAuthSource($this->authId);
    }


    /**
     * Attempt to log in using the given username and password.
     *
     * @param string $username  The username the user wrote.
     * @param string $password  The password the user wrote.
     * @return array  Associative array with the users attributes.
     */
    protected function login(string $username, string $password): array {
        $searchScope = $this->ldapConfig->getOptionalString('search.scope', Query::SCOPE_SUB);
        Assert::oneOf($searchScope, [Query::SCOPE_BASE, Query::SCOPE_ONE, Query::SCOPE_SUB]);

        $timeout = $this->ldapConfig->getOptionalInteger('timeout', 3);
        Assert::natural($timeout);

        $searchBase = $this->ldapConfig->getArray('search.base');
        $options = [
            'scope' => $searchScope,
            'timeout' => $timeout,
        ];

        $searchEnable = $this->ldapConfig->getOptionalBoolean('search.enable', false);
        if ($searchEnable === false) {
            $dnPattern = $this->ldapConfig->getString('dnpattern');
            $dn = str_replace('%username%', $username, $dnPattern);
        } else {
            $searchUsername = $this->ldapConfig->getString('search.username');
            Assert::notWhitespaceOnly($searchUsername);

            $searchPassword = $this->ldapConfig->getOptionalString('search.password', null);
            Assert::nullOrnotWhitespaceOnly($searchPassword);

            try {

                $this->connector->bind($searchUsername, $searchPassword);
            } catch (Error\Error $e) {
                throw new Error\Exception("Unable to bind using the configured search.username and search.password.");
            }

            $filter = $this->buildSearchFilter($username);

            try {
                $entry = /** @scrutinizer-ignore-type */$this->connector->search($searchBase, $filter, $options, false);

                $dn = $entry->getDn();

                if($this->hasToChangePassword($this->processAttributes($entry))):
                    if($this->sysCanChangeUserPassword($dn, $password)):
                        // @TODO: short circuit to update the user password using system account
                    else:
                        Logger::debug(sprintf('The password for "%s" has expired and system is unable to update the user password. Stopping authentication...', $username));
                        throw new Error\Exception('EXPIRED_PASSWORD', -31171);
                    endif;
                endif;
                $entry = null;
            } catch (Error\Exception $e) {
                if($e->getCode() !== -31171):
                    Logger::debug(sprintf('An error occurred on LDAP authentication: "%s".', $e->getMessage()));
                    throw new Error\Error('WRONGUSERPASS');
                endif;
                throw new Error\Error($e->getMessage());
            }
        }

        try {
            $this->connector->bind($dn, $password);

            $options['scope'] = Query::SCOPE_BASE;
            $filter = '(objectClass=*)';

            $entry = $this->connector->search([$dn], $filter, $options, false);
            
        } catch (Error\Exception $e) {
            throw new Error\Error('WRONGUSERPASS');
        }

        return $this->processAttributes(/** @scrutinizer-ignore-type */$entry);
    }

    /**
     * Returns an LDAP Connection
     * 
     * @return null|\LDAP\Connection
     */
    protected function getConnection():?\LDAP\Connection{
        $connection = $this->connector->getAdapter()->getConnection()->getResource();
        if(is_a($connection, \LDAP\Connection::class)):
            return $connection;
        endif;
        return null;
    }

    /**
     * Unbinds the current LDAP connection
     * 
     * return bool True if the unbind was successful, false otherwise
     */
    protected function unbind():bool{
        if(!empty($connection = $this->getConnection())):
            return ldap_unbind($connection);
        endif;
        return false;
    }

    /**
     * Given the LDAP attributes, check if the DN user has to update its password
     * 
     * @param array $attributes LDAP attributes with the 'pwdlastset' or 'useraccountcontrol' (or equivalent fields set in the configuration)
     * @return bool True if the user has to update the password, false otherwise
     */
    protected function hasToChangePassword(array $attributes):bool{
        $attributes = array_change_key_case($attributes, CASE_LOWER);

        $shouldUpdatePassword = false;
        
        // pwdlastset == 0
        if( !$shouldUpdatePassword && isset($attributes[$this->pwdlastset_fieldname])):
            $pwdlastset = is_array($attributes[$this->pwdlastset_fieldname]) ? reset($attributes[$this->pwdlastset_fieldname]) : $attributes[$this->pwdlastset_fieldname];
            if($pwdlastset==0):
                $shouldUpdatePassword = true;
            endif;
        endif;

        // useraccountcontrol & 0x0020
        if( !$shouldUpdatePassword && isset($attributes[$this->useraccountcontrol_fieldname])):
            $useraccountcontrol = is_array($attributes[$this->useraccountcontrol_fieldname]) ? reset($attributes[$this->useraccountcontrol_fieldname]) : $attributes[$this->useraccountcontrol_fieldname];
            if($useraccountcontrol & 0x0020):
                $shouldUpdatePassword = true;
            endif;
        endif;

        return $shouldUpdatePassword;
    }

    /**
     * Check if we have a system user authorized to update the user password
     * 
     * @param string $userDn The current user DN
     * @param string $password The current user password
     * @return bool True if the user has to update the password, false otherwise
     */
    protected function sysCanChangeUserPassword(string $userDn, string $password):bool{
        if(empty($userDn)):
            return false;
        endif;
        if(!$this->ldapConfig->getOptionalBoolean('sys.update.user.pwd', false)):
            return false;
        endif;
        if(empty($sysUpdateUserPwdUsername = $this->ldapConfig->getOptionalString('sys.update.user.pwd.username', ''))):
            return false;
        endif;
        $sysUpdateUserPwdPassword = $this->ldapConfig->getOptionalString('sys.update.user.pwd.password', '');
        
        try {
            $this->connector->bind($sysUpdateUserPwdUsername, $sysUpdateUserPwdPassword);
            if(empty($connection = $this->getConnection())):
                return false;
            endif;

            // @TODO: integrate a library or another method to check if the system user has the necessary permissions to update the user (e.g., by parsing the value of "ntSecurityDescriptor"). Also compare the given password with the AD password to check if the user knows the old password before reset his/her password. For now, return false so that no system password update is allowed.
            
            return false;
        } catch (Error\Error $e) {
            return false;
        }
    }

    /**
     * Initialize login.
     *
     * This function saves the information about the login, and redirects to a
     * login page.
     *
     * @param array &$state  Information about the current authentication.
     */
    public function authenticate(array &$state): void
    {
        /*
         * Save the identifier of this authentication source, so that we can
         * retrieve it later. This allows us to call the login()-function on
         * the current object.
         */
        $state[self::AUTHID] = $this->authId;

        // What username we should force, if any
        if ($this->forcedUsername !== null) {
            /*
             * This is accessed by the login form, to determine if the user
             * is allowed to change the username.
             */
            $state['forcedUsername'] = $this->forcedUsername;
        }

        // ECP requests supply authentication credentials with the AuthnRequest
        // so we validate them now rather than redirecting. The SAML spec
        // doesn't define how the credentials are transferred, but Office 365
        // uses the Authorization header, so we will just use that in lieu of
        // other use cases.
        if (isset($state['saml:Binding']) && $state['saml:Binding'] === Constants::BINDING_PAOS) {
            if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
                Logger::error("ECP AuthnRequest did not contain Basic Authentication header");
                // TODO Return a SOAP fault instead of using the current binding?
                throw new Error\Error("WRONGUSERPASS");
            }

            $username = $_SERVER['PHP_AUTH_USER'];
            $password = $_SERVER['PHP_AUTH_PW'];

            if (isset($state['forcedUsername'])) {
                $username = $state['forcedUsername'];
            }

            $attributes = $this->login($username, $password);
            $state['Attributes'] = $attributes;

            return;
        }

        // Save the $state-array, so that we can restore it after a redirect
        $id = State::saveState($state, self::STAGEID);

        /*
         * Redirect to the login form. We include the identifier of the saved
         * state array as a parameter to the login form.
         */
        $url = Module::getModuleURL('uab/loginuserpass');
        $params = ['AuthState' => $id];
        $httpUtils = new HTTP();
        $httpUtils->redirectTrustedURL($url, $params);

        // The previous function never returns, so this code is never executed.
        assert::true(false);
    }/**
     * @param \Symfony\Component\Ldap\Entry $entry
     * @return array
     */
    private function processAttributes(Entry $entry): array
    {
        $attributes = $this->ldapConfig->getOptionalValue('attributes', []);
        if ($attributes === null) {
            $result = $entry->getAttributes();
        } else {
            Assert::isArray($attributes);
            $result = array_intersect_key(
                $entry->getAttributes(),
                array_fill_keys(array_values($attributes), null)
            );
        }

        $binaries = array_intersect(
            array_keys($result),
            $this->ldapConfig->getOptionalArray('attributes.binary', []),
        );
        foreach ($binaries as $binary) {
            $result[$binary] = array_map('base64_encode', $result[$binary]);
        }

        return $result;
    }


    /**
     * @param string $username
     * @return string
     */
    private function buildSearchFilter(string $username): string
    {
        $searchAttributes = $this->ldapConfig->getArray('search.attributes');
        /** @psalm-var string|null $searchFilter */
        $searchFilter = $this->ldapConfig->getOptionalString('search.filter', null);

        $filter = '';
        foreach ($searchAttributes as $attr) {
            $filter .= '(' . $attr . '=' . $username . ')';
        }
        $filter = '(|' . $filter . ')';

        // Append LDAP filters if defined
        if ($searchFilter !== null) {
            $filter = "(&" . $filter . $searchFilter . ")";
        }

        return $filter;
    }
}
