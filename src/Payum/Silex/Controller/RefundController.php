<?php
namespace Payum\Silex\Controller;

use Payum\Core\Request\Refund;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class RefundController extends PayumController
{
    /**
     * @param Request $request
     *
     * @return RedirectResponse
     */
    public function doAction(Request $request)
    {
        $token = $this->httpRequestVerifier->verify($request);

        $payment = $this->registry->getPayment($token->getPaymentName());
        $payment->execute(new Refund($token));

        $this->httpRequestVerifier->invalidate($token);

        return new RedirectResponse($token->getAfterUrl());
    }
}
