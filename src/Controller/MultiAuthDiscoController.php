<?php

declare(strict_types=1);

namespace SimpleSAML\Module\uab\Controller;

use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\HTTP\RunnableResponse;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Module\uab\Auth\Source\MultiAuth;
use SimpleSAML\Session;
use SimpleSAML\Utils;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller class for the multiauth module.
 *
 * This class serves the different views available in the module.
 *
 * @package SimpleSAML\Module\uab
 */
class MultiAuthDiscoController
{
    /** @var \SimpleSAML\Configuration */
    protected Configuration $config;

    /** @var \SimpleSAML\Session */
    protected Session $session;

    /**
     * @var \SimpleSAML\Auth\Source|string
     * @psalm-var \SimpleSAML\Auth\Source|class-string
     */
    protected $authSource = Auth\Source::class;

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
    public function __construct(
        Configuration $config,
        Session $session
    ) {
        $this->config = $config;
        $this->session = $session;
    }


    /**
     * Inject the \SimpleSAML\Auth\Source dependency.
     *
     * @param \SimpleSAML\Auth\Source $authSource
     */
    public function setAuthSource(Auth\Source $authSource): void
    {
        $this->authSource = $authSource;
    }


    /**
     * Inject the \SimpleSAML\Auth\State dependency.
     *
     * @param \SimpleSAML\Auth\State $authState
     */
    public function setAuthState(Auth\State $authState): void
    {
        $this->authState = $authState;
    }


    /**
     * This controller shows a list of authentication sources. When the user selects
     * one of them if pass this information to the
     * \SimpleSAML\Module\uab\Auth\Source\MultiAuth class and call the
     * delegateAuthentication method on it.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @return \SimpleSAML\XHTML\Template|\SimpleSAML\HTTP\RunnableResponse
     *   An HTML template or a redirection if we are not authenticated.
     */
    public function discovery(Request $request)
    {
        // Retrieve the authentication state
        $authStateId = $request->query->get('AuthState', null);
        if (is_null($authStateId)) {
            throw new Error\BadRequest('Missing AuthState parameter.');
        }

        $state = Auth\State::loadState($authStateId, MultiAuth::STAGEID);

        $as = null;
        if (array_key_exists("\SimpleSAML\Auth\Source.id", $state)) {
            $authId = $state["\SimpleSAML\Auth\Source.id"];

            /** @var \SimpleSAML\Module\uab\Auth\Source\MultiAuth $as */
            $as = Auth\Source::getById($authId);
        }

        // Get a preselected source either from the URL or the discovery page
        $urlSource = $request->get('source', null);
        $discoSource = $request->get('sourceChoice', null);

        if ($urlSource !== null) {
            $selectedSource = $urlSource;
        } elseif ($discoSource !== null) {
            $selectedSource = array_key_first($discoSource);
        }

        if (isset($selectedSource)) {
            if ($as !== null) {
                $as->setPreviousSource($selectedSource);
            }
            return MultiAuth::delegateAuthentication($selectedSource, $state);
        }

        if (array_key_exists('multiauth:preselect', $state)) {
            $source = $state['multiauth:preselect'];
            return MultiAuth::delegateAuthentication($source, $state);
        }

        $t = new Template($this->config, 'uab:selectsource.twig');

        $t->data['authstate'] = $authStateId;
        $t->data['sources'] = $state[MultiAuth::SOURCESID];
        $t->data['preferred'] = is_null($as) ? null : $as->getPreviousSource();
        return $t;
    }
}
