<?php
namespace Payum\Silex;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Bridge\Symfony\Action\GetHttpRequestAction;
use Payum\Core\Bridge\Symfony\Action\ObtainCreditCardAction;
use Payum\Core\Bridge\Symfony\Form\Type\CreditCardExpirationDateType;
use Payum\Core\Bridge\Symfony\Form\Type\CreditCardType;
use Payum\Core\Bridge\Symfony\Form\Type\GatewayConfigType;
use Payum\Core\Bridge\Symfony\Form\Type\GatewayFactoriesChoiceType;
use Payum\Core\Bridge\Symfony\Form\Type\GatewayChoiceType;
use Payum\Core\Bridge\Symfony\ReplyToSymfonyResponseConverter;
use Payum\Core\Bridge\Symfony\Security\HttpRequestVerifier;
use Payum\Core\Bridge\Symfony\Security\TokenFactory;
use Payum\Core\Bridge\Twig\TwigFactory;
use Payum\Core\Payum;
use Payum\Core\PayumBuilder;
use Payum\Core\Registry\StorageRegistryInterface;
use Payum\Core\Reply\ReplyInterface;
use Payum\Core\Storage\StorageInterface;
use Payum\Silex\Controller\AuthorizeController;
use Payum\Silex\Controller\CaptureController;
use Payum\Silex\Controller\NotifyController;
use Payum\Silex\Controller\RefundController;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;

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
        $app['payum.builder'] = $app->share(function($app) {
            $builder = new PayumBuilder();

            $builder->addDefaultStorages();

            $builder->setCoreGatewayFactoryConfig([
                'twig.env' => $app['twig'],

                'payum.action.get_http_request' => function() use ($app) {
                    $action = new GetHttpRequestAction();
                    $action->setHttpRequest($app['request']);

                    return $action;
                },
                'payum.action.obtain_credit_card' => function(ArrayObject $config) use($app) {
                    $action = new ObtainCreditCardAction($app['form.factory'], $config['payum.template.obtain_credit_card']);
                    $action->setRequest($app['request']);

                    return $action;
                },
            ]);

            $builder->setGenericTokenFactoryPaths([
                'capture' => 'payum_capture_do',
                'notify' => 'payum_notify_do',
                'authorize' => 'payum_authorize_do',
                'refund' => 'payum_refund_do'
            ]);

            $builder->setTokenFactory(function(StorageInterface $tokenStorage, StorageRegistryInterface $registry) use ($app) {
                return new TokenFactory($tokenStorage, $registry, $app['url_generator']);
            });

            $builder->setHttpRequestVerifier(function(StorageInterface $tokenStorage) {
                return new HttpRequestVerifier($tokenStorage);
            });

            return $builder;
        });

        $app['payum'] = $app->share(function($app) {
            /** @var PayumBuilder $builder */
            $builder = $app['payum.builder'];

            return $builder->getPayum();
        });

        $app['twig.loader.filesystem'] = $app->share($app->extend('twig.loader.filesystem', function($loader, $app) {
            /** @var  \Twig_Loader_Filesystem $loader */

            foreach (TwigFactory::createGenericPaths() as $path => $name) {
                $loader->addPath($path, $name);
            }

            return $loader;
        }));

        $app['payum.reply_to_symfony_response_converter'] = $app->share(function($app) {
            return new ReplyToSymfonyResponseConverter();
        });

        $app['form.types'] = $app->share($app->extend('form.types', function ($types) use ($app) {
            $types[] = new CreditCardType();
            $types[] = new CreditCardExpirationDateType();
            $types[] = new GatewayFactoriesChoiceType($app['payum.gateway_factory_choices']);
            $types[] = new GatewayChoiceType($app['payum.gateway_choices']);
            $types[] = new GatewayConfigType($app['payum']);

            return $types;
        }));

        $app['payum.gateway_factory_choices'] = $app->share(function ($app) {
            /** @var Payum $payum */
            $payum = $app['payum'];

            $choices = [];
            foreach ($payum->getGatewayFactories() as $name => $factory) {
                if (in_array($name, ['omnipay', 'omnipay_direct', 'omnipay_offsite'])) {
                    continue;
                }

                $choices[$name] = ucwords(str_replace(['_', 'omnipay'], ' ', $name));
            }

            return $choices;
        });

        $app['payum.gateway_choices'] = $app->share(function ($app) {
            /** @var Payum $payum */
            $payum = $app['payum'];

            $choices = [];
            foreach ($payum->getGateways() as $name => $gateway) {
                $choices[$name] = ucwords(str_replace(['_'], ' ', $name));
            }

            return $choices;
        });
    }

    /**
     * @param Application $app
     */
    protected function registerControllers(Application $app)
    {
        $app['payum.controller.authorize'] = $app->share(function() use ($app) {
            return new AuthorizeController($app['payum']);
        });
        $app->get('/payment/authorize/{payum_token}', 'payum.controller.authorize:doAction')->bind('payum_authorize_do');
        $app->post('/payment/authorize/{payum_token}', 'payum.controller.authorize:doAction')->bind('payum_authorize_do_post');

        $app['payum.controller.capture'] = $app->share(function() use ($app) {
            return new CaptureController($app['payum']);
        });
        $app->get('/payment/capture/{payum_token}', 'payum.controller.capture:doAction')->bind('payum_capture_do');
        $app->post('/payment/capture/{payum_token}', 'payum.controller.capture:doAction')->bind('payum_capture_do_post');

        $app['payum.controller.notify'] = $app->share(function() use ($app) {
            return new NotifyController($app['payum']);
        });
        $app->get('/payment/notify/{payum_token}', 'payum.controller.notify:doAction')->bind('payum_notify_do');
        $app->post('/payment/notify/{payum_token}', 'payum.controller.notify:doAction')->bind('payum_notify_do_post');

        $app['payum.controller.refund'] = $app->share(function() use ($app) {
            return new RefundController($app['payum']);
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
