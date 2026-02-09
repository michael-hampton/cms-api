<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class InfoBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string $infoType,
        public readonly string $description
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            infoType: $data['infoType'] ?? 'info',
            description: $data['description'] ?? ''
        );
    }
}