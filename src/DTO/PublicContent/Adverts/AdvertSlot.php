<?php

namespace App\DTO\PublicContent\Adverts;

/**
 * Structured advert placement for API document consumers outside the site shell.
 */
final readonly class AdvertSlot
{
    public function __construct(
        public int $index,
        public string $placement,
        public ?int $afterMainBlockIndex,
        public string $type,
        /** @var array<string, mixed> */
        public array $payload = [],
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'index' => $this->index,
            'placement' => $this->placement,
            'after_main_block_index' => $this->afterMainBlockIndex,
            'type' => $this->type,
            'payload' => $this->payload,
        ];
    }
}
