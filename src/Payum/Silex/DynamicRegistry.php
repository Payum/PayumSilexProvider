<?php
namespace Payum\Silex;

use Payum\Core\Extension\StorageExtension;
use Payum\Core\Gateway;
use Payum\Core\Registry\DynamicRegistry as CoreDynamicRegistry;

class DynamicRegistry extends CoreDynamicRegistry
{
    /**
     * {@inheritDoc}
     */
    public function getGateway($name)
    {
        $gateway = parent::getGateway($name);

        $this->addStorageToGateway($gateway);

        return $gateway;
    }

    /**
     * @param Gateway $gateway
     */
    protected function addStorageToGateway(Gateway $gateway)
    {
        foreach ($this->getStorages() as $storage) {
            $gateway->addExtension(new StorageExtension($storage));
        }
    }
}
