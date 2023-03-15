<?php 

declare(strict_types=1);

namespace SimpleSAML\Module\uab\Controller;

use Exception;
use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\HTTP\RunnableResponse;
use SimpleSAML\Module;
use SimpleSAML\Module\uab\Auth\Source\MultiAuth;
use SimpleSAML\Session;
use SimpleSAML\Utils;
use SimpleSAML\XHTML\Template;;
use SimpleSAML\Assert\Assert;
use Symfony\Component\HttpFoundation\Request;
use SimpleSAML\Logger;

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

            $currentPasswordFormat = '%s_currentPassword';
            $confirmationPasswordFormat = '%s_confirmation';

            $returnUrl = $state['returnUrl'];
            if (!empty($returnUrl)):
                $returnUrl = $this->httpUtils->checkURLAllowed($returnUrl);
            endif;
            if (empty($returnTo)):
                $returnUrl = $this->config->getBasePath();
            endif;

            if(is_array($authsourceConfig)):
                $authsourceConfig = Configuration::loadFromArray($authsourceConfig);

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
                                        sprintf($confirmationPasswordFormat, $key) => [
                                            'label'=>'New Password (confirmation)',
                                            'description'=>'Reenter the new password again to confirm.',
                                            'edit'=>[
                                                'allow'=>true,
                                                'key'=>'password',
                                                'type' => 'password',
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
                                                                    "select"=>'.attribute-group.attribute-'.sprintf($confirmationPasswordFormat, $key),
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
                                            ],
                                            'view'=>[
                                                'allow'=>false,
                                            ],
                                        ],
                                        sprintf($currentPasswordFormat, $key) => [
                                            'label'=>'Current Password',
                                            'description'=>'Specify your current password to authenticate the request.',
                                            'edit'=>[
                                                'allow'=>true,
                                                'key'=>'password',
                                                'type' => 'password',
                                                'htmlType' => 'password',
                                                'classes' => 'hide-field expandable-element',
                                                
                                                'htmlAttributes' => [
                                                    'data-restrictions'=>json_encode([
                                                        "hide"=>[
                                                            "match"=>'.attribute-'.$key.'[value=""]',
                                                            "nested"=>[
                                                                [
                                                                    "select"=>'.attribute-group.attribute-'.sprintf($currentPasswordFormat, $key),
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
                                            ],
                                            'view'=>[
                                                'allow'=>false,
                                            ],
                                        ],
                                    ],
                                    array_slice($attributesToEdit, $currentPasswordIndex+1, null, true)
                                );

                                if(!isset($attributes[sprintf($currentPasswordFormat, $key)])):
                                    $attributes[sprintf($currentPasswordFormat, $key)] = [''];
                                endif;
                                if(!isset($attributes[sprintf($confirmationPasswordFormat, $key)])):
                                    $attributes[sprintf($confirmationPasswordFormat, $key)] = [''];
                                endif;
                                break;

                        endswitch;
                    endforeach;

                    $uid = $authsourceConfig->getOptionalString('uid.attribute', 'sAMAccountName');
                    if(!empty($attributes[$uid])):
                        $username = is_array($attributes[$uid])?reset($attributes[$uid]):(is_scalar($attributes[$uid])?$attributes[$uid]:'');
                    endif;
                else:
                    throw new Error\Error(\sprintf('Profile editing is disabled. Cannot proceed.'));
                endif;
            else:
                throw new Error\Error(\sprintf('Unable to load Multi-Authentication configuration. Cannot proceed.'));
            endif;

            $save = $request->getMethod() === 'POST' && !empty($request->request->get('save'));
            if($save):

                //serverInputValidation
                $request->request->all();
                echo("<pre>".print_r($request->request->all(), true)."</pre>");
                die("TODO: Save<pre>".print_r($attributesToEdit, true)."</pre>");
                

                $this->authState::deleteState($state);
                $savedProfileState = [
                    'success'=>true,
                    'username'=>$username??null,
                    'updatedFields'=>[],
                ];
                $stateId = $this->authState::saveState($savedProfileState, ProfileEdit::STATEID);
                $this->httpUtils->redirectTrustedURL($returnUrl, [
                    'ProfileSavedStateId' => $stateId,
                ]);
                die();
            endif;

        } catch (\Throwable $e) {
            switch(true):
                case $e instanceof Error\BadRequest: 
                    $message = $e->getReason();
                    break;
                default: 
                    $message = $e->getMessage();
            endswitch;
            $errors[]  = $message;
            Logger::debug(sprintf('An error occurred while editing the profile: "%s".', $message));
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
}