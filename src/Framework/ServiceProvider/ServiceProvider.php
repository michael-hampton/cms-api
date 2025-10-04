<?php

namespace App\Framework\ServiceProvider;

use App\Framework\Container;
use App\Framework\AutoDiscovery;

/**
 * Base service provider class
 */
abstract class ServiceProvider
{
    protected Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register services in the container
     */
    abstract public function register(): void;

    /**
     * Boot services after all providers are registered
     */
    public function boot(): void
    {
        // Override in child classes if needed
    }

    /**
     * Auto-discover and register classes of a specific type
     */
    protected function autoRegister(string $namespace, ?string $interfaceOrParent = null, bool $singleton = true): void
    {
        // Skip if auto-discovery is disabled
        if (!($this->config['auto_discovery']['enabled'] ?? true)) {
            return;
        }

        $classes = $this->discoverClasses($namespace);

        foreach ($classes as $class) {
            if ($interfaceOrParent && !is_subclass_of($class, $interfaceOrParent) && !in_array($interfaceOrParent, class_implements($class) ?: [])) {
                continue;
            }

            if ($this->container->bound($class)) {
                continue;
            }

            if ($singleton) {
                $this->container->singleton($class);
            } else {
                $this->container->bind($class);
            }
        }
    }

    /**
     * Auto-discover classes in a namespace
     */
    protected function discoverClasses(string $type): array
    {
        return match($type) {
            'repositories' => AutoDiscovery::discoverRepositories(),
            'services' => AutoDiscovery::discoverServices(),
            'controllers' => AutoDiscovery::discoverControllers(),
            'models' => AutoDiscovery::discoverModels(),
            'parsers' => AutoDiscovery::discoverByDir(null, 'Parsers'),
            default => []
        };
    }

    /**
     * Check if auto-discovery is enabled for a specific type
     */
    protected function isAutoDiscoveryEnabled(string $type): bool
    {
        return ($this->config['auto_discovery']['enabled'] ?? true) &&
            isset($this->config['auto_discovery']['directories'][$type]);
    }
}

