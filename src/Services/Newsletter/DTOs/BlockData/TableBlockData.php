<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class TableBlockData extends BaseBlockData
{
    public function __construct(
        public readonly bool  $hasHeader,
        public readonly array $rows
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            hasHeader: $data['hasHeader'] ?? false,
            rows: $data['rows'] ?? []
        );
    }
}