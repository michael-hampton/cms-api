<?php

declare(strict_types=1);

namespace App\Services\Newsletter\DTOs\BlockData;

class TableBlockData extends BaseBlockData
{
    public function __construct(
        public readonly bool  $hasHeader,
        public readonly array $rows,
    )
    {
    }

    public static function fromArray(array $data): static
    {
        $instance = new static(
            hasHeader: (bool)($data['hasHeader'] ?? false),
            rows: $data['rows'] ?? [],
        );

        $instance->resolveStyle($data);

        return $instance;
    }
}