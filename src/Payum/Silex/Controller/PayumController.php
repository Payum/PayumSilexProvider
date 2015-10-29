<?php
namespace Payum\Silex\Controller;

use Payum\Core\Payum;

abstract class PayumController
{
    /**
     * @var Payum
     */
    protected $payum;

    /**
     * @param Payum $payum
     */
    public function __construct(Payum $payum)
    {
        $this->payum = $payum;
    }
}
