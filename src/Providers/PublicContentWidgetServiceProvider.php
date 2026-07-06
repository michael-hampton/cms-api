<?php

namespace App\Providers;

use App\Framework\ServiceProvider\ServiceProvider;
use App\Services\PublicContent\Widgets\DatabasePublicContentWidgetDefinitionClassProvider;
use App\Services\PublicContent\Widgets\PublicContentWidgetDefinition;
use App\Services\PublicContent\Widgets\PublicContentWidgetDefinitionClassProvider;
use App\Services\PublicContent\Widgets\PublicContentWidgetRegistry;

final class PublicContentWidgetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->bind(PublicContentWidgetDefinitionClassProvider::class, DatabasePublicContentWidgetDefinitionClassProvider::class);

        $this->container->singleton(PublicContentWidgetRegistry::class);
    }

    public function boot(): void
    {
        $registry = $this->container->resolve(PublicContentWidgetRegistry::class);
        $classProvider = $this->container->resolve(PublicContentWidgetDefinitionClassProvider::class);

        foreach ($classProvider->all() as $className) {
            $widget = $this->container->resolve($className);

            if ($widget instanceof PublicContentWidgetDefinition) {
                $registry->register($widget);
            }
        }
    }
}