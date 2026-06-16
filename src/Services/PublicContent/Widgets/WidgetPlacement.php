<?php

namespace App\Services\PublicContent\Widgets;

final readonly class WidgetPlacement
{
    public function __construct(
        public string $widgetKey,
        public string $region,
        public int $priority,
        public bool $enabled = true,
        public array $configuration = [],
    ) {
    }

    public function withOverrides(
        ?string $region = null,
        ?int $priority = null,
        ?bool $enabled = null,
        ?array $configuration = null,
    ): self {
        return new self(
            widgetKey: $this->widgetKey,
            region: $region ?? $this->region,
            priority: $priority ?? $this->priority,
            enabled: $enabled ?? $this->enabled,
            configuration: array_replace(
                $this->configuration,
                $configuration ?? [],
            ),
        );
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return $this->configuration[$key] ?? $default;
    }
}
