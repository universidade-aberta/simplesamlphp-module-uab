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

/**
 * LDAP authentication source.
 *
 * See the ldap-entry in config-templates/authsources.php for information about
 * configuration of this authentication source.
 */

class Ldap extends \SimpleSAML\Module\ldap\Auth\Source\Ldap{

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

        $this->connector = ConnectorFactory::fromAuthSource($this->authId);
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
    }
}
