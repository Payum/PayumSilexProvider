<?php
namespace Payum\Silex\Controller;

use Payum\Core\Request\Capture;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class CaptureController extends PayumController
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
        $payment->execute(new Capture($token));

        $this->httpRequestVerifier->invalidate($token);

        return new RedirectResponse($token->getAfterUrl());
    }
}
