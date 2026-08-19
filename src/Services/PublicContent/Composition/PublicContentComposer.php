<?php

namespace App\Services\PublicContent\Composition;

use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentContext;
use App\Enums\PublicContent\WidgetSkipReason;
use App\Services\PublicContent\Diagnostics\PublicContentWidgetDiagnostics;
use App\Services\PublicContent\Islands\PublicContentIslandFiller;
use App\Services\PublicContent\Islands\PublicContentIslandMarker;
use App\Services\PublicContent\Widgets\BuiltInPublicContentWidgetCatalog;
use App\Services\PublicContent\Widgets\Contracts\WidgetPlacementResolverInterface;
use App\Services\PublicContent\Widgets\PublicContentWidgetRegistry;

final class PublicContentComposer
{
    public function __construct(
        private readonly BuiltInPublicContentWidgetCatalog $builtInWidgets,
        private readonly RegionalPublicContentComponentFactory $regionalComponents,
        private readonly PublicContentWidgetRegistry $registry,
        private readonly WidgetPlacementResolverInterface $layouts,
        private readonly PublicContentWidgetDiagnostics $diagnostics,
        private readonly PublicContentIslandFiller $islandFiller,
    ) {
    }

    /** @return array<string, list<PublicContentComponent>> */
    public function compose(PublicContentContext $context): array
    {
        $this->registerDefaults();
        $this->diagnostics->reset();

        $regions = [];
        $restricted = ($context->viewData['access']['can_view'] ?? true) === false;
        $allowedRestrictedWidgets = ['paywall-overlay', 'subscription-modal'];
        $placements = $this->layouts->resolve($context, $this->registry);
        $overrideKeys = [];

        foreach ($placements as $placement) {
            if ($placement->pageOverride && $placement->enabled) {
                $overrideKeys[] = $placement->widgetKey;
            }
        }

        if ($overrideKeys !== []) {
            $context = $context->withPageTypeOverrideKeys($overrideKeys);
        }

        foreach ($placements as $placement) {
            if ($restricted && !in_array($placement->widgetKey, $allowedRestrictedWidgets, true)) {
                $this->diagnostics->recordSkipped(
                    $placement->widgetKey,
                    WidgetSkipReason::RestrictedContent,
                    $context,
                );
                continue;
            }

            $definition = $this->registry->get($placement->widgetKey);

            if (!$definition->supports($context)) {
                $this->diagnostics->recordSkipped(
                    $placement->widgetKey,
                    WidgetSkipReason::SupportsFailed,
                    $context,
                );
                continue;
            }

            $islandId = $placement->widgetKey;
            $built = null;
            $html = $this->islandFiller->fill(
                PublicContentIslandMarker::placeholder($islandId),
                [
                    $islandId => function () use ($definition, $context, $placement, &$built): string {
                        $built = $definition->build($context, $placement);

                        return $built->html;
                    },
                ],
            );

            if (
                !$built instanceof PublicContentComponent
                || $html === PublicContentIslandFiller::FAILED_FALLBACK
                || $html === PublicContentIslandFiller::MISSING_FALLBACK
            ) {
                $this->diagnostics->recordSkipped(
                    $placement->widgetKey,
                    WidgetSkipReason::BuildFailed,
                    $context,
                );
                continue;
            }

            if (trim($built->html) === '') {
                $this->diagnostics->recordSkipped(
                    $placement->widgetKey,
                    WidgetSkipReason::EmptyHtml,
                    $context,
                );
                continue;
            }

            $regions[$built->region][] = $built;
        }

        foreach ($regions as &$components) {
            usort(
                $components,
                static fn(PublicContentComponent $left, PublicContentComponent $right): int =>
                    $left->priority <=> $right->priority,
            );
        }

        return $regions;
    }

    private function registerDefaults(): void
    {
        foreach ($this->builtInWidgets->all() as $definition) {
            $this->registry->register($definition);
        }

        $this->registry->register($this->regionalComponents);
    }
}
