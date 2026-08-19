<?php

namespace App\Providers;

use App\Framework\ServiceProvider\ServiceProvider;
use App\Services\PublicContent\Widgets\BuiltInPublicContentWidgetCatalog;
use App\Services\PublicContent\Widgets\Contracts\WidgetPlacementResolverInterface;
use App\Services\PublicContent\Widgets\Contracts\WidgetThemeResolverInterface;
use App\Services\PublicContent\Widgets\DatabasePublicContentWidgetDefinitionClassProvider;
use App\Services\PublicContent\Widgets\PageWidgetLayoutResolver;
use App\Services\PublicContent\Widgets\PublicContentWidgetDefinition;
use App\Services\PublicContent\Widgets\PublicContentWidgetDefinitionClassProvider;
use App\Services\PublicContent\Widgets\PublicContentWidgetRegistry;
use App\Services\PublicContent\Widgets\PublicContentWidgetThemeResolver;

final class PublicContentWidgetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->bind(PublicContentWidgetDefinitionClassProvider::class, DatabasePublicContentWidgetDefinitionClassProvider::class);
        $this->container->bind(WidgetPlacementResolverInterface::class, PageWidgetLayoutResolver::class);
        $this->container->bind(WidgetThemeResolverInterface::class, PublicContentWidgetThemeResolver::class);

        $this->container->singleton(PublicContentWidgetRegistry::class);
    }

    public function boot(): void
    {
        $registry = $this->container->resolve(PublicContentWidgetRegistry::class);
        $catalog = $this->container->resolve(BuiltInPublicContentWidgetCatalog::class);

        foreach ($catalog->all() as $definition) {
            $registry->register($definition);
        }

        $classProvider = $this->container->resolve(PublicContentWidgetDefinitionClassProvider::class);

        foreach ($classProvider->all() as $className) {
            $widget = $this->container->resolve($className);

            if ($widget instanceof PublicContentWidgetDefinition) {
                $registry->register($widget);
            }
        }
    }
}