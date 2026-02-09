<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class TeamBlockData extends BaseBlockData
{
    public function __construct(
        public readonly ?string $title,
        public readonly ?string $subtitle,
        public readonly array   $members,
        public readonly string  $layout
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'] ?? null,
            subtitle: $data['subtitle'] ?? null,
            members: $data['members'] ?? [],
            layout: $data['layout'] ?? 'grid'
        );
    }
}