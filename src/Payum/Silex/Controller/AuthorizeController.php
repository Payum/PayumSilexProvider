<?php
namespace Payum\Silex\Controller;

use Payum\Core\Request\Authorize;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class AuthorizeController extends PayumController
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
        $payment->execute(new Authorize($token));

        $this->httpRequestVerifier->invalidate($token);

        return new RedirectResponse($token->getAfterUrl());
    }
}
