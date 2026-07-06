<?php
namespace App\Providers;

use App\Framework\ServiceProvider\ServiceProvider;
use App\Services\PublicContent\Config\DatabasePublicContentConfigSource;
use App\Services\PublicContent\Config\FallbackPublicContentConfigSource;
use App\Services\PublicContent\Config\FilePublicContentConfigSource;
use App\Services\PublicContent\Config\PublicContentConfigSource;
use App\Services\PublicContent\Config\PublicContentConfigSourceMode;
use App\Services\PublicContent\Theming\DatabasePublicContentDesignTokenSource;
use App\Services\PublicContent\Theming\FallbackPublicContentDesignTokenSource;
use App\Services\PublicContent\Theming\FilePublicContentDesignTokenSource;
use App\Services\PublicContent\Theming\PublicContentDesignTokenSource;
use App\Services\PublicContent\Widgets\DatabasePublicContentWidgetDefinitionClassProvider;
use App\Services\PublicContent\Widgets\FallbackPublicContentWidgetDefinitionClassProvider;
use App\Services\PublicContent\Widgets\FilePublicContentWidgetDefinitionClassProvider;
use App\Services\PublicContent\Widgets\PublicContentWidgetDefinitionClassProvider;

final class PublicContentConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(PublicContentWidgetDefinitionClassProvider::class, function ($container) {
            $mode = PublicContentConfigSourceMode::tryFrom(
                (string) env('PUBLIC_CONTENT_CONFIG_SOURCE', 'file')
            ) ?? PublicContentConfigSourceMode::File;

            $file = $container->resolve(FilePublicContentWidgetDefinitionClassProvider::class);

            return $mode === PublicContentConfigSourceMode::File
                ? $file
                : new FallbackPublicContentWidgetDefinitionClassProvider(
                    $container->resolve(DatabasePublicContentWidgetDefinitionClassProvider::class),
                    $file,
                );
        });

    }
}