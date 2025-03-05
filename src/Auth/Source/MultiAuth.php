<?php

declare(strict_types=1);

namespace SimpleSAML\Module\uab\Auth\Source;

use Exception;
use SAML2\Exception\Protocol\NoAuthnContextException;
use SimpleSAML\Assert\Assert;
use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\Auth\ProcessingChain;
use SimpleSAML\Auth\Source;
use SimpleSAML\Auth\State;
use SimpleSAML\Logger;
use SimpleSAML\Error;
use SimpleSAML\HTTP\RunnableResponse;
use SimpleSAML\Module;
use SimpleSAML\Module\core\Auth\UserPassBase;
use SimpleSAML\Module\core\Auth\UserPassOrgBase;
use SimpleSAML\Session;
use SimpleSAML\Utils;

/**
 * Authentication source which let the user chooses among a list of
 * other authentication sources
 *
 * @package SimpleSAML\Module\uab
 */
class MultiAuth extends UserPassBase {
    /**
     * The key of the AuthId field in the state.
     */
    public const AUTHID = '\SimpleSAML\Module\uab\Auth\Source\MultiAuth.AuthId';

    /**
     * The string used to identify our states.
     */
    public const STAGEID = '\SimpleSAML\Module\uab\Auth\Source\MultiAuth.StageId';

    /**
     * The key where the sources is saved in the state.
     */
    public const SOURCESID = '\SimpleSAML\Module\uab\Auth\Source\MultiAuth.SourceId';

    /**
     * The key where the selected source is saved in the session.
     */
    public const SESSION_SOURCE = 'uab-multiauth:selectedSource';

    /**
     * Array of sources we let the user chooses among.
     * @var array
     */
    private array $sources;

    /**
     * @var string|null preselect source in filter module configuration
     */
    private ?string $preselect;


    /**
     * Constructor for this authentication source.
     *
     * @param array $info Information about this authentication source.
     * @param array $config Configuration.
     */
    public function __construct(array $info, array $config)
    {
        // Call the parent constructor first, as required by the interface
        parent::__construct($info, $config);

        if (!array_key_exists('sources', $config)) {
            throw new Exception('The required "sources" config option was not found');
        }

        if (array_key_exists('preselect', $config) && is_string($config['preselect'])) {
            if (!array_key_exists($config['preselect'], $config['sources'])) {
                throw new Exception('The optional "preselect" config option must be present in "sources"');
            }

            $this->preselect = $config['preselect'];
        }

        $this->sources = $config['sources'];
    }


    /**
     * Prompt the user with a list of authentication sources.
     *
     * This method saves the information about the configured sources,
     * and redirects to a page where the user must select one of these
     * authentication sources.
     *
     * This method never return. The authentication process is finished
     * in the delegateAuthentication method.
     *
     * @param array &$state Information about the current authentication.
     */
    public function authenticate(array &$state): void
    {
        $state[self::AUTHID] = $this->authId;
        $state[self::SOURCESID] = $this->sources;
        $arrayUtils = new Utils\Arrays();

        if (!array_key_exists('multiauth:preselect', $state) && isset($this->preselect)) {
            $state['multiauth:preselect'] = $this->preselect;
        }

        if (
            array_key_exists('saml:RequestedAuthnContext', $state)
            && !is_null($state['saml:RequestedAuthnContext'])
            && array_key_exists('AuthnContextClassRef', $state['saml:RequestedAuthnContext'])
        ) {
            $refs = array_values($state['saml:RequestedAuthnContext']['AuthnContextClassRef']);
            $new_sources = [];
            foreach ($this->sources as $key => $source) {
                $config_refs = $arrayUtils->arrayize($source['AuthnContextClassRef']);
                if (count(array_intersect($config_refs, $refs)) >= 1) {
                    $new_sources[$key] = $source;
                }
            }
            $state[self::SOURCESID] = $new_sources;

            $number_of_sources = count($new_sources);
            if ($number_of_sources === 0) {
                throw new NoAuthnContextException(
                    'No authentication sources exist for the requested AuthnContextClassRefs: ' . implode(', ', $refs),
                );
            } elseif ($number_of_sources === 1) {
                static::delegateAuthentication(array_key_first($new_sources), $state);
            }
        }

        // Save the $state array, so that we can restore if after a redirect
        $id = Auth\State::saveState($state, self::STAGEID);

        /* Redirect to the select source page. We include the identifier of the
         * saved state array as a parameter to the login form
         */
        $url = Module::getModuleURL('uab/discovery');
        $params = ['AuthState' => $id];

        // Allows the user to specify the auth source to be used
        if (isset($_GET['source'])) {
            $params['source'] = $_GET['source'];
        }

        $httpUtils = new Utils\HTTP();
        $httpUtils->redirectTrustedURL($url, $params);

        // The previous function never returns, so this code is never executed
        Assert::true(false);
    }


    /**
     * Delegate authentication.
     *
     * This method is called once the user has chosen one authentication
     * source. It saves the selected authentication source in the session
     * to be able to logout properly. Then it calls the authenticate method
     * on such selected authentication source.
     *
     * @param string $authId Selected authentication source
     * @param array $state Information about the current authentication.
     * @return \SimpleSAML\HTTP\RunnableResponse
     * @throws \Exception
     */
    public static function delegateAuthentication(string $authId, array $state): RunnableResponse
    {
        $as = Auth\Source::getById($authId);
        if ($as === null || !array_key_exists($authId, $state[self::SOURCESID])) {
            throw new Exception('Invalid authentication source: ' . $authId);
        }

        // Save the selected authentication source for the logout process.
        $session = Session::getSessionFromRequest();
        $session->setData(
            self::SESSION_SOURCE,
            $state[self::AUTHID],
            $authId,
            Session::DATA_TIMEOUT_SESSION_END,
        );

        return new RunnableResponse([self::class, 'doAuthentication'], [$as, $state]);
    }


    /**
     * @param \SimpleSAML\Auth\Source $as
     * @param array $state
     * @return void
     */
    public static function doAuthentication(Auth\Source $as, array $state): void
    {
        try {
            $as->authenticate($state);
        } catch (Error\Exception $e) {
            Auth\State::throwException($state, $e);
        } catch (Exception $e) {
            $e = new Error\UnserializableException($e);
            Auth\State::throwException($state, $e);
        }
        Auth\Source::completeAuth($state);
    }

    /**
     * Attempt to log in using the given username and password.
     *
     * On a successful login, this function should return the users attributes. On failure,
     * it should throw an exception/error. If the error was caused by the user entering the wrong
     * username or password, a \SimpleSAML\Error\Error('WRONGUSERPASS') should be thrown.
     *
     * Note that both the username and the password are UTF-8 encoded.
     *
     * @param string $username  The username the user wrote.
     * @param string $password  The password the user wrote.
     * @return array Associative array with the user's attributes.
     */
    protected function login(string $username, string $password): array{
        throw new \SimpleSAML\Error\Error('WRONGUSERPASS');
    }


    /**
     * Log out from this authentication source.
     *
     * This method retrieves the authentication source used for this
     * session and then call the logout method on it.
     *
     * @param array &$state Information about the current logout operation.
     */
    public function logout(array &$state): void
    {
        // Get the source that was used to authenticate
        $session = Session::getSessionFromRequest();
        $authId = $session->getData(self::SESSION_SOURCE, $this->authId);

        $source = Auth\Source::getById($authId);
        if ($source === null) {
            throw new Exception('Invalid authentication source during logout: ' . $authId);
        }
        // Then, do the logout on it
        $source->logout($state);
    }


    /**
     * Set the previous authentication source.
     *
     * This method remembers the authentication source that the user selected
     * by storing its name in a cookie.
     *
     * @param string $source Name of the authentication source the user selected.
     */
    public function setPreviousSource(string $source): void
    {
        return; // We don't want to save the previous source cookie
        // $cookieName = 'uab-multiauth_source_' . $this->authId;

        // $config = Configuration::getInstance();
        // $params = [
        //     // We save the cookies for 90 days
        //     'lifetime' => 7776000, //60*60*24*90
        //     // The base path for cookies. This should be the installation directory for SimpleSAMLphp.
        //     'path' => $config->getBasePath(),
        //     'httponly' => false,
        // ];

        // $httpUtils = new Utils\HTTP();
        // $httpUtils->setCookie($cookieName, $source, $params, false);
    }


    /**
     * Get the previous authentication source.
     *
     * This method retrieves the authentication source that the user selected
     * last time or NULL if this is the first time or remembering is disabled.
     * @return string|null
     */
    public function getPreviousSource(): ?string
    {
        $cookieName = 'uab-multiauth_source_' . $this->authId;
        if (array_key_exists($cookieName, $_COOKIE)) {
            return $_COOKIE[$cookieName];
        } else {
            return null;
        }
    }

    /**
     * Handle login request.
     *
     * This function is used by the login form (core/loginuserpass) when the user
     * enters a username and password. On success, it will not return. On wrong
     * username/password failure, and other errors, it will throw an exception.
     *
     * @param string $authStateId  The identifier of the authentication state.
     * @param string $username  The username the user wrote.
     * @param string $password  The password the user wrote.
     * 
     * @see \SimpleSAML\Module\core\Auth\UserPassBase::handleLogin()
     */
    use tConfig;
    public static function handleLoginBase(string $authStateId, string $username, string $password): void
    {
        // Here we retrieve the state array we saved in the authenticate-function.
        $state = State::loadState($authStateId, UserPassBase::STAGEID);

        // Retrieve the authentication source we are executing.
        Assert::keyExists($state, UserPassBase::AUTHID);

        /** @var \SimpleSAML\Module\core\Auth\UserPassBase|null $source */
        $source = Source::getById($state[UserPassBase::AUTHID]);
        if ($source === null) {
            throw new Exception('Could not find authentication source with id ' . $state[UserPassBase::AUTHID]);
        }
        // Attempt to log in
        try {
            $attributes = $source->login($username, $password);
        } catch (Exception $e) {
            Logger::stats('Unsuccessful login attempt from ' . $_SERVER['REMOTE_ADDR'] . '.');
            throw $e;
        }

        Logger::stats('User \'' . $username . '\' successfully authenticated from ' . $_SERVER['REMOTE_ADDR']);

        // Save the attributes we received from the login-function in the $state-array
        $state['Attributes'] = $attributes;

        // @UAb: enable attribute processing chain
        self::processingChain($source, $state);

        // Return control to SimpleSAMLphp after successful authentication.
        Source::completeAuth($state);
    }
    /**
     * Handle login request.
     *
     * This function is used by the login form (core/loginuserpassorg) when the user
     * enters a username and password. On success, it will not return. On wrong
     * username/password failure, and other errors, it will throw an exception.
     *
     * @param string $authStateId  The identifier of the authentication state.
     * @param string $username  The username the user wrote.
     * @param string $password  The password the user wrote.
     * @param string $organization  The id of the organization the user chose.
     * 
     * @see \SimpleSAML\Module\core\Auth\UserPassOrgBase::handleLogin
     */
    public static function handleLoginOrgBase(
        string $authStateId,
        string $username,
        string $password,
        string $organization
    ): void {
        /* Retrieve the authentication state. */
        $state = Auth\State::loadState($authStateId, UserPassOrgBase::STAGEID);

        /* Find authentication source. */
        Assert::keyExists($state, UserPassOrgBase::AUTHID);

        /** @var \SimpleSAML\Module\core\Auth\UserPassOrgBase|null $source */
        $source = Auth\Source::getById($state[UserPassOrgBase::AUTHID]);
        if ($source === null) {
            throw new Exception('Could not find authentication source with id ' . $state[UserPassOrgBase::AUTHID]);
        }

        $orgMethod = $source->getUsernameOrgMethod();
        if ($orgMethod !== 'none') {
            $tmp = explode('@', $username, 2);
            if (count($tmp) === 2) {
                $username = $tmp[0];
                $organization = $tmp[1];
            } else {
                if ($orgMethod === 'force') {
                    /* The organization should be a part of the username, but isn't. */
                    throw new Error\Error('WRONGUSERPASS');
                }
            }
        }

        /* Attempt to log in. */
        try {
            $attributes = $source->login($username, $password, $organization);
        } catch (Exception $e) {
            Logger::stats('Unsuccessful login attempt from ' . $_SERVER['REMOTE_ADDR'] . '.');
            throw $e;
        }

        Logger::stats(
            'User \'' . $username . '\' at \'' . $organization
            . '\' successfully authenticated from ' . $_SERVER['REMOTE_ADDR']
        );

        // Add the selected Org to the state
        $state[UserPassOrgBase::ORGID] = $organization;
        $state['PersistentAuthData'][] = UserPassOrgBase::ORGID;

        $state['Attributes'] = $attributes;

        // @UAb: enable attribute processing chain
        self::processingChain($source, $state);

        Auth\Source::completeAuth($state);
    }

    public static function processingChain(Auth\Source $source, array &$state):void{
        $returnCallAdded = false;
        if(empty($state['ReturnCall']) && empty($state['ReturnURL'])):
            $state['ReturnCall'] = function(){};
            $returnCallAdded = true;
        endif;
        $pc = new ProcessingChain(array_merge(['entityid'=>$source->getAuthId()], self::loadConfig($source->getAuthId())), ['entityid'=>''], 'ldap');
        $pc->processState($state);
        if($returnCallAdded):
            unset($state['ReturnCall']);
        endif;
    }
}
