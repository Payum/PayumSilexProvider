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

        $gateway = $this->registry->getGateway($token->getGatewayName());
        $gateway->execute(new Refund($token));

        $this->httpRequestVerifier->invalidate($token);

        return new RedirectResponse($token->getAfterUrl());
    }
}
