<?php

namespace App\Services\Newsletter\Layout;

use App\DTO\Newsletters\Layout\LayoutRegionValueObject;
use App\DTO\Newsletters\Layout\RegionDTO;

/**
 * Validates a LayoutRegionValueObject before a layout version is saved.
 * Throws domain exceptions — never returns booleans.
 * Must be called from the service layer, never controllers.
 */
class LayoutRegionValidator
{
    /**
     * @throws \DomainException on any validation failure
     */
    public function validate(LayoutRegionValueObject $layout): void
    {
        $this->assertCenterRegionExists($layout);
        $this->assertRegionIdsAreUnique($layout);
        $this->assertOrderValuesAreUnique($layout);
        $this->assertOrderIsSequential($layout);
        $this->assertSingleCenterRegion($layout);
    }

    private function assertCenterRegionExists(LayoutRegionValueObject $layout): void
    {
        if (!$layout->hasCenterRegion()) {
            throw new \DomainException(
                'Layout must contain exactly one center region.'
            );
        }
    }

    private function assertRegionIdsAreUnique(LayoutRegionValueObject $layout): void
    {
        $ids = $layout->getRegions()->pluck('id');

        if ($ids->count() !== $ids->unique()->count()) {
            $duplicates = $ids->duplicates()->values()->implode(', ');
            throw new \DomainException(
                "Region IDs must be unique. Duplicates found: {$duplicates}."
            );
        }
    }

    private function assertOrderValuesAreUnique(LayoutRegionValueObject $layout): void
    {
        $orders = $layout->getRegions()->pluck('order');

        if ($orders->count() !== $orders->unique()->count()) {
            $duplicates = $orders->duplicates()->values()->implode(', ');
            throw new \DomainException(
                "Region order values must be unique. Duplicates found: {$duplicates}."
            );
        }
    }

    private function assertOrderIsSequential(LayoutRegionValueObject $layout): void
    {
        $orders = $layout->getRegions()
            ->pluck('order')
            ->sort()
            ->values()
            ->all();

        $expected = range(1, count($orders));

        if ($orders !== $expected) {
            throw new \DomainException(
                'Region order values must be sequential starting from 1. Got: ' . implode(', ', $orders) . '.'
            );
        }
    }

    private function assertSingleCenterRegion(LayoutRegionValueObject $layout): void
    {
        $centerCount = $layout->getRegions()
            ->filter(fn(RegionDTO $r) => $r->isCenter())
            ->count();

        if ($centerCount > 1) {
            throw new \DomainException(
                'Layout may only contain one center region. Found ' . $centerCount . '.'
            );
        }
    }
}