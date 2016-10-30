<?php

namespace AssetManager\Service;

use AssetManager\Resolver\TranslatePathStackResolver;
use AssetManager\Resolver\PathStackResolver;
use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class TranslatePathStackResolverServiceFactory implements FactoryInterface
{
    /**
     * @inheritDoc
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config  = $container->get('config');
        $translatables = array();

        if (isset($config['asset_manager']['resolver_configs']['translatables'])) {
            $translatables = $config['asset_manager']['resolver_configs']['translatables'];
        }

        return new TranslatePathStackResolver($translatables);
    }

    /**
     * {@inheritDoc}
     *
     * @return PathStackResolver
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        return $this($serviceLocator, TranslatePathStackResolver::class);
    }
}
