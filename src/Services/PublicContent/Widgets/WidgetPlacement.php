<?php

namespace App\Services\PublicContent\Widgets;

use App\Enums\PublicContent\WidgetRegion;

class WidgetPlacement
{
    public WidgetRegion $region;

    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(
        public string $widgetKey,
        string|WidgetRegion $region,
        public int $priority,
        public bool $enabled = true,
        public array $configuration = [],
        public bool $pageOverride = false,
    ) {
        $this->region = $region instanceof WidgetRegion
            ? $region->layoutSlot()
            : WidgetRegion::fromConfig($region)->layoutSlot();
    }

    public function regionName(): string
    {
        return $this->region->value;
    }

    public function withOverrides(
        string|WidgetRegion|null $region = null,
        ?int $priority = null,
        ?bool $enabled = null,
        ?array $configuration = null,
        ?bool $pageOverride = null,
    ): self {
        $nextRegion = $this->region;
        if ($region instanceof WidgetRegion) {
            $nextRegion = $region->layoutSlot();
        } elseif (is_string($region) && $region !== '') {
            $parsed = WidgetRegion::tryFromConfig($region);
            if ($parsed !== null) {
                $nextRegion = $parsed->layoutSlot();
            }
        }

        return new self(
            widgetKey: $this->widgetKey,
            region: $nextRegion,
            priority: $priority ?? $this->priority,
            enabled: $enabled ?? $this->enabled,
            configuration: array_replace(
                $this->configuration,
                $configuration ?? [],
            ),
            pageOverride: $pageOverride ?? $this->pageOverride,
        );
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->configuration[$key] ?? $default;
    }
}
