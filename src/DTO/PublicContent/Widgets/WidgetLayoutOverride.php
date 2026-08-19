<?php

namespace App\DTO\PublicContent\Widgets;

use App\Enums\PublicContent\WidgetRegion;

final readonly class WidgetLayoutOverride
{
    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(
        public string $widgetKey,
        public ?WidgetRegion $region = null,
        public ?int $priority = null,
        public ?bool $enabled = null,
        public array $configuration = [],
    ) {
    }

    public static function none(string $widgetKey): self
    {
        return new self($widgetKey);
    }

    public function isEmpty(): bool
    {
        return $this->region === null
            && $this->priority === null
            && $this->enabled === null
            && $this->configuration === [];
    }

    /**
     * @return array{
     *     widget_key: string,
     *     region: ?string,
     *     priority: ?int,
     *     is_enabled: ?bool,
     *     configuration: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'widget_key' => $this->widgetKey,
            'region' => $this->region?->value,
            'priority' => $this->priority,
            'is_enabled' => $this->enabled,
            'configuration' => $this->configuration,
        ];
    }
}
