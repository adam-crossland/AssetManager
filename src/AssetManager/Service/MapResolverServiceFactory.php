<?php

namespace AssetManager\Service;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use AssetManager\Resolver\MapResolver;

class MapResolverServiceFactory implements FactoryInterface
{
    /**
     * @inheritDoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get('Config');
        $map    = array();

        if (isset($config['asset_manager']['resolver_configs']['map'])) {
            $map = $config['asset_manager']['resolver_configs']['map'];
        }

        $patchStackResolver = new MapResolver($map);

        return $patchStackResolver;
    }

    /**
     * {@inheritDoc}
     *
     * @return MapResolver
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        return $this($serviceLocator, MapResolver::class);
    }
}
