<?php

namespace App\Services\PublicContent\Composition;

use App\DTO\PublicContent\PublicContentComponent;
use App\DTO\PublicContent\PublicContentContext;
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
    ) {
    }

    /** @return array<string, list<PublicContentComponent>> */
    public function compose(PublicContentContext $context): array
    {
        $this->registerDefaults();
        $regions = [];
        $restricted = ($context->viewData['access']['can_view'] ?? true) === false;
        $allowedRestrictedWidgets = ['page-title', 'paywall-overlay', 'subscription-modal'];

        foreach ($this->layouts->resolve($context, $this->registry) as $placement) {
            if ($restricted && !in_array($placement->widgetKey, $allowedRestrictedWidgets, true)) {
                continue;
            }

            $definition = $this->registry->get($placement->widgetKey);

            if (!$definition->supports($context)) {
                continue;
            }

            $component = $definition->build($context, $placement);
            if (trim($component->html) === '') {
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
