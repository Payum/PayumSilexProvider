<?php
namespace Payum\Silex\Action;

use Payum\Core\Bridge\Symfony\Action\GetHttpRequestAction as BaseGetHttpRequestAction;
use Silex\Application;

class GetHttpRequestAction extends BaseGetHttpRequestAction
{
    /**
     * @var Application
     */
    private $app;

    /**
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * {@inheritDoc}
     */
    public function execute($request)
    {
        $this->httpRequest = $this->app['request'];

        parent::execute($request);
    }
}
