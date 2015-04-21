<?php
namespace Payum\Silex;

use Payum\Core\Registry\AbstractRegistry;
use Silex\Application;

class PimpleAwareRegistry extends AbstractRegistry
{
    /**
     * @var \Pimple
     */
    private $pimple;

    /**
     * @param \Pimple $pimple
     */
    public function setPimple(\Pimple $pimple)
    {
        $this->pimple = $pimple;
    }

    /**
     * {@inheritDoc}
     */
    protected function getService($id)
    {
        return isset($this->pimple[$id]) ? $this->pimple[$id] : $id;
    }
}
