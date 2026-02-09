<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class ServicesBlockData extends BaseBlockData
{
    public function __construct(
        public readonly ?string $title,
        public readonly ?string $subtitle,
        public readonly array   $services,
        public readonly string  $layout
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? null,
            subtitle: $data['subtitle'] ?? null,
            services: $data['services'] ?? [],
            layout: $data['layout'] ?? 'grid'
        );
    }
}