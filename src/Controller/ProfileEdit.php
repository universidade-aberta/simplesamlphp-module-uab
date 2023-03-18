<?php 

declare(strict_types=1);

namespace SimpleSAML\Module\uab\Controller;

use Exception;
use Gettext\Translator;
use Gettext\TranslatorFunctions;
use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\HTTP\RunnableResponse;
use SimpleSAML\Locale\Localization;
use SimpleSAML\Locale\Translate;
use SimpleSAML\Module;
use SimpleSAML\Module\ldap\ConnectorInterface;
use SimpleSAML\Module\uab\Auth\Source\MultiAuth;
use SimpleSAML\Module\uab\ConnectorFactory;
use SimpleSAML\Session;
use SimpleSAML\Utils;
use SimpleSAML\XHTML\Template;;
use SimpleSAML\Assert\Assert;
use Symfony\Component\HttpFoundation\Request;
use SimpleSAML\Logger;
use Symfony\Component\Ldap\Adapter\ExtLdap\Query;
use Symfony\Component\Ldap\Exception\ConnectionException;

class ProfileEdit{
    const ERROR_CODE_CUSTOM = -31171;
    public static $failuredMessages = [
        'title'=>[
            'EMPTY_USERNAME_OR_PASSWORD'=>'Empty username or password',
            'EXPIRED_PASSWORD'=>'Expired password',
            'LDAP_CONNECTION_FAILURE'=>'Server Connection Failure',
        ],
        'descr'=>[
            'EMPTY_USERNAME_OR_PASSWORD'=>'You must provide an username and password to login',
            'EXPIRED_PASSWORD'=>'Your password has expired. Please, consult the documentation of contact technical support for information on how to reset your password.',
            'LDAP_CONNECTION_FAILURE'=>'The connection to the authentication server failed so we are unable to confirm your credentials. Please, try again later or contact technical support if the issue persists.',
        ]
    ];
    /**
     * The key of the AuthId field in the state.
     */
    public const STATEID = __CLASS__.'.StateID';

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
     * This controller shows a list of authentication sources. When the user selects
     * one of them if pass this information to the
     * \SimpleSAML\Module\multiauth\Auth\Source\MultiAuth class and call the
     * delegateAuthentication method on it.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \SimpleSAML\XHTML\Template|\SimpleSAML\HTTP\RunnableResponse
     *   An HTML template or a redirection if we are not authenticated.
     */
    public function edit(Request $request){

        $errors = [];
        try{
            $authStateId = $request->query->get('StateId');
            if(empty($authStateId)):
                throw new Error\BadRequest(\sprintf('Missing state ID. Cannot proceed.'));
            endif;

            $state = $this->authState::loadState($authStateId.":".$this->getBaseUrl(), self::STATEID, true);
            
            if(empty($state)):
                throw new Error\BadRequest(\sprintf('Missing state data. Cannot proceed.'));
            endif;

            $authId = $state['AuthID']??null;
            if(empty($authId)):
                throw new Error\BadRequest(\sprintf('Missing authentication ID. Cannot proceed.'));
            endif;

            $authsource = new $this->authSimple($authId);
            if (!$authsource->isAuthenticated()) :
                throw new Error\BadRequest(\sprintf('Not authenticated. Cannot proceed.'));
            endif;

            $attributes = $authsource->getAttributes();
            $authData = $authsource->getAuthDataArray();
            $links = $this->config->getOptionalArray('uab:loginpage_links', []);
            $attributesToEdit = [];
            $authsourceConfig = MultiAuth::loadConfig($authId);
            $username = $state['username'];

            $metaFields = [];

            $returnUrl = $state['returnUrl'];
            if (!empty($returnUrl)):
                $returnUrl = $this->httpUtils->checkURLAllowed($returnUrl);
            endif;
            if (empty($returnTo)):
                $returnUrl = $this->config->getBasePath();
            endif;

            if(is_array($authsourceConfig)):
                $authsourceConfig = Configuration::loadFromArray($authsourceConfig);

                if(empty($editAuthSourceId = $authsourceConfig->getOptionalString('uab.profile.edit.source', null))):
                    throw new Error\Error(\sprintf('No profile editing source defined. Cannot proceed.'));
                endif;

                try{
                    if(empty($authsourceEditConfig = Configuration::loadFromArray(MultiAuth::loadConfig($editAuthSourceId)))):
                        throw new Error\Error(\sprintf('Unable load the configuration for "%s"', $editAuthSourceId));
                    endif;
                    if(empty($sourceConnector = ConnectorFactory::fromAuthSource($editAuthSourceId))):
                        throw new Error\Error(\sprintf('Unable create a connector source from %s', $editAuthSourceId));
                    endif;
                }catch(\Throwable $ex){
                    Logger::debug($ex->getMessage());
                    throw new Error\Error(\sprintf('Unable to create a connector from the authentication source. Cannot proceed.'));
                }
                
                //$editAuthSource = new $this->authSimple($editAuthSourceId);
                // $editAuthSource->requireAuth();
                // if (!$editAuthSource->isAuthenticated()) :
                //     throw new Error\BadRequest(\sprintf('Not authenticated. Cannot proceed.'));
                // endif;

                if($authsourceConfig->getOptionalBoolean('uab.profile.edit.enabled', false)):
                    $attributesToEdit = array_filter($authsourceConfig->getOptionalArray('uab.profile.attributes', array_keys($attributes)), function($attribute, $key) use (&$attributes) {
                        if(!isset($attributes[$key])):
                            $attributes[$key] = [''];
                        endif;
                        return ($attribute['edit']['allow']??false)==true;
                    }, ARRAY_FILTER_USE_BOTH);

                    if(count($attributesToEdit)<=0):
                        throw new Error\Error(\sprintf('No attributes available for editing. Cannot proceed.'));
                    endif;
                    
                    $attributes = array_filter($attributes, function($attribute, $key) use ($attributesToEdit){
                        return isset($attributesToEdit[$key]);
                    }, ARRAY_FILTER_USE_BOTH);

                    
                    $uid = $authsourceConfig->getOptionalString('uid.attribute', 'sAMAccountName');
                    if(!empty($attributes[$uid])):
                        $username = is_array($attributes[$uid])?reset($attributes[$uid]):(is_scalar($attributes[$uid])?$attributes[$uid]:'');
                    endif;

                    $this->addCustomValidationAttributes($attributesToEdit, $attributes, $request, $metaFields, $username, $sourceConnector, $authsourceEditConfig);
                else:
                    throw new Error\Error(\sprintf('Profile editing is disabled. Cannot proceed.'));
                endif;
            else:
                throw new Error\Error(\sprintf('Unable to load Multi-Authentication configuration. Cannot proceed.'));
            endif;

            $save = $request->getMethod() === 'POST' && !empty($request->request->get('save'));
            if($save && empty($errors)):

                $dataToSave = $this->validateSubmittedAttributes($errors, $attributesToEdit, $request, $metaFields);
                if(empty($errors) && !empty($dataToSave)):
                    $this->save($errors, $dataToSave);
                    $this->authState::deleteState($state);
                    $savedProfileState = [
                        'success'=>true,
                        'username'=>$username??null,
                        'updatedFields'=>array_keys($dataToSave),
                    ];
                    $stateId = $this->authState::saveState($savedProfileState, ProfileEdit::STATEID);
                    $this->httpUtils->redirectTrustedURL($returnUrl, [
                        'ProfileSavedStateId' => $stateId,
                    ]);
                endif;

                foreach($attributes as $key=>$attribute):
                    $attributes[$key]=$request->request->get($key);
                endforeach;
            endif;

        } catch (\Throwable $e) {
            switch(true):
                case $e instanceof Error\BadRequest: 
                    $message = $e->getReason();
                    break;
                default: 
                    $message = $e->getMessage();
            endswitch;
            $errors['_general_'] = $message;
            Logger::debug(sprintf('An error occurred while editing the profile: "%s".', $message));
            // throw $e;
        }

        $t = new Template($this->config, 'uab:profile-edit.twig');
        $t->data = [
            'StateId'=>$authStateId,
            'submitUrl'=>$this->httpUtils->getSelfURL(),
            'returnUrl'=>$returnUrl??Module::getModuleURL('uab/hello'),
            'username'=>$username??null,
            'name'=>$state['name']??null,
            'links'=>$links??[],
            'attributes'=>$attributes??[],
            'attributesToEdit'=>$attributesToEdit??[],
            'errors'=>$errors??[],
        ];

        return $t;
    }

    protected function getBaseUrl():string{
        return $this->httpUtils->getBaseURL();
    }

    protected static function getCurrentPasswordKey(string $key):string{
        return sprintf('%s_currentPassword', $key);
    }

    protected static function getConfirmationPasswordKey(string $key):string{
        return sprintf('%s_confirmationPassword', $key);
    }

    protected function addCustomValidationAttributes(array &$attributesToEdit, array &$attributes, Request $request, array &$metaFields, string $username, ConnectorInterface $sourceConnector, Configuration $authsourceEditConfig){
        $attributesToEditCache = [...$attributesToEdit];
        
        foreach($attributesToEditCache as $key=>$attribute):
            switch(strtolower($attribute['edit']['type']??null)):
                case 'password':
                    $currentPasswordIndex = array_search($key, array_keys($attributesToEdit));
                    $attributesToEdit = array_merge(
                        array_slice($attributesToEdit, 0, $currentPasswordIndex, true),
                        [
                            $key=>[
                                ...$attribute,
                                'label'=>'New Password',
                                'description'=>'Specify a new password if you want to change it. Leave it blank to keep the current password.',
                            ],
                            self::getConfirmationPasswordKey($key) => [
                                'label'=>'New Password (confirmation)',
                                'description'=>'Reenter the new password again to confirm.',
                                'edit'=>[
                                    'allow'=>true,
                                    'key'=>'password',
                                    'type' => 'confirmationPassword',
                                    'htmlType' => 'password',
                                    'classes' => 'hide-field expandable-element',
                                    'htmlAttributes' => [
                                        'data-restrictions'=>json_encode([
                                            "hide"=>[
                                                "match"=>'.attribute-'.$key.'[value=""]',
                                                // "if"=>[
                                                //     "true"=>[
                                                //         "class"=>"cpois-sim",
                                                //         "data-class"=>"dpois-sim",
                                                //     ],
                                                //     "false"=>[
                                                //         "class"=>"cpois-nao",
                                                //     ],
                                                // ],
                                                "nested"=>[
                                                    [
                                                        "select"=>'.attribute-group.attribute-'.self::getConfirmationPasswordKey($key),
                                                        "if"=>[
                                                            "true"=>[
                                                                "class"=>"hide-field",
                                                                "aria-hidden"=>"true",
                                                            ],
                                                            "false"=>[
                                                                "class"=>"show-field",
                                                                "aria-hidden"=>"false",
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ]),
                                    ],
                                    'serverInputValidation'=>[
                                        'filter' => FILTER_CALLBACK,
                                        'flags' => FILTER_REQUIRE_ARRAY, 
                                        'options' => function($value) use ($key, $request){ 
                                            $errors = [];

                                            $newPassword = $request->request->get($key);
                                            if($value !== (is_array($newPassword)?reset($newPassword):$newPassword)):
                                                $errors[] = [
                                                    'en'=>'The confirm password does not match the new password',
                                                    'pt'=>'A palavra-passe de confirmação não coincide com a nova palavra-passe',
                                                ];
                                            endif;

                                            if(!empty($errors)):
                                                throw new class($errors) extends Exception{
                                                    protected $errors = [];
                                                    public function __construct(array $errors, $message='', $code = 0, Exception $previous = null) {
                                                        $this->errors = $errors;
                                                        parent::__construct($message, $code, $previous);
                                                    }
                
                                                    public function getErrors():array{
                                                        return $this->errors;
                                                    }
                                                };
                                            endif;
                                            return $value;
                                        },
                                    ],
                                ],
                                'view'=>[
                                    'allow'=>false,
                                ],
                            ],
                        ],
                        array_slice($attributesToEdit, $currentPasswordIndex+1, null, true),
                        [
                            self::getCurrentPasswordKey($key) => [
                                'label'=>'Current Password',
                                'description'=>'Specify your current password to authenticate the request.',
                                'edit'=>[
                                    'allow'=>true,
                                    'key'=>'password',
                                    'type' => 'currentPassword',
                                    'htmlType' => 'password',
                                    'classes' => 'hide-field expandable-element',
                                    
                                    'htmlAttributes' => [
                                        'data-restrictions'=>json_encode([
                                            "hide"=>[
                                                "match"=>'.attribute-'.$key.'[value=""]',
                                                "nested"=>[
                                                    [
                                                        "select"=>'.attribute-group.attribute-'.self::getCurrentPasswordKey($key),
                                                        "if"=>[
                                                            "true"=>[
                                                                "class"=>"hide-field",
                                                                "aria-hidden"=>"true",
                                                            ],
                                                            "false"=>[
                                                                "class"=>"show-field",
                                                                "aria-hidden"=>"false",
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ]),
                                    ],
                                    'serverInputValidation'=>[
                                        'filter' => FILTER_CALLBACK,
                                        'flags' => FILTER_REQUIRE_ARRAY, 
                                        'options' => function($value) use ($username, $sourceConnector, $authsourceEditConfig, $attributesToEdit){ 
                                            $errors = [];

                                            if(!empty(array_filter($attributesToEdit, function($attribute){
                                                return $attribute['edit']['requireAuthForUpdate']??false;
                                            }))):
                                                try{
                                                    self::bind($username, $value, $authsourceEditConfig, $sourceConnector);
                                                    return $value;
                                                }catch(\Throwable $ex){
                                                    $errors[] = [
                                                        'en'=>'Unable to authenticate with the current password given.',
                                                        'pt'=>'Não foi possível autenticar com a palavra-passe atual fornecida.',
                                                    ];
                                                }
                                            endif;

                                            if(!empty($errors)):
                                                throw new class($errors) extends Exception{
                                                    protected $errors = [];
                                                    public function __construct(array $errors, $message='', $code = 0, Exception $previous = null) {
                                                        $this->errors = $errors;
                                                        parent::__construct($message, $code, $previous);
                                                    }
                
                                                    public function getErrors():array{
                                                        return $this->errors;
                                                    }
                                                };
                                            endif;
                                            return $value;
                                        },
                                    ],
                                ],
                                'view'=>[
                                    'allow'=>false,
                                ],
                            ],
                        ],
                    );

                    if(!isset($attributes[self::getCurrentPasswordKey($key)])):
                        $attributes[self::getCurrentPasswordKey($key)] = [''];
                    endif;
                    if(!isset($attributes[self::getConfirmationPasswordKey($key)])):
                        $attributes[self::getConfirmationPasswordKey($key)] = [''];
                    endif;

                    $metaFields[] = self::getCurrentPasswordKey($key);
                    $metaFields[] = self::getConfirmationPasswordKey($key);

                    break;
            endswitch;
        endforeach;
    }

    protected function validateSubmittedAttributes(array &$errors, array &$attributesToEdit, Request $request, $metaFields):array{
        if(!empty($errors)):
            return $errors;
        endif;
        
        $dataToSave = [];
        
        $currentLanguage = (new Translate($this->config))->getLanguage()->getLanguage();
        $localization = new Localization($this->config);
        $localization->addModuleDomain('uab');
        $translator = $localization->getTranslator();
        TranslatorFunctions::register($translator);

        $translateFromString = [Translate::class, 'translateSingularGettext'];
        $translateFromArray = function(array $translations) use ($currentLanguage){
            return ([Translate::class, 'translateFromArray'])([
                'currentLanguage'=>$currentLanguage,
            ], $translations);
        };
        $translateLabel = function($attributeLabel, $attributeName) use($translateFromArray, $translateFromString){
            $label = $attributeName;
            if(!empty($attributeLabel)):
                if(is_array($attributeLabel)):
                    $label = ($translateFromArray($attributeLabel));
                elseif(is_scalar($attributeLabel)):
                    $label = ($translateFromString($attributeLabel));
                endif;
            endif;
            return $label;
        };

        foreach($attributesToEdit as $attributeName=>$attribute):

            $label = $translateLabel($attribute['label']??null, $attributeName);
            if(empty($attribute['edit']['allow']??null) || $attribute['edit']['allow']!=true):
                $errors[$attributeName] = sprintf($translateFromString('The field "%s" is not editable'), $label);

                continue;
            endif;

            if($attribute['edit']['required']??null == true && empty($request->request->get($attributeName)) ):
                $errors[$attributeName] = sprintf($translateFromString('The field "%s" is required'), $label);

                continue;
            elseif(!empty($attribute['edit']['serverInputValidation'])):

                $options = [];
                foreach(['options', 'flags'] as $optionName):
                    if(!empty($attribute['edit']['serverInputValidation'][$optionName]??null)):
                        $options[$optionName] = $attribute['edit']['serverInputValidation'][$optionName];
                    endif;
                endforeach;

                try{
                    if(($validationResult = $request->request->filter($attributeName, null, $attribute['edit']['serverInputValidation']['filter']??FILTER_DEFAULT, $options))===false):
                        $errors[$attributeName] = sprintf($translateFromString('The field "%s" value is invalid. Please, check the field value requirements and try again.'), $label);

                        continue;
                    elseif($attribute['edit']['required']??null == true && empty($validationResult) ):
                        $errors[$attributeName] = sprintf($translateFromString('The field "%s" is required but resulting value after validation is empty. Please, check the field value requirements and try again.'), $label);
        
                        continue;
                    else:
                        //if(!in_array($attributeName, $metaFields)):
                            $dataToSave[$attributeName] = $validationResult;
                        //endif;
                    endif;
                } catch (\Throwable $e) {
                    if(method_exists($e, 'getErrors')):
                        if(is_array($eErrors = $e->getErrors())):
                            $errors[$attributeName] = sprintf($translateFromString('Validation error for field "%s": %s.'), $label, "\n".implode("; \n", array_map(function($error) use($translateLabel, $attributeName) {
                                return $translateLabel($error??null, $attributeName);
                            }, $eErrors)));
                        else:
                            $errors[$attributeName] = $e->getErrors();
                        endif;
                    else:
                        $errors[$attributeName] = $e->getMessage();
                    endif;

                    continue;
                }

            endif;

        endforeach;

        if(!empty($errors)):
            return [];
        endif;

        return $dataToSave;
    }



    /**
     * Attempt to log in using the given username and password.
     *
     * @param string $username  The username the user wrote.
     * @param string $password  The password the user wrote.
     * @param Configuration $ldapConfig LDAP Configuration
     * @param ConnectorInterface $connector LDAP Connector to use
     * @return bool True on sucess, exception on error
     * 
     * @see \SimpleSAML\Module\uab\Auth\Source\Ldap::login
     */
    protected static function bind(string $username, string $password, Configuration $ldapConfig, ConnectorInterface $connector): bool {
        $searchScope = $ldapConfig->getOptionalString('search.scope', Query::SCOPE_SUB);
        Assert::oneOf($searchScope, [Query::SCOPE_BASE, Query::SCOPE_ONE, Query::SCOPE_SUB]);

        $timeout = $ldapConfig->getOptionalInteger('timeout', 3);
        Assert::natural($timeout);

        $searchBase = $ldapConfig->getArray('search.base');
        $options = [
            'scope' => $searchScope,
            'timeout' => $timeout,
        ];

        $searchEnable = $ldapConfig->getOptionalBoolean('search.enable', false);
        if ($searchEnable === false) {
            $dnPattern = $ldapConfig->getString('dnpattern');
            $dn = str_replace('%username%', $username, $dnPattern);
        } else {
            $searchUsername = $ldapConfig->getString('search.username');
            Assert::notWhitespaceOnly($searchUsername);

            $searchPassword = $ldapConfig->getOptionalString('search.password', null);
            Assert::nullOrnotWhitespaceOnly($searchPassword);

            try {
                $connector->bind($searchUsername, $searchPassword);
            } catch (ConnectionException $e) {
                throw new Error\Error('LDAP_CONNECTION_FAILURE');
            } catch (Error\Exception $e) {
                if($e->getCode() !== self::ERROR_CODE_CUSTOM):
                    Logger::debug(sprintf('An error occurred on LDAP authentication: "%s".', $e->getMessage()));
                    throw new Error\Error('WRONGUSERPASS');
                endif;
                throw new Error\Error($e->getMessage());
            }

            $filter = static::buildSearchFilter($username, $ldapConfig);

            try {
                $entry = /** @scrutinizer-ignore-type */$connector->search($searchBase, $filter, $options, false);

                $dn = $entry->getDn();
                $entry = null;
            } catch (Error\Exception $e) {
                if($e->getCode() !== self::ERROR_CODE_CUSTOM):
                    Logger::debug(sprintf('An error occurred on LDAP authentication: "%s".', $e->getMessage()));
                    throw new Error\Error('WRONGUSERPASS');
                endif;
                throw new Error\Error($e->getMessage());
            }
        }

        try {
            $connector->bind($dn, $password);

            $options['scope'] = Query::SCOPE_BASE;
            $filter = '(objectClass=*)';

            $entry = $connector->search([$dn], $filter, $options, false);
            
        } catch (Error\Exception $e) {
            throw new Error\Error('WRONGUSERPASS');
        }

        return true;
    }


    /**
     * @param string $username
     * @param Configuration $ldapConfig LDAP Configuration
     * 
     * @see \SimpleSAML\Module\uab\Auth\Source\Ldap::login
     */
    protected static function buildSearchFilter(string $username, Configuration $ldapConfig): string
    {
        $searchAttributes = $ldapConfig->getArray('search.attributes');
        /** @psalm-var string|null $searchFilter */
        $searchFilter = $ldapConfig->getOptionalString('search.filter', null);

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

    protected function save($errors, $dataToSave):bool {

        die("TODO: Save<pre>".print_r($dataToSave, true)."</pre>");

        return false;
    }


}