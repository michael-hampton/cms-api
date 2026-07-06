<?php

namespace App\Framework\ServiceProvider;

use App\Framework\Container;
use App\Services\PublicContent\Config\DatabasePublicContentConfigSource;
use App\Services\PublicContent\Config\FallbackPublicContentConfigSource;
use App\Services\PublicContent\Config\FilePublicContentConfigSource;
use App\Services\PublicContent\Config\PublicContentConfigSource;
use App\Services\PublicContent\Config\PublicContentConfigSourceMode;
use App\Services\PublicContent\Theming\DatabasePublicContentDesignTokenSource;
use App\Services\PublicContent\Theming\FallbackPublicContentDesignTokenSource;
use App\Services\PublicContent\Theming\FilePublicContentDesignTokenSource;
use App\Services\PublicContent\Theming\PublicContentDesignTokenSource;

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
        $this->container->singleton(PublicContentDesignTokenSource::class, function ($container) {
            $mode = PublicContentConfigSourceMode::tryFrom(
                (string) env('PUBLIC_CONTENT_CONFIG_SOURCE', 'file')
            ) ?? PublicContentConfigSourceMode::File;

            $file = $container->resolve(FilePublicContentDesignTokenSource::class);

            return $mode === PublicContentConfigSourceMode::File
                ? $file
                : new FallbackPublicContentDesignTokenSource(
                    $container->resolve(DatabasePublicContentDesignTokenSource::class),
                    $file,
                );
        });

        $this->container->singleton(PublicContentConfigSource::class, function ($container) {
            $mode = PublicContentConfigSourceMode::tryFrom(
                (string) env('PUBLIC_CONTENT_CONFIG_SOURCE', 'file')
            ) ?? PublicContentConfigSourceMode::File;

            $file = $container->resolve(FilePublicContentConfigSource::class);

            if ($mode === PublicContentConfigSourceMode::File) {
                return $file;
            }

            $database = $container->resolve(DatabasePublicContentConfigSource::class);

            return new FallbackPublicContentConfigSource($database, $file);
        });

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