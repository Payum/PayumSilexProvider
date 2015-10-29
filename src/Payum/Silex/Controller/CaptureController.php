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
        $token = $this->payum->getHttpRequestVerifier()->verify($request);

        $gateway = $this->payum->getGateway($token->getGatewayName());
        $gateway->execute(new Capture($token));

        $this->payum->getHttpRequestVerifier()->invalidate($token);

        return new RedirectResponse($token->getAfterUrl());
    }
}
