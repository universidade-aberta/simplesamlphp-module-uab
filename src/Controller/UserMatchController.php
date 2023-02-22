<?php 

declare(strict_types=1);

namespace SimpleSAML\Module\uab\Controller;

use Exception;
use SimpleSAML\Auth;
use SimpleSAML\Auth\AuthenticationFactory;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\HTTP\RunnableResponse;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Module\multiauth\Auth\Source\MultiAuth;
use SimpleSAML\Session;
use SimpleSAML\Utils;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use SimpleSAML\Assert\Assert;
use SimpleSAML\Auth\Source;

use \SimpleSAML\Module\uab\Auth\Process\UserMatch;

class UserMatchController{

    /**
     * The string used to identify our states.
     */
    public const STAGEID = __CLASS__.'.state';

    /**
     * The key of the AuthId field in the state.
     */
    public const AUTHID = __CLASS__.'.AuthId';

    public const USER_MATCH_CONFIG = 'UserMatchConfig';

    /** @var \SimpleSAML\Configuration */
    protected Configuration $config;

    /** @var \SimpleSAML\Session */
    protected Session $session;

    /**
     * @var \SimpleSAML\Auth\Simple|string
     * @psalm-var \SimpleSAML\Auth\Simple|class-string
     */
    protected $authSimple = Auth\Simple::class;

    /**
     * @var \SimpleSAML\Auth\State|string
     * @psalm-var \SimpleSAML\Auth\State|class-string
     */
    protected $authState = Auth\State::class;

    /** @var \SimpleSAML\Utils\HTTP */
    private \SimpleSAML\Utils\HTTP $httpUtils;

    /**
     * @var \SimpleSAML\Utils\HTTP::class|string
     * @psalm-var \SimpleSAML\Utils\HTTP::class|class-string
     */
    protected $HttpUtils = \SimpleSAML\Utils\HTTP::class;

    /**
     * @var \SimpleSAML\Database::class|string
     * @psalm-var \SimpleSAML\Database::class|class-string
     */
    protected static $database = \SimpleSAML\Database::class;

    /**
     * @var \SimpleSAML\Auth\ProcessingChain::class|string
     * @psalm-var \SimpleSAML\Auth\ProcessingChain::class|class-string
     */
    protected static $ProcessingChain = \SimpleSAML\Auth\ProcessingChain::class;

    /**
     * Controller constructor.
     *
     * It initializes the global configuration and auth source configuration for the controllers implemented here.
     *
     * @param \SimpleSAML\Configuration              $config The configuration to use by the controllers.
     * @param \SimpleSAML\Session                    $session The session to use by the controllers.
     *
     * @throws \Exception
     */
    public function __construct(Configuration $config, Session $session) {
        $this->config = $config;
        $this->session = $session;

        $this->httpUtils = new $this->HttpUtils();
    }

    /**
     * Inject the \SimpleSAML\Auth\Simple dependency.
     *
     * @param \SimpleSAML\Auth\Simple $authSimple
     */
    public function setAuthSimple(Auth\Simple $authSimple):void {
        $this->authSimple = $authSimple;
    }


    /**
     * Inject the \SimpleSAML\Auth\State dependency.
     *
     * @param \SimpleSAML\Auth\State $authState
     */
    public function setAuthState(Auth\State $authState):void {
        $this->authState = $authState;
    }

    /**
     * This controller shows a notice to inform the user that he/she must authenticate with the primary credentials to associate the primary with the secondary account
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \SimpleSAML\XHTML\Template|\SimpleSAML\HTTP\RunnableResponse
     *   An HTML template
     */
    public function primaryAuthNotice(Request $request):Template|RunnableResponse{
        $authStateId = $request->query->get('StateId');
        $state = $this->authState::loadState($authStateId.":".$this->getBaseUrl(), self::STAGEID, true);
        Assert::keyExists($state, self::AUTHID);
        if (empty($state[self::AUTHID])):
            throw new \Exception('Invalid state.');
        endif;
        
        // Check the existence of required state data
        if(empty($state[self::AUTHID.'_data'])):
            throw new Error\Exception(\sprintf('Missing state data. Cannot proceed.'));
        endif;

        $stateData = $state[self::AUTHID.'_data'];

        $t = new Template($this->config, 'uab:primary-auth-notice.twig');
        $t->data['StateId'] = $authStateId;
        $t->data['MainAccountProvider'] = $stateData[UserMatch::CONFIG_auth_source_primary_provider_name]??'Main';
        $t->data['SecondaryAccount'] = $stateData[UserMatch::CONFIG_auth_source_secondary_match_value]??'';

        return $t;
    }

    /**
     * This controller forwards the authentication request for the primary auth source
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \SimpleSAML\XHTML\Template|\SimpleSAML\HTTP\RunnableResponse
     *   An HTML template
     */
    public function primaryAuth(Request $request): Template|RunnableResponse{
        $authStateId = $request->query->get('StateId');
        $state = $this->authState::loadState($authStateId.":".$this->getBaseUrl(), self::STAGEID, true);

        // Check the existence of required state data
        if(empty($state[self::AUTHID.'_data'])):
            throw new Error\Exception(\sprintf('Missing state data. Cannot proceed.'));
        endif;

        // Check for the existence of the required authsources
        $stateData = $state[self::AUTHID.'_data'][self::USER_MATCH_CONFIG];
        $authSources = [
            UserMatch::CONFIG_auth_source_primary,
            UserMatch::CONFIG_auth_source_secondary,
        ];
        foreach ($authSources as $authSource): 
            if (empty($stateData[$authSource])):
                throw new Error\Exception(\sprintf('Missing auth source for "%s". Cannot proceed.', $authSource));
            endif;
        endforeach;

        $continue = $request->getMethod() === 'POST' && !empty($request->request->get('continue'));
        $authSourceId = $stateData[$continue?UserMatch::CONFIG_auth_source_primary:UserMatch::CONFIG_auth_source_secondary];
        $authSource = new $this->authSimple($authSourceId);
        if ($authSource === null):
            throw new Error\Exception(\sprintf('Auth source "%s" not found. Cannot proceed.', $authSourceId));
        endif;

        if ($continue):
            // Proceed to primary auth authentication
            Logger::debug(sprintf('Forward user to primary auth source "%s" login...', $authSourceId));
            $params = [
                'ErrorURL' => Module::getModuleURL('uab/primary-auth-notice', [
                    'StateId' => $authStateId,
                ]),
                'ReturnTo' => Module::getModuleURL('uab/primary-auth-completed', [
                    'StateId' => $authStateId,
                ]),
                $this->authState::RESTART => Module::getModuleURL('uab/primary-auth-notice', [
                    'StateId' => $authStateId,
                ]),
            ];
            return new RunnableResponse([$authSource, 'login'], [$params]);
        endif;

        // Proceed to secondary auth logout
        unset($state[self::AUTHID]);
        unset($state[self::AUTHID.'_data']);
        $id = $this->authState::saveState($state, $authStateId);

        $url = $this->getBaseUrl();
        $params = [
            'ErrorURL' => $url,
            'ReturnTo' => $url,
            $this->authState::RESTART => $url,
        ];

        Logger::debug(sprintf('User cancelled the primary login. Logout from secondary auth source "%s"...', $authSourceId));
        return new RunnableResponse([$authSource, 'logout'], [$params]);
    }

    /**
     * This controller shows a notice to inform the user that he/she must authenticate with the primary credentials to associate the primary with the secondary account
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \SimpleSAML\XHTML\Template|\SimpleSAML\HTTP\RunnableResponse
     *   An HTML template
     */
    public function primaryAuthCompleted(Request $request): Template|RunnableResponse{
        $authStateId = $request->query->get('StateId');
        $state = $this->authState::loadState($authStateId.":".$this->getBaseUrl(), self::STAGEID, true);

        // Check the existence of required state data
        if(empty($state[self::AUTHID.'_data'])):
            throw new Error\Exception(\sprintf('Missing state data. Cannot proceed.'));
        endif;

        // Check for the existence of the required authsources
        $stateData = $state[self::AUTHID.'_data'][self::USER_MATCH_CONFIG];
        $authSources = [
            UserMatch::CONFIG_auth_source_primary,
            UserMatch::CONFIG_auth_source_secondary,
        ];
        foreach ($authSources as $authSource): 
            if (empty($stateData[$authSource])):
                throw new Error\Exception(\sprintf('Missing auth source for "%s". Cannot proceed.', $authSource));
            endif;
        endforeach;

        $authSourceId = $stateData[UserMatch::CONFIG_auth_source_primary];
        $authSource = new $this->authSimple($authSourceId);
        if ($authSource === null):
            throw new Error\Exception(\sprintf('Auth source "%s" not found. Cannot proceed.', $authSourceId));
        endif;

        if (!$authSource->isAuthenticated()) :
            throw new Error\Exception(\sprintf('Primary authentication failed.'));
        endif;

        $attributes = \array_map(function($attribute){
            return \is_array($attribute)?\reset($attribute):$attribute;
        }, $authSource->getAttributes());

        if(!empty($attributes[$stateData[UserMatch::CONFIG_auth_source_primary_match_field]])):
            $secondaryValue = $state['Attributes'][$stateData[UserMatch::CONFIG_auth_source_secondary_match_field]];
            if(\is_array($secondaryValue)):
                $secondaryValue = \reset($secondaryValue);
            endif;
            self::associateAccounts($attributes[$stateData[UserMatch::CONFIG_auth_source_primary_match_field]], $secondaryValue, $stateData);
        else:
            throw new Error\Exception(\sprintf('Field "%s" was not returned by the primary authentication source.', $stateData[UserMatch::CONFIG_auth_source_primary_match_field]));
        endif;

        $state['Attributes'] = self::filterAttributes($authSource->getAttributes()); //\array_merge($state['Attributes'], $authSource->getAttributes());
        unset($state[self::AUTHID]);
        unset($state[self::AUTHID.'_data']);
        return new RunnableResponse([self::$ProcessingChain, 'resumeProcessing'], [$state]);
    }

    /**
     * This controller shows a notice to inform the user that the primary account is disabled or expired
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \SimpleSAML\XHTML\Template|\SimpleSAML\HTTP\RunnableResponse
     *   An HTML template
     */
    public function primaryAccountDisabledNotice(Request $request):Template|RunnableResponse{
        $authStateId = $request->query->get('StateId');
        $state = $this->authState::loadState($authStateId.":".$this->getBaseUrl(), self::STAGEID, true);

        // Check the existence of required state data
        if(empty($state[self::AUTHID.'_data'])):
            throw new Error\Exception(\sprintf('Missing state data. Cannot proceed.'));
        endif;

        $stateData = $state[self::AUTHID.'_data'];

        $t = new Template($this->config, 'uab:primary-account-disabled-notice.twig');
        $t->data['StateId'] = $authStateId;
        $t->data['MainAccountProvider'] = $stateData[UserMatch::CONFIG_auth_source_primary_provider_name]??'Main';
        $t->data['PrimaryAccount'] = $stateData[UserMatch::CONFIG_auth_source_primary_match_value]??'';

        return $t;
    }

    protected function getBaseUrl():string{
        return $this->httpUtils->getBaseURL();
    }

    protected static function hash(string $value):string{
        return sodium_bin2hex(sodium_crypto_generichash($value));
    }

    public static function getPrimaryAccounts(string $secondaryValue, array $config):array{
        $result = self::$database::getInstance()->read("
            SELECT `".UserMatch::CONFIG_auth_source_primary_match_value."` 
            FROM `{$config[UserMatch::CONFIG_table_name]}` 
            WHERE `".UserMatch::CONFIG_auth_source_secondary_match_value."` = :secondaryValue
                AND `".UserMatch::CONFIG_auth_source_secondary_match_field."` = :secondaryField
                AND `".UserMatch::CONFIG_auth_source_secondary."` = :secondary
                AND `".UserMatch::CONFIG_auth_source_primary_match_field."` = :primaryField
                AND `".UserMatch::CONFIG_auth_source_primary."` = :primary
        ", [
            'secondaryValue' => (string) self::hash($secondaryValue),
            'secondaryField' => (string) $config[UserMatch::CONFIG_auth_source_secondary_match_field],
            'secondary' => (string) $config[UserMatch::CONFIG_auth_source_secondary],
            'primaryField' => (string) $config[UserMatch::CONFIG_auth_source_primary_match_field],
            'primary' => (string) $config[UserMatch::CONFIG_auth_source_primary],
        ]);

        return $result->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Associate a primary and secondary accounts
     * 
     * @param string $primaryValue The value of primary account (e.g. value of sAMAccountName)
     * @param string $secondaryValue The value of primary account (e.g. value of NIF)
     * @param array $config UserMatch config with the fields information
     * 
     * @return int|false The number of associated accounts or false
     */
    public static function associateAccounts(string $primaryValue, string $secondaryValue, array $config):int|false{
        return self::$database::getInstance()->write("
            INSERT IGNORE INTO `{$config[UserMatch::CONFIG_table_name]}` (
                `".UserMatch::CONFIG_auth_source_primary."`,
                `".UserMatch::CONFIG_auth_source_primary_match_field."`,
                `".UserMatch::CONFIG_auth_source_primary_match_value."`, 
                `".UserMatch::CONFIG_auth_source_secondary."`, 
                `".UserMatch::CONFIG_auth_source_secondary_match_field."`, 
                `".UserMatch::CONFIG_auth_source_secondary_match_value."`
            ) VALUES (
                :primary, 
                :primaryField,
                :primaryValue,
                :secondary,
                :secondaryField, 
                :secondaryValue
            );
        ", [
            'primary' => (string) $config[UserMatch::CONFIG_auth_source_primary],
            'primaryField' => (string) $config[UserMatch::CONFIG_auth_source_primary_match_field],
            'primaryValue' => (string) $primaryValue,
            'secondary' => (string) $config[UserMatch::CONFIG_auth_source_secondary],
            'secondaryField' => (string) $config[UserMatch::CONFIG_auth_source_secondary_match_field],
            'secondaryValue' => (string) self::hash($secondaryValue),
        ]);
    }

    /**
     * Check if a LDAP account is expired based on its 'accountExpires' value
     */
    public static function isLdapAccountExpired(int $accountExpires):bool {
        // If the LDAP accountExpires is less than current time + number of seconds between 1601 and 1970 in 100-nanosecond intervals, the account is expired
        return $accountExpires<=((time()+11644473600)*10000000);
   }

   /**
    * Check if a LDAP account is disabled based on its 'userAccountControl' value
    */
    public static function isLdapAccountDisabled(int $userAccountControl):bool {
       return (bool)($userAccountControl & 2);
    }

    public static function filterAttributes(array $attributes=[], array $attributesToRemove=['userAccountControl', 'accountExpires']):array{
        return array_filter($attributes, function($key) use($attributesToRemove) {
            return !\in_array($key, $attributesToRemove);
        }, \ARRAY_FILTER_USE_KEY);
    }
}