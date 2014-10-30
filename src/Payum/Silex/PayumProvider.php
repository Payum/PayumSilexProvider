<?php
namespace Payum\Silex;

use Payum\Core\Bridge\Symfony\ReplyToSymfonyResponseConverter;
use Payum\Core\Bridge\Symfony\Security\HttpRequestVerifier;
use Payum\Core\Bridge\Symfony\Security\TokenFactory;
use Payum\Core\Registry\SimpleRegistry;
use Payum\Core\Reply\ReplyInterface;
use Payum\Silex\Controller\AuthorizeController;
use Payum\Silex\Controller\CaptureController;
use Payum\Silex\Controller\NotifyController;
use Payum\Silex\Controller\RefundController;
use Silex\Application;
use Silex\ServiceProviderInterface;

class PayumProvider implements ServiceProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function register(Application $app)
    {
        $this->registerService($app);
        $this->registerControllers($app);
        $this->registerListeners($app);
    }

    /**
     * {@inheritDoc}
     */
    public function boot(Application $app)
    {
    }

    /**
     * @param Application $app
     */
    protected function registerService(Application $app)
    {
        $app['payum.security.token_storage'] = $app->share(function() {
            throw new \LogicException('This service has to be overwritten. Check the example in the doc at payum.org');
        });

        $app['payum.reply_to_symfony_response_converter'] = $app->share(function($app) {
            return new ReplyToSymfonyResponseConverter();
        });

        $app['payum.security.http_request_verifier'] = $app->share(function($app) {
            return new HttpRequestVerifier($app['payum.security.token_storage']);
        });

        $app['payum.security.token_factory'] = $app->share(function($app) {
            return new TokenFactory(
                $app['url_generator'],
                $app['payum.security.token_storage'],
                $app['payum'],
                'payum_capture_do',
                'payum_notify_do',
                'payum_authorize_do'
            );
        });

        $app['payum.payments'] = $app->share(function () {
            return [
                // name => instance of PaymentInterface
            ];
        });

        $app['payum.storages'] = $app->share(function ($app) {
            return [
                // modelClass => instance of StorageInterface
            ];
        });

        $app['payum'] = $app->share(function($app) {
            return new SimpleRegistry($app['payum.payments'], $app['payum.storages'], null, null);
        });
    }

    /**
     * @param Application $app
     */
    protected function registerControllers(Application $app)
    {
        $app['payum.controller.authorize'] = $app->share(function() use ($app) {
            return new AuthorizeController(
                $app['payum.security.token_factory'],
                $app['payum.security.http_request_verifier'],
                $app['payum']
            );
        });
        $app->get('/payment/authorize/{payum_token}', 'payum.controller.authorize:doAction')->bind('payum_authorize_do');
        $app->post('/payment/authorize/{payum_token}', 'payum.controller.authorize:doAction')->bind('payum_authorize_do_post');

        $app['payum.controller.capture'] = $app->share(function() use ($app) {
            return new CaptureController(
                $app['payum.security.token_factory'],
                $app['payum.security.http_request_verifier'],
                $app['payum']
            );
        });
        $app->get('/payment/capture/{payum_token}', 'payum.controller.capture:doAction')->bind('payum_capture_do');
        $app->post('/payment/capture/{payum_token}', 'payum.controller.capture:doAction')->bind('payum_capture_do_post');

        $app['payum.controller.notify'] = $app->share(function() use ($app) {
            return new NotifyController(
                $app['payum.security.token_factory'],
                $app['payum.security.http_request_verifier'],
                $app['payum']
            );
        });
        $app->get('/payment/notify/{payum_token}', 'payum.controller.notify:doAction')->bind('payum_notify_do');
        $app->post('/payment/notify/{payum_token}', 'payum.controller.notify:doAction')->bind('payum_notify_do_post');

        $app['payum.controller.refund'] = $app->share(function() use ($app) {
            return new RefundController(
                $app['payum.security.token_factory'],
                $app['payum.security.http_request_verifier'],
                $app['payum']
            );
        });
        $app->get('/payment/refund/{payum_token}', 'payum.controller.refund:doAction')->bind('payum_refund_do');
        $app->post('/payment/refund/{payum_token}', 'payum.controller.refund:doAction')->bind('payum_refund_do_post');
    }

    /**
     * @param Application $app
     */
    protected function registerListeners(Application $app)
    {
        $app->error(function (\Exception $e, $code) use ($app) {
            if (false == $e instanceof ReplyInterface) {
                return;
            }

            /** @var ReplyToSymfonyResponseConverter $converter */
            $converter = $app['payum.reply_to_symfony_response_converter'];

            return $converter->convert($e);
        }, $priority = 100);
    }
}
