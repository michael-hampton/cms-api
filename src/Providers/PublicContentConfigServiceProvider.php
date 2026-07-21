<?php

namespace App\Providers;

use App\DTO\PublicContent\Locale\LocaleRulesArtefact;
use App\Framework\ServiceProvider\ServiceProvider;
use App\Services\PublicContent\Locale\LocaleRulesArtefactLoader;
use App\Services\PublicContent\Locale\PublicContentLocaleResolver;
use App\Services\PublicContent\Observability\PublicContentRuntimeFailureMonitor;
use App\Services\PublicContent\Observability\PublicContentRuntimeFailureSignal;
use App\Services\PublicContent\Parity\PublicContentParityKillPath;
use App\Services\PublicContent\PublicContentRollout;
use App\Services\PublicContent\Rollout\PublicContentKillSwitch;
use App\Services\PublicContent\Widgets\DatabasePublicContentWidgetDefinitionClassProvider;
use App\Services\PublicContent\Widgets\FallbackPublicContentWidgetDefinitionClassProvider;
use App\Services\PublicContent\Widgets\FilePublicContentWidgetDefinitionClassProvider;
use App\Services\PublicContent\Widgets\PublicContentWidgetDefinitionClassProvider;
use App\Services\PublicContent\Config\PublicContentConfigSourceMode;

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

        $this->container->singleton(PublicContentKillSwitch::class, function () {
            $path = dirname(__DIR__) . '/storage/public-content/kill-switch.json';

            return new PublicContentKillSwitch(
                statePath: $path,
                cacheClearSeconds: (int) config('public_content.cache.kill_switch_cache_clear_seconds', 60),
            );
        });

        $this->container->singleton(PublicContentRuntimeFailureSignal::class, function () {
            return new PublicContentRuntimeFailureSignal(
                windowSize: (int) config('public_content.runtime.failure_window_size', 100),
                threshold: (float) config('public_content.runtime.failure_rate_threshold', 0.05),
            );
        });

        $this->container->singleton(PublicContentRuntimeFailureMonitor::class);
        $this->container->singleton(PublicContentParityKillPath::class);

        $this->container->singleton(PublicContentRollout::class, function ($container) {
            return new PublicContentRollout(
                $container->resolve(PublicContentKillSwitch::class),
            );
        });

        // Fail closed: missing/malformed locale rules refuse start-up.
        $this->container->singleton(LocaleRulesArtefact::class, function () {
            $relative = (string) config(
                'public_content.locale_rules.path',
                'config/public-content-locale-rules.json',
            );
            $absolute = str_starts_with($relative, '/')
                ? $relative
                : dirname(__DIR__) . '/' . ltrim($relative, '/');

            return (new LocaleRulesArtefactLoader())->load($absolute);
        });

        $this->container->singleton(PublicContentLocaleResolver::class, function ($container) {
            return new PublicContentLocaleResolver(
                $container->resolve(LocaleRulesArtefact::class),
            );
        });
    }

    public function boot(): void
    {
        // Force artefact load at boot so a broken file stops the line.
        $this->container->resolve(LocaleRulesArtefact::class);
    }
}
