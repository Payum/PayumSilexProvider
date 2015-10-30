<?php
namespace Payum\Silex;

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
        $token = $this->payum->getHttpRequestVerifier()->verify($request);

        $gateway = $this->payum->getGateway($token->getGatewayName());
        $gateway->execute(new Authorize($token));

        $this->payum->getHttpRequestVerifier()->invalidate($token);

        return new RedirectResponse($token->getAfterUrl());
    }
}
