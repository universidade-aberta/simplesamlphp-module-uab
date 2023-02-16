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
            throw new \Exception('Could not find authentication source with id ' . $as);
        endif;

        $url = Module::getModuleURL('uab/hello/', []);
        $params = [
            'ErrorURL' => $url,
            'ReturnTo' => $url,
            Auth\State::RESTART => $url,
            //'ReturnCallback' => '',
        ];

        $authsource = new $this->authSimple($as);
        
        if (!is_null($request->query->get('logout'))):
            return new RunnableResponse([$authsource, 'logout'], [$params /*$this->config->getBasePath() . 'logout.php'*/]);
        elseif (!is_null($request->query->get(Auth\State::EXCEPTION_PARAM))):
            // This is just a simple example of an error
            /** @var array $state */
            $state = $this->authState::loadExceptionState();
            Assert::keyExists($state, Auth\State::EXCEPTION_DATA);
            throw $state[Auth\State::EXCEPTION_DATA];
        endif;

        if (!$authsource->isAuthenticated()) :
            return new RunnableResponse([$authsource, 'login'], [$params]);
        endif;

        $attributes = $authsource->getAttributes();
        $authData = $authsource->getAuthDataArray();
        $nameId = $authsource->getAuthData('uab:sp:NameID') ?? false;

        $httpUtils = new Utils\HTTP();
        $t = new Template($this->config, 'uab:status.twig');
        $l = $t->getLocalization();
        $l->addAttributeDomains();
        $t->data = [
            'attributes' => $attributes,
            'authData' => $authData,
            'remaining' => isset($authData['Expire']) ? $authData['Expire'] - time() : null,
            'nameid' => $nameId,
            'logouturl' => $httpUtils->getSelfURLNoQuery() . '?as=' . urlencode($as) . '&logout',
        ];

        return $t;
    }
}