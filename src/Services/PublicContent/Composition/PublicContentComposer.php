<?php

namespace App\Services\PublicContent\Composition;

use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentContext;
use App\Enums\PublicContent\WidgetSkipReason;
use App\Services\PublicContent\Diagnostics\PublicContentWidgetDiagnostics;
use App\Services\PublicContent\Widgets\BuiltInPublicContentWidgetCatalog;
use App\Services\PublicContent\Widgets\PageWidgetLayoutResolver;
use App\Services\PublicContent\Widgets\PublicContentWidgetRegistry;

final class PublicContentComposer
{
    public function __construct(
        private readonly BuiltInPublicContentWidgetCatalog $builtInWidgets,
        private readonly RegionalPublicContentComponentFactory $regionalComponents,
        private readonly PublicContentWidgetRegistry $registry,
        private readonly PageWidgetLayoutResolver $layouts,
        private readonly PublicContentWidgetDiagnostics $diagnostics,
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

        foreach ($this->layouts->resolve($context, $this->registry) as $placement) {
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

            $component = $definition->build($context, $placement);
            if (trim($component->html) === '') {
                $this->diagnostics->recordSkipped(
                    $placement->widgetKey,
                    WidgetSkipReason::EmptyHtml,
                    $context,
                );
                continue;
            }

            $regions[$component->region][] = $component;
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
