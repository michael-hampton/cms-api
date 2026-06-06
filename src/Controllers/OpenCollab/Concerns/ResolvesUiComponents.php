<?php

namespace App\Controllers\OpenCollab\Concerns;

use App\Framework\Authorization\Auth;
use App\Framework\Support\SiteContext;
use App\Services\OpenCollab\Dashboard\WidgetResolver;
use App\Services\UI\Components\UiPanelComponent;

trait ResolvesUiComponents
{
    /**
     * @return array<int, array<string, mixed>>
     */
    protected function allowedUiComponentDefinitionsForSurface(string $surface, ?string $type = null): array
    {
        $components = app(WidgetResolver::class)->allowedForSurface(
            (int) Auth::id(),
            (int) SiteContext::getId(),
            $surface,
        );

        if ($type === null) {
            return $components;
        }

        return array_values(array_filter(
            $components,
            static fn(array $component): bool => ($component['type'] ?? null) === $type
        ));
    }

    /**
     * @return array<int, string>
     */
    protected function allowedUiComponentKeysForSurface(string $surface): array
    {
        return array_map(
            static fn(array $component): string => (string) $component['key'],
            $this->allowedUiComponentDefinitionsForSurface($surface)
        );
    }

    /**
     * @return array<int, UiPanelComponent>
     */
    protected function allowedUiPanelsForSurface(string $surface): array
    {
        $definitions = $this->allowedUiComponentDefinitionsForSurface($surface, 'page_panel');
        $panels = [];

        foreach ($definitions as $definition) {
            $class = (string) ($definition['component'] ?? '');
            if ($class === '' || !class_exists($class)) {
                continue;
            }

            $component = app($class);
            if (!$component instanceof UiPanelComponent) {
                continue;
            }

            $panels[] = $component;
        }

        return $panels;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function allowedUiPanelDescriptorsForSurface(string $surface, array $context = []): array
    {
        return array_map(
            static fn(UiPanelComponent $panel): array => $panel->descriptor($context),
            $this->allowedUiPanelsForSurface($surface)
        );
    }
}
