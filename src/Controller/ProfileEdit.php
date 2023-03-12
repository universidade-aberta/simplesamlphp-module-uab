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

class ProfileEdit{
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
        $authStateId = $request->query->get('StateId');
        if(empty($authStateId)):
            throw new Error\Exception(\sprintf('Missing state ID. Cannot proceed.'));
        endif;

        $state = $this->authState::loadState($authStateId.":".$this->getBaseUrl(), self::STATEID, true);
        if(empty($state)):
            throw new Error\Exception(\sprintf('Missing state data. Cannot proceed.'));
        endif;

        $authId = $state['AuthID']??null;
        if(empty($authId)):
            throw new Error\Exception(\sprintf('Missing auth ID. Cannot proceed.'));
        endif;

        $authsource = new $this->authSimple($authId);
        if (!$authsource->isAuthenticated()) :
            throw new Error\Exception(\sprintf('Not authenticated. Cannot proceed.'));
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
                    throw new Error\Exception(\sprintf('No attributes available for editing. Cannot proceed.'));
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
                                    sprintf($currentPasswordFormat, $key) => [
                                        'label'=>'Current Password',
                                        'edit'=>[
                                            'allow'=>true,
                                            'key'=>'password',
                                            'type' => 'password',
                                            'htmlType' => 'password',
                                            'classes' => '',
                                            'htmlAttributes' => [],
                                        ],
                                        'view'=>[
                                            'allow'=>false,
                                        ],
                                    ],
                                    $key=>$attribute,
                                    sprintf($confirmationPasswordFormat, $key) => [
                                        'label'=>'Password (confirmation)',
                                        'edit'=>[
                                            'allow'=>true,
                                            'key'=>'password',
                                            'type' => 'password',
                                            'htmlType' => 'password',
                                            'classes' => '',
                                            'htmlAttributes' => [],
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
                throw new Error\Exception(\sprintf('Profile editing is disabled. Cannot proceed.'));
            endif;
        else:
            throw new Error\Exception(\sprintf('Unable to load MultiAuth configuration. Cannot proceed.'));
        endif;

        $errors = [];
        $save = $request->getMethod() === 'POST' && !empty($request->request->get('save'));
        if($save):
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

        $t = new Template($this->config, 'uab:profile-edit.twig');
        $t->data = [
            'username'=>$username??null,
            'name'=>$state['name']??null,
            'returnUrl'=>$returnUrl??null,
            'links'=>$links??[],
            'attributes'=>$attributes,
            'attributesToEdit'=>$attributesToEdit??[],
            'errors'=>$errors,
        ];

        return $t;
    }

    protected function getBaseUrl():string{
        return $this->httpUtils->getBaseURL();
    }
}