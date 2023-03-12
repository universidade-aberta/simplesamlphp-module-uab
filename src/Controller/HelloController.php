<?php 

declare(strict_types=1);

namespace SimpleSAML\Module\uab\Controller;

use Exception;
use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\HTTP\RunnableResponse;
use SimpleSAML\Module;
use SimpleSAML\Module\uab\Auth\Source\MultiAuth;
use SimpleSAML\Session;
use SimpleSAML\Utils;
use SimpleSAML\XHTML\Template;;
use SimpleSAML\Assert\Assert;
use Symfony\Component\HttpFoundation\Request;

class HelloController{
    const DEFAULT_AUTHENTICATOR = 'UAb.defaultAuthenticator';

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

        if (!$config->hasValue(self::DEFAULT_AUTHENTICATOR)) {
            throw new Exception('The required "'.self::DEFAULT_AUTHENTICATOR.'" config option was not found');
        }
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
     * This controller shows a list of authentication sources. When the user selects
     * one of them if pass this information to the
     * \SimpleSAML\Module\multiauth\Auth\Source\MultiAuth class and call the
     * delegateAuthentication method on it.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \SimpleSAML\XHTML\Template|\SimpleSAML\HTTP\RunnableResponse
     *   An HTML template or a redirection if we are not authenticated.
     */
    public function discovery(Request $request){
        $as =  $this->config->getValue(self::DEFAULT_AUTHENTICATOR);
        if ($as === null):
            throw new Exception('Could not find authentication source with id ' . $as);
        endif;

        $url = Module::getModuleURL('uab/hello/', []);
        $params = [
            'ErrorURL' => $url,
            'ReturnTo' => $url,
            $this->authState::RESTART => $url,
            //'ReturnCallback' => '',
        ];

        $authsource = new $this->authSimple($as);
        
        if (!is_null($request->query->get('logout'))):
            return new RunnableResponse([$authsource, 'logout'], [$params /*$this->config->getBasePath() . 'logout.php'*/]);
        elseif (!is_null($request->query->get($this->authState::EXCEPTION_PARAM))):
            /** @var array $state */
            $state = $this->authState::loadExceptionState();
            Assert::keyExists($state, $this->authState::EXCEPTION_DATA);
            throw $state[$this->authState::EXCEPTION_DATA];
        endif;

        if (!$authsource->isAuthenticated()) :
            return new RunnableResponse([$authsource, 'login'], [$params]);
        endif;

        $attributes = $authsource->getAttributes();
        $authData = $authsource->getAuthDataArray();
        $nameId = $authsource->getAuthData('uab:sp:NameID') ?? false;

        $links = $this->config->getOptionalArray('uab:loginpage_links', []);
        $attributesToShow = array_keys($attributes);
        $authsourceConfig = MultiAuth::loadConfig($authsource->getAuthSource()->getAuthId());
        $username = '';
        $name = '';
        $adminLinks = [];
        $profileEditUrl = null;
        if(is_array($authsourceConfig)):
            $authsourceConfig = Configuration::loadFromArray($authsourceConfig);

            $attributesToShow = array_filter($authsourceConfig->getOptionalArray('uab.profile.attributes', $attributesToShow), function($attribute){
                return ($attribute['view']['allow']??true)==true;
            });

            $uid = $authsourceConfig->getOptionalString('uid.attribute', 'sAMAccountName');
            if(!empty($attributes[$uid])):
                $username = is_array($attributes[$uid])?reset($attributes[$uid]):(is_scalar($attributes[$uid])?$attributes[$uid]:'');
            endif;

            $uid = $authsourceConfig->getOptionalValue('uid.name', 'displayName');
            $name = is_scalar($uid) && empty($attributes[$uid])?$attributes[$uid]:(is_callable($uid)?call_user_func($uid, $attributes):'');

            $adminLinks = $authsourceConfig->getOptionalArray('uab.admin.links', []);
            if($authsourceConfig->getOptionalBoolean('uab.profile.edit.enabled', false)):
                $editProfileState = [
                    'AuthID'=>$authsource->getAuthSource()->getAuthId(),
                    'returnUrl'=>$url,
                    'username'=>$username,
                    'name'=>$name,
                ];
                $stateId = $this->authState::saveState($editProfileState, ProfileEdit::STATEID);

                $profileEditUrl = Module::getModuleURL('uab/edit-profile', [
                    'StateId' => $stateId,
                ]);
            endif;
        endif;

        $httpUtils = new Utils\HTTP();
        $t = new Template($this->config, 'uab:profile-view.twig');
        $l = $t->getLocalization();
        $l->addAttributeDomains();
        $t->data = [
            'attributes' => $attributes,
            'authData' => $authData,
            'remaining' => isset($authData['Expire']) ? $authData['Expire'] - time() : null,
            'nameid' => $nameId,
            'logouturl' => $httpUtils->getSelfURLNoQuery() . '?as=' . urlencode($as) . '&logout',
            'links'=>$links??null,
            'adminLinks'=>$adminLinks,
            'attributesToShow'=>$attributesToShow,
            'username'=>$username,
            'name'=>$name,
            'profileEditUrl'=>$profileEditUrl,
        ];

        return $t;
    }
}