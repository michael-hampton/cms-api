<?php

namespace App\DTO\Newsletters\Layout;

class SlotDTO
{
    public function __construct(
        public string $name,
        public array  $blocks,
        public array  $allowedBlockTypes,
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            blocks: $data['blocks'] ?? [],
            allowedBlockTypes: $data['allowed_block_types'] ?? [],
        );
    }

    public function isEmpty(): bool
    {
        return empty($this->blocks);
    }

    public function toArray(): array
    {
        $arr = [
            'name' => $this->name,
            'blocks' => $this->blocks,
        ];

        if (!empty($this->allowedBlockTypes)) {
            $arr['allowed_block_types'] = $this->allowedBlockTypes;
        }

        return $arr;
    }
}