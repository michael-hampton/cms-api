<?php

namespace App\DTO\PublicContent;

final readonly class ContentRegion
{
    /**
     * @param list<array<string, mixed>> $blocks
     */
    public function __construct(
        public string $name,
        public array $blocks,
        public string $renderedHtml,
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'blocks' => $this->blocks,
            'rendered_html' => $this->renderedHtml,
        ];
    }
}
