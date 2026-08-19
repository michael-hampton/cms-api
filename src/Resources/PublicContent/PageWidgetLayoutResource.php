<?php

namespace App\Resources\PublicContent;

use App\DTO\PublicContent\Widgets\WidgetLayoutOverride;

final readonly class PageWidgetLayoutResource
{
    /**
     * @param list<WidgetLayoutOverride> $overrides
     */
    public function __construct(private array $overrides)
    {
    }

    /**
     * @return array{widgets: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'widgets' => array_map(
                static fn(WidgetLayoutOverride $override): array => $override->toArray(),
                $this->overrides,
            ),
        ];
    }
}
