<?php
namespace Payum\Silex\Controller;

use Payum\Core\Request\Notify;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class NotifyController extends PayumController
{
    /**
     * @param Request $request
     *
     * @return Response
     */
    public function doAction(Request $request)
    {
        $token = $this->httpRequestVerifier->verify($request);

        $payment = $this->registry->getPayment($token->getPaymentName());

        $payment->execute(new Notify($token));

        return new Response('', 204);
    }
}
