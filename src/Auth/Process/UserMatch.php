<?php
declare(strict_types=1);

namespace SimpleSAML\Module\uab\Auth\Process;

use \SimpleSAML\Assert\Assert;
use \SimpleSAML\Auth;
use \SimpleSAML\Error;
use \SimpleSAML\Utils\HTTP;
use \SimpleSAML\Logger;
use \SimpleSAML\Module;

use \SimpleSAML\Module\ldap\ConnectorFactory;
use \SimpleSAML\Module\uab\Controller\UserMatchController;

/**
 * Filter to modify attributes using regular expressions
 *
 * This filter can modify or replace attributes given a regular expression.
 *
 * @package SimpleSAMLphp
 */
class UserMatch extends Auth\ProcessingFilter {

    const CONFIG = 'config';

    const CONFIG_table_name = 'mapping_table';
    const CONFIG_auth_source_primary = 'auth_source_primary';
    const CONFIG_auth_source_primary_match_field = 'auth_source_primary_match_field';
    const CONFIG_auth_source_primary_match_value = 'auth_source_primary_match_value';
    const CONFIG_auth_source_secondary = 'auth_source_secondary';
    const CONFIG_auth_source_secondary_match_field = 'auth_source_secondary_match_field';
    const CONFIG_auth_source_secondary_match_value = 'auth_source_secondary_match_value';
    const CONFIG_auth_source_primary_provider_name = 'auth_source_primary_provider_name';
    
    const TABLE_NAME_DEFAULT = 'uab_user_attributes_matching__tbl';

    /**
     * The string used to identify our states.
     */
    public const STAGEID = UserMatchController::STAGEID;

    /**
     * The key of the AuthId field in the state.
     */
    public const AUTHID = UserMatchController::AUTHID;


    /** @var array */
    private array $config;

    /** @var \SimpleSAML\Utils\HTTP */
    private \SimpleSAML\Utils\HTTP $httpUtils;
    
    /**
     * @var \SimpleSAML\Auth\Simple|string
     * @psalm-var \SimpleSAML\Auth\Simple|class-string
     */
    protected $authSimple = \SimpleSAML\Auth\Simple::class;

    /**
     * @var \SimpleSAML\Auth\State|string
     * @psalm-var \SimpleSAML\Auth\State|class-string
     */
    protected $authState = Auth\State::class;

    /**
     * @var \SimpleSAML\Utils\HTTP::class|string
     * @psalm-var \SimpleSAML\Utils\HTTP::class|class-string
     */
    protected $HttpUtils = HTTP::class;

    /**
     * @var \SimpleSAML\Database::class|string
     * @psalm-var \SimpleSAML\Database::class|class-string
     */
    protected $database = \SimpleSAML\Database::class;


    /**
     * Inject the \SimpleSAML\Auth\State dependency.
     *
     * @param \SimpleSAML\Auth\State $authState
     */
    public function setAuthState(Auth\State $authState):void{
        $this->authState = $authState;
    }


    /**
     * Inject the \SimpleSAML\Utils\HTTP dependency.
     *
     * @param \SimpleSAML\Utils\HTTP $httpUtils
     */
    public function setHttpUtils(\SimpleSAML\Utils\HTTP $httpUtils):void{
        $this->httpUtils = $httpUtils;
    }
    
    /**
     * Initialize this filter, parse configuration.
     *
     * @param array &$config  Configuration information about this filter.
     * @param mixed $reserved  For future use.
     * @param \SimpleSAML\Utils\HTTP $httpUtils  HTTP utility service (handles redirects).
     */
    public function __construct(array &$config, $reserved, \SimpleSAML\Utils\HTTP $httpUtils = null)
    {
        parent::__construct($config, $reserved);

        $this->httpUtils = $httpUtils ?: new $this->HttpUtils();

        $this->config = $config[self::CONFIG]?:$config;

        // Set config default values
        $this->config = array_merge([
            self::CONFIG_table_name=>self::TABLE_NAME_DEFAULT,
            self::CONFIG_auth_source_primary_match_field=>'sAMAccountName',
            self::CONFIG_auth_source_secondary_match_field=>'NIF',
        ], $this->config);
    }

    /**
     * Process this filter
     *
     * @param array &$state  The current request
     */
    public function process(array &$state): void{
        Assert::keyExists($state, 'Attributes');

        if (empty($this->config[self::CONFIG_auth_source_primary])):
            throw new Error\Exception(\sprintf('No "%s" set in the filter configuration.', self::CONFIG_auth_source_primary));
        endif;

        if (!isset($state['Attributes'][$this->config[self::CONFIG_auth_source_secondary_match_field]])):
            throw new Error\Exception(\sprintf('Missing attribute "%s".', $this->config[self::CONFIG_auth_source_secondary_match_field]));
        endif;

        $secondaryValue = $state['Attributes'][$this->config[self::CONFIG_auth_source_secondary_match_field]];
        if(\is_array($secondaryValue)):
            $secondaryValue = \reset($secondaryValue);
        endif;

        $primaryAccounts = $this->getPrimaryAccounts($secondaryValue);
        if(count($primaryAccounts)<=0):
            Logger::debug(sprintf('No primary accounts found for "%s". Requesting primary account authentication...', $secondaryValue));

            $state[self::AUTHID] = $this->config[self::CONFIG_auth_source_primary];
            $state[self::AUTHID.'_data'] = [
                self::CONFIG_auth_source_primary_provider_name=>$this->config[self::CONFIG_auth_source_primary_provider_name],
                self::CONFIG_auth_source_secondary_match_value=>$secondaryValue,

            ];

            $id = $this->authState::saveState($state, self::STAGEID);
            $url = Module::getModuleURL('uab/primary-auth-notice');
            $this->httpUtils->redirectTrustedURL($url, ['StateId' => $id]);
            return;

        elseif(count($primaryAccounts)===1):
            $primaryValue = $primaryAccounts[0][self::CONFIG_auth_source_primary_match_value];
            Logger::debug(sprintf('One primary account map found for "%s": "%s". Authenticating primary account...', $secondaryValue, $primaryValue));

            
            return;
        else:
            Logger::debug(sprintf('%d primary accounts found for "%s". Presenting the primary account selection interface...', count($primaryAccounts), $secondaryValue));


        endif;
        echo("<pre>".print_r($primaryAccounts, true)."</pre>");

        // $authsourcePrimary = new $this->authSimple($this->config[self::CONFIG_auth_source_primary]);

        // echo("<pre>".print_r(get_class_methods($authsourcePrimary->getAuthSource()), true)."</pre>");
        // echo("<pre>".print_r(ConnectorFactory::fromAuthSource($authsourcePrimary->getAuthSource()->getAuthId()), true)."</pre>");

        die("bu");
        return; 

        // if (
        //     array_key_exists('Source', $state)
        //     && array_key_exists('entityid', $state['Source'])
        //     && in_array($state['Source']['entityid'], $this->ignoreEntities, true)
        // ) {
        //     Logger::debug('CardinalitySingle: Ignoring assertions from ' . $state['Source']['entityid']);
        //     return;
        // }

        // //$authsource = new $this->authSimple($as);

        // if (
        //     array_key_exists('Source', $state)
        //     && array_key_exists('entityid', $state['Source'])
        //     && in_array($state['Source']['entityid'], $this->ignoreEntities, true)
        // ) {
        //     Logger::debug('CardinalitySingle: Ignoring assertions from ' . $state['Source']['entityid']);
        //     return;
        // }

        // foreach ($state['Attributes'] as $k => $v) {
        //     if (!is_array($v)) {
        //         continue;
        //     }
        //     if (count($v) <= 1) {
        //         continue;
        //     }

        //     if (in_array($k, $this->singleValued)) {
        //         $state['core:cardinality:errorAttributes'][$k] = [count($v), '0 ≤ n ≤ 1'];
        //         continue;
        //     }
        //     if (in_array($k, $this->firstValue)) {
        //         $state['Attributes'][$k] = [array_shift($v)];
        //         continue;
        //     }
        //     if (in_array($k, $this->flatten)) {
        //         $state['Attributes'][$k] = [implode($this->flattenWith, $v)];
        //         continue;
        //     }
        // }

        // /* abort if we found a problematic attribute */
        // if (array_key_exists('core:cardinality:errorAttributes', $state)) {
        //     $id = Auth\State::saveState($state, 'core:cardinality');
        //     $url = Module::getModuleURL('core/error/cardinality');
        //     $this->httpUtils->redirectTrustedURL($url, ['StateId' => $id]);
        //     return;
        // }
    }

    protected function getPrimaryAccounts($secondaryValue){
        $result = $this->database::getInstance()->read("
            SELECT `".self::CONFIG_auth_source_primary_match_value."` 
            FROM `{$this->config[self::CONFIG_table_name]}` 
            WHERE `".self::CONFIG_auth_source_secondary_match_value."` = :secondaryValue
                AND `".self::CONFIG_auth_source_secondary_match_field."` = :secondaryField
                AND `".self::CONFIG_auth_source_secondary."` = :secondary
                AND `".self::CONFIG_auth_source_primary_match_field."` = :primaryField
                AND `".self::CONFIG_auth_source_primary."` = :primary
        ", [
            'secondaryValue' => (string) $secondaryValue,
            'secondaryField' => (string) $this->config[self::CONFIG_auth_source_secondary_match_field],
            'secondary' => (string) $this->config[self::CONFIG_auth_source_secondary],
            'primaryField' => (string) $this->config[self::CONFIG_auth_source_primary_match_field],
            'primary' => (string) $this->config[self::CONFIG_auth_source_primary],
        ]);

        return $result->fetchAll(\PDO::FETCH_ASSOC);
    }
}