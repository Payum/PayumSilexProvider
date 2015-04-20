<?php
namespace Payum\Silex;

use Payum\Core\Bridge\Symfony\Action\GetHttpRequestAction;
use Payum\Core\Bridge\Symfony\Action\ObtainCreditCardAction;
use Payum\Core\Bridge\Symfony\Form\Type\CreditCardExpirationDateType;
use Payum\Core\Bridge\Symfony\Form\Type\CreditCardType;
use Payum\Core\Bridge\Symfony\Form\Type\GatewayConfigType;
use Payum\Core\Bridge\Symfony\Form\Type\GatewayFactoriesChoiceType;
use Payum\Core\Bridge\Symfony\ReplyToSymfonyResponseConverter;
use Payum\Core\Bridge\Symfony\Security\HttpRequestVerifier;
use Payum\Core\Bridge\Symfony\Security\TokenFactory;
use Payum\Core\Bridge\Twig\TwigFactory;
use Payum\Core\GatewayFactory;
use Payum\Core\GatewayFactoryInterface;
use Payum\Core\Registry\DynamicRegistry;
use Payum\Core\Registry\SimpleRegistry;
use Payum\Core\Reply\ReplyInterface;
use Payum\Core\Security\GenericTokenFactory;
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
        $app['payum.template.layout'] = '@PayumCore/layout.html.twig';
        $app['payum.template.obtain_credit_card'] = '@PayumSymfonyBridge/obtainCreditCard.html.twig';

        $app['twig.loader.filesystem'] = $app->share($app->extend('twig.loader.filesystem', function($loader, $app) {
            /** @var  \Twig_Loader_Filesystem $loader */

            foreach (TwigFactory::createGenericPaths() as $name => $path) {
                $loader->addPath($path, $name);
            }

            return $loader;
        }));

        $app['payum.action.get_http_request'] = $app->share(function($app) {
            $action = new GetHttpRequestAction();
            $action->setHttpRequest($app['request']);

            return $action;
        });

        $app['payum.action.obtain_credit_card'] = $app->share(function($app) {
            $action = new ObtainCreditCardAction($app['form.factory'], $app['payum.template.obtain_credit_card']);
            $action->setRequest($app['request']);

            return $action;
        });

        $app['payum.gateway_config_storage'] = $app->share(function($app) {
            return null;
        });

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
            return new GenericTokenFactory(
                new TokenFactory($app['payum.security.token_storage'], $app['payum'], $app['url_generator']),
                array(
                    'capture' => 'payum_capture_do',
                    'notify' => 'payum_notify_do',
                    'authorize' => 'payum_authorize_do',
                    'refund' => 'payum_refund_do'
                )
            );
        });

        $app['form.types'] = $app->share($app->extend('form.types', function ($types) use ($app) {
            $types[] = new CreditCardType();
            $types[] = new CreditCardExpirationDateType();
            $types[] = new GatewayFactoriesChoiceType($app['payum.gateway_choices']);
            $types[] = new GatewayConfigType($app['payum']);

            return $types;
        }));

        $app['payum.gateway_choices'] = $app->share(function ($app) {
            $choices = array();
            foreach ($app['payum.gateway_factories'] as $factory) {
                /** @var $factory GatewayFactoryInterface */
                $config = $factory->createConfig();

                $choices[$config['payum.factory_name']] = $config['payum.factory_title'];
            }

            return $choices;
        });

        $app['payum.core_gateway_factory_config'] = $app->share(function ($app) {
            return [
                'twig.env' => $app['twig'],
                'payum.template.layout' => $app['payum.template.layout'],

                'payum.action.get_http_request' => $app['payum.action.get_http_request'],
                'payum.action.obtain_credit_card' => $app['payum.action.get_http_request'],
            ];
        });

        $app['payum.core_gateway_factory'] = $app->share(function ($app) {
            return new GatewayFactory($app['payum.core_gateway_factory_config']);
        });

        $app['payum.gateway_factories'] = $app->share(function () {
            return [
                // name => instance of GatewayFactoryInterface
            ];
        });

        $app['payum.gateways'] = $app->share(function () {
            return [
                // name => instance of GatewayInterface
            ];
        });

        $app['payum.storages'] = $app->share(function ($app) {
            return [
                // modelClass => instance of StorageInterface
            ];
        });

        $app['payum'] = $app->share(function($app) {
            $registry = new SimpleRegistry(
                $app['payum.gateways'],
                $app['payum.storages'],
                $app['payum.gateway_factories']
            );

            if ($configStorage = $app['payum.gateway_config_storage']) {
                $registry = new DynamicRegistry($configStorage, $registry);
            }

            return $registry;
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
