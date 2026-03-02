<?php

namespace App\Tests\Unit\Services\Newsletters\Layout;

use App\DTO\Newsletters\Layout\LayoutRegionValueObject;
use PHPUnit\Framework\TestCase;

class LayoutRegionValueObjectTest extends TestCase
{
    public function test_hydrates_regions_from_array(): void
    {
        $vo = LayoutRegionValueObject::fromArray($this->makeDefinition());

        $this->assertCount(3, $vo->getRegions());
    }

    private function makeDefinition(?array $regions = null): array
    {
        return [
            'schema_version' => 2,
            'regions' => $regions ?? [
                    ['id' => 'top', 'name' => 'Top', 'order' => 1, 'slots' => []],
                    ['id' => 'center', 'name' => 'Center', 'order' => 2, 'slots' => []],
                    ['id' => 'bottom', 'name' => 'Bottom', 'order' => 3, 'slots' => []],
                ],
        ];
    }

    public function test_getOrderedRegions_sorts_by_order_ascending(): void
    {
        $vo = LayoutRegionValueObject::fromArray([
            'schema_version' => 2,
            'regions' => [
                ['id' => 'bottom', 'name' => 'Bottom', 'order' => 3, 'slots' => []],
                ['id' => 'top', 'name' => 'Top', 'order' => 1, 'slots' => []],
                ['id' => 'center', 'name' => 'Center', 'order' => 2, 'slots' => []],
            ],
        ]);

        $ordered = $vo->getOrderedRegions()->pluck('id')->all();

        $this->assertEquals(['top', 'center', 'bottom'], $ordered);
    }

    public function test_hasCenterRegion_returns_true_when_center_present(): void
    {
        $vo = LayoutRegionValueObject::fromArray($this->makeDefinition());
        $this->assertTrue($vo->hasCenterRegion());
    }

    public function test_hasCenterRegion_returns_false_when_missing(): void
    {
        $vo = LayoutRegionValueObject::fromArray([
            'schema_version' => 2,
            'regions' => [
                ['id' => 'top', 'name' => 'Top', 'order' => 1, 'slots' => []],
            ],
        ]);

        $this->assertFalse($vo->hasCenterRegion());
    }

    public function test_getCenterRegion_returns_center(): void
    {
        $vo = LayoutRegionValueObject::fromArray($this->makeDefinition());
        $center = $vo->getCenterRegion();

        $this->assertNotNull($center);
        $this->assertEquals('center', $center->id);
    }

    public function test_withCenterSlots_replaces_center_slots_only(): void
    {
        $vo = LayoutRegionValueObject::fromArray($this->makeDefinition());
        $newSlot = ['name' => 'content', 'blocks' => [['type' => 'text', 'data' => []]]];
        $updated = $vo->withCenterSlots([$newSlot]);

        $this->assertCount(1, $updated->getCenterRegion()->slots);
        $this->assertEquals('content', $updated->getCenterRegion()->slots[0]->name);

        // Top and bottom unchanged
        $this->assertEmpty($updated->getRegionById('top')->slots);
        $this->assertEmpty($updated->getRegionById('bottom')->slots);
    }

    public function test_withCenterSlots_returns_new_instance(): void
    {
        $vo = LayoutRegionValueObject::fromArray($this->makeDefinition());
        $updated = $vo->withCenterSlots([]);

        $this->assertNotSame($vo, $updated);
    }

    public function test_default_returns_v2_layout_with_three_regions(): void
    {
        $vo = LayoutRegionValueObject::default();

        $this->assertTrue($vo->isV2());
        $this->assertCount(3, $vo->getRegions());
        $this->assertTrue($vo->hasCenterRegion());
    }

    public function test_toArray_round_trips(): void
    {
        $definition = $this->makeDefinition();
        $vo = LayoutRegionValueObject::fromArray($definition);

        $this->assertEquals($definition, $vo->toArray());
    }

    public function test_schema_version_1_is_not_v2(): void
    {
        $vo = LayoutRegionValueObject::fromArray(['schema_version' => 1, 'regions' => []]);
        $this->assertFalse($vo->isV2());
    }
}