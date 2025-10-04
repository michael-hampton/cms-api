<?php

namespace App\Framework\ServiceProvider;

use App\Framework\Container;

/**
 * Service Provider Manager
 */
class ServiceProviderManager
{
    private Container $container;
    private array $config;
    private array $providers = [];
    private array $booted = [];

    public function __construct(Container $container, array $config = [])
    {
        $this->container = $container;
        $this->config = $config;
        $this->processConfiguration();
    }

    private function processConfiguration(): void
    {
        // Register explicit bindings from config
        if (isset($this->config['bindings'])) {
            foreach ($this->config['bindings'] as $abstract => $concrete) {
                $this->container->bind($abstract, $concrete);
            }
        }

        // Register explicit singletons from config
        if (isset($this->config['singletons'])) {
            foreach ($this->config['singletons'] as $singleton) {
                $this->container->singleton($singleton);
            }
        }
    }

    public function register(string $providerClass): void
    {
        if (isset($this->providers[$providerClass])) {
            return;
        }

        $provider = new $providerClass($this->container, $this->config);
        $this->providers[$providerClass] = $provider;
        $provider->register();
    }

    public function boot(string $providerClass): void
    {
        if (isset($this->booted[$providerClass]) || !isset($this->providers[$providerClass])) {
            return;
        }

        $this->providers[$providerClass]->boot();
        $this->booted[$providerClass] = true;
    }

    public function bootAll(): void
    {
        foreach (array_keys($this->providers) as $providerClass) {
            $this->boot($providerClass);
        }
    }
}