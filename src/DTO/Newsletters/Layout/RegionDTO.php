<?php

namespace App\DTO\Newsletters\Layout;

use App\Framework\Support\Collection;

final class RegionDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly int    $order,
        /** @var array<int, SlotDTO> */
        public readonly array  $slots,
    )
    {
    }

    public function isCenter(): bool
    {
        return $this->id === 'center';
    }

    public function isEmpty(): bool
    {
        return empty($this->slots);
    }

    /** Return new instance with replaced slots */
    public function withSlots(array $slots): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            order: $this->order,
            slots: array_map(
                fn(array|SlotDTO $s) => $s instanceof SlotDTO
                    ? $s
                    : SlotDTO::fromArray($s),
                $slots
            ),
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'] ?? $data['id'],
            order: (int)($data['order'] ?? 0),
            slots: array_map(
                fn(array $s) => SlotDTO::fromArray($s),
                $data['slots'] ?? []
            ),
        );
    }

    /** @return Collection<int, SlotDTO> */
    public function getSlots(): Collection
    {
        return collect($this->slots);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'order' => $this->order,
            'slots' => array_map(fn(SlotDTO $s) => $s->toArray(), $this->slots),
        ];
    }
}