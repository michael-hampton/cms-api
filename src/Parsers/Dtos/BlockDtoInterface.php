<?php

namespace App\Parsers\Dtos;

interface BlockDtoInterface
{
    /**
     * Build DTO from parser input with validation/normalization
     */
    public static function fromArray(array $data): self;

    /**
     * Return array for legacy support
     */
    public function toArray(): array;

    /**
     * Get block type
     */
    public function getType(): string;
}