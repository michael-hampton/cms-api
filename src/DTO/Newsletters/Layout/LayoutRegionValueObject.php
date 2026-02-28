<?php

namespace App\DTO\Newsletters\Layout;

use App\Framework\Support\Collection;

/**
 * Immutable value object hydrated from a layout version's regions JSON.
 * Owns all region access, ordering, and slot resolution.
 */
final class LayoutRegionValueObject
{
    /** @param Collection<int, RegionDTO> $regions */
    private function __construct(
        private readonly Collection $regions,
        private readonly int        $schemaVersion,
    )
    {
    }

    /**
     * Create a default v2 layout with empty top/center/bottom regions.
     */
    public static function default(): self
    {
        return self::fromArray([
            'schema_version' => 2,
            'regions' => [
                ['id' => 'top', 'name' => 'Top Region', 'order' => 1, 'slots' => []],
                ['id' => 'center', 'name' => 'Content Region', 'order' => 2, 'slots' => []],
                ['id' => 'bottom', 'name' => 'Footer Region', 'order' => 3, 'slots' => []],
            ],
        ]);
    }

    public static function fromArray(array $data): self
    {
        $schemaVersion = (int)($data['schema_version'] ?? 1);
        $rawRegions = $data['regions'] ?? [];

        $regions = collect($rawRegions)
            ->map(fn(array $r) => RegionDTO::fromArray($r))
            ->values();

        return new self($regions, $schemaVersion);
    }

    /**
     * Regions sorted ascending by order.
     * @return Collection<int, RegionDTO>
     */
    public function getOrderedRegions(): Collection
    {
        return $this->regions->sortBy('order')->values();
    }

    /** @return Collection<int, RegionDTO> */
    public function getRegions(): Collection
    {
        return $this->regions;
    }

    public function hasCenterRegion(): bool
    {
        return $this->getCenterRegion() !== null;
    }

    public function getCenterRegion(): ?RegionDTO
    {
        return $this->getRegionById('center');
    }

    public function getRegionById(string $id): ?RegionDTO
    {
        return $this->regions->firstWhere('id', $id);
    }

    public function schemaVersion(): int
    {
        return $this->schemaVersion;
    }

    public function isV2(): bool
    {
        return $this->schemaVersion >= 2;
    }

    /**
     * Return a new instance with the center region's slots replaced.
     * Used by NewsletterContentResolver to inject newsletter content.
     */
    public function withCenterSlots(array $slots): self
    {
        $updatedRegions = $this->regions->map(function (RegionDTO $r) use ($slots) {
            if ($r->isCenter()) {
                return $r->withSlots(
                    array_map(
                        fn(array $slot) => SlotDTO::fromArray($slot),
                        $slots
                    )
                );
            }

            return $r;
        });

        return new self($updatedRegions, $this->schemaVersion);
    }

    public function toArray(): array
    {
        return [
            'schema_version' => $this->schemaVersion,
            'regions' => $this->regions->map(fn(RegionDTO $r) => $r->toArray())->values()->all(),
        ];
    }
}