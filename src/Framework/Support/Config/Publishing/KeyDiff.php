<?php

namespace App\Framework\Support\Config\Publishing;

final class KeyDiff
{
    public function __construct(
        public readonly string $key,
        public readonly KeyDiffStatus $status,
        public readonly bool $baseExists,
        public readonly mixed $baseValue,
        public readonly bool $mineExists,
        public readonly mixed $mineValue,
        public readonly bool $latestExists,
        public readonly mixed $latestValue,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'status' => $this->status->value,
            'base' => $this->baseExists ? ['exists' => true, 'value' => $this->baseValue] : ['exists' => false],
            'mine' => $this->mineExists ? ['exists' => true, 'value' => $this->mineValue] : ['exists' => false],
            'latest' => $this->latestExists ? ['exists' => true, 'value' => $this->latestValue] : ['exists' => false],
        ];
    }
}