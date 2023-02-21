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
    public function primaryAuthNotice(Request $request): Template{
        $authStateId = $request->query->get('StateId');
        $state = $this->authState::loadState($authStateId, self::STAGEID);
        Assert::keyExists($state, self::AUTHID);
        if (empty($state[self::AUTHID])):
            throw new \Exception('Invalid state.');
        endif;

        $stateData = $state[self::AUTHID.'_data'];

        $t = new Template($this->config, 'uab:primary-auth-notice.twig');
        $t->data['StateId'] = $authStateId;
        $t->data['MainAccountProvider'] = $stateData[UserMatch::CONFIG_auth_source_primary_provider_name]??'Main';
        $t->data['SecondaryAccount'] = $stateData[UserMatch::CONFIG_auth_source_secondary_match_value]??''; //@TODO: UAb

        return $t;
    }

    /**
     * This controller shows a notice to inform the user that he/she must authenticate with the primary credentials to associate the primary with the secondary account
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \SimpleSAML\XHTML\Template|\SimpleSAML\HTTP\RunnableResponse
     *   An HTML template
     */
    public function primaryAuth(Request $request): Template{
        if ($request->getMethod() === 'POST' && !empty($request->request->get('continue'))):

            die("reencaminhar para o início de sessão".$request->request->get('continue'));

                //return new RunnableResponse([$httpUtils, 'redirectTrustedURL'], [$returnTo]);
        endif;
        die("terminar sessão".$request->query->get('cancel'));
        // $authStateId = $request->query->get('StateId');
        // $state = $this->authState::loadState($authStateId, self::STAGEID);
        // Assert::keyExists($state, self::AUTHID);
        // if (empty($state[self::AUTHID])):
        //     throw new \Exception('Invalid state.');
        // endif;

        // $t = new Template($this->config, 'uab:primary-auth-notice.twig');
        // $t->data['StateId'] = $authStateId;
        // $t->data['MainAccountProvider'] = 'UAb'; //@TODO: UAb
        // $t->data['SecondaryAccount'] = ''; //@TODO: UAb

        

        // return $t;
    }
}