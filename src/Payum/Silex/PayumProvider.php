<?php
namespace Payum\Silex;

use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Bridge\Symfony\Action\GetHttpRequestAction;
use Payum\Core\Bridge\Symfony\Action\ObtainCreditCardAction;
use Payum\Core\Bridge\Symfony\Builder\HttpRequestVerifierBuilder;
use Payum\Core\Bridge\Symfony\Builder\TokenFactoryBuilder;
use Payum\Core\Bridge\Symfony\Form\Type\CreditCardExpirationDateType;
use Payum\Core\Bridge\Symfony\Form\Type\CreditCardType;
use Payum\Core\Bridge\Symfony\Form\Type\GatewayConfigType;
use Payum\Core\Bridge\Symfony\Form\Type\GatewayFactoriesChoiceType;
use Payum\Core\Bridge\Symfony\Form\Type\GatewayChoiceType;
use Payum\Core\Bridge\Symfony\ReplyToSymfonyResponseConverter;
use Payum\Core\Payum;
use Payum\Core\PayumBuilder;
use Payum\Core\Reply\ReplyInterface;
use Silex\Application;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;
use Silex\ServiceProviderInterface;
use Symfony\Component\OptionsResolver\Options;

class PayumProvider implements ServiceProviderInterface, ControllerProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function register(Application $app)
    {
        $app['payum.builder'] = $app->share(function($app) {
            $builder = new PayumBuilder();

            $builder->setCoreGatewayFactoryConfig([
                'twig.env' => $app['twig'],

                'payum.template.obtain_credit_card' => '@PayumSymfonyBridge/obtainCreditCard.html.twig',

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

            $builder->setTokenFactory(new TokenFactoryBuilder($app['url_generator']));

            $builder->setHttpRequestVerifier(new HttpRequestVerifierBuilder());

            return $builder;
        });

        $app['payum'] = $app->share(function($app) {
            /** @var PayumBuilder $builder */
            $builder = $app['payum.builder'];

            return $builder->getPayum();
        });

        $app['payum.reply_to_symfony_response_converter'] = $app->share(function($app) {
            return new ReplyToSymfonyResponseConverter();
        });

        $app['form.types'] = $app->share($app->extend('form.types', function ($types) use ($app) {
            $types[] = new CreditCardType();
            $types[] = new CreditCardExpirationDateType();
            $types[] = new GatewayFactoriesChoiceType($app['payum.gateway_factory_choices_callback']);
            $types[] = new GatewayChoiceType($app['payum.gateway_choices_callback']);
            $types[] = new GatewayConfigType($app['payum']);

            return $types;
        }));

        $app['payum.gateway_factory_choices_callback'] = $app->share(function ($app) {
            return function(Options $options) use ($app) {
                /** @var Payum $payum */
                $payum = $app['payum'];

                $choices = [];
                foreach ($payum->getGatewayFactories() as $name => $factory) {
                    if (in_array($name, ['omnipay', 'omnipay_direct', 'omnipay_offsite'])) {
                        continue;
                    }

                    $choices[ucwords(str_replace(['_', 'omnipay'], ' ', $name))] = $name;
                }

                return $choices;
            };
        });

        $app['payum.gateway_choices_callback'] = $app->share(function ($app) {
            return function(Options $options) use ($app) {
                /** @var Payum $payum */
                $payum = $app['payum'];

                $choices = [];
                foreach ($payum->getGateways() as $name => $gateway) {
                    $choices[$name] = ucwords(str_replace(['_'], ' ', $name));
                }

                return $choices;
            };
        });

        $app->error(function (\Exception $e, $code) use ($app) {
            if (false == $e instanceof ReplyInterface) {
                return;
            }

            /** @var ReplyToSymfonyResponseConverter $converter */
            $converter = $app['payum.reply_to_symfony_response_converter'];

            return $converter->convert($e);
        });

        $app['payum.controller.notify'] = $app->share(function() use ($app) {
            return new NotifyController($app['payum']);
        });
        $app['payum.controller.authorize'] = $app->share(function() use ($app) {
            return new AuthorizeController($app['payum']);
        });

        $app['payum.controller.capture'] = $app->share(function() use ($app) {
            return new CaptureController($app['payum']);
        });

        $app['payum.controller.refund'] = $app->share(function() use ($app) {
            return new RefundController($app['payum']);
        });

        $app['payum.payments_controller_collection'] = $app['controllers_factory'];
    }

    /**
     * {@inheritdoc}
     */
    public function connect(Application $app)
    {
        /** @var ControllerCollection $controllers */
        $payment = $app['payum.payments_controller_collection'];

        $payment->get('/authorize/{payum_token}', 'payum.controller.authorize:doAction')->bind('payum_authorize_do');
        $payment->post('/authorize/{payum_token}', 'payum.controller.authorize:doAction')->bind('payum_authorize_do_post');
        $payment->get('/capture/{payum_token}', 'payum.controller.capture:doAction')->bind('payum_capture_do');
        $payment->post('/capture/{payum_token}', 'payum.controller.capture:doAction')->bind('payum_capture_do_post');
        $payment->get('/notify/{payum_token}', 'payum.controller.notify:doAction')->bind('payum_notify_do');
        $payment->post('/notify/{payum_token}', 'payum.controller.notify:doAction')->bind('payum_notify_do_post');
        $payment->get('/refund/{payum_token}', 'payum.controller.refund:doAction')->bind('payum_refund_do');
        $payment->post('/refund/{payum_token}', 'payum.controller.refund:doAction')->bind('payum_refund_do_post');

        return $payment;
    }

    /**
     * {@inheritDoc}
     */
    public function boot(Application $app)
    {
    }
}
