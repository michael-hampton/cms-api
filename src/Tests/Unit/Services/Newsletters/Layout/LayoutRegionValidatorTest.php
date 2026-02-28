<?php

namespace App\Tests\Unit\Services\Newsletters\Layout;

use App\DTO\Newsletters\Layout\LayoutRegionValueObject;
use App\Services\Newsletter\Layout\LayoutRegionValidator;
use PHPUnit\Framework\TestCase;

class LayoutRegionValidatorTest extends TestCase
{
    private LayoutRegionValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new LayoutRegionValidator();
    }

    public function test_valid_layout_passes(): void
    {
        $this->validator->validate($this->makeValid());
        $this->assertTrue(true);
    }

    private function makeValid(): LayoutRegionValueObject
    {
        return LayoutRegionValueObject::fromArray([
            'schema_version' => 2,
            'regions' => [
                ['id' => 'top', 'name' => 'Top', 'order' => 1, 'slots' => []],
                ['id' => 'center', 'name' => 'Center', 'order' => 2, 'slots' => []],
                ['id' => 'bottom', 'name' => 'Bottom', 'order' => 3, 'slots' => []],
            ],
        ]);
    }

    public function test_missing_center_region_throws(): void
    {
        $vo = LayoutRegionValueObject::fromArray([
            'schema_version' => 2,
            'regions' => [
                ['id' => 'top', 'name' => 'Top', 'order' => 1, 'slots' => []],
                ['id' => 'bottom', 'name' => 'Bottom', 'order' => 2, 'slots' => []],
            ],
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/center region/');
        $this->validator->validate($vo);
    }

    public function test_multiple_center_regions_throws(): void
    {
        $vo = LayoutRegionValueObject::fromArray([
            'schema_version' => 2,
            'regions' => [
                ['id' => 'center', 'name' => 'Center 1', 'order' => 1, 'slots' => []],
                ['id' => 'center', 'name' => 'Center 2', 'order' => 2, 'slots' => []],
            ],
        ]);

        $this->expectException(\DomainException::class);
        $this->validator->validate($vo);
    }

    public function test_duplicate_region_ids_throws(): void
    {
        $vo = LayoutRegionValueObject::fromArray([
            'schema_version' => 2,
            'regions' => [
                ['id' => 'top', 'name' => 'Top A', 'order' => 1, 'slots' => []],
                ['id' => 'top', 'name' => 'Top B', 'order' => 2, 'slots' => []],
                ['id' => 'center', 'name' => 'Center', 'order' => 3, 'slots' => []],
            ],
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/unique/i');
        $this->validator->validate($vo);
    }

    public function test_duplicate_order_values_throws(): void
    {
        $vo = LayoutRegionValueObject::fromArray([
            'schema_version' => 2,
            'regions' => [
                ['id' => 'top', 'name' => 'Top', 'order' => 1, 'slots' => []],
                ['id' => 'center', 'name' => 'Center', 'order' => 1, 'slots' => []],
                ['id' => 'bottom', 'name' => 'Bottom', 'order' => 3, 'slots' => []],
            ],
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/order/i');
        $this->validator->validate($vo);
    }

    public function test_non_sequential_order_throws(): void
    {
        $vo = LayoutRegionValueObject::fromArray([
            'schema_version' => 2,
            'regions' => [
                ['id' => 'top', 'name' => 'Top', 'order' => 1, 'slots' => []],
                ['id' => 'center', 'name' => 'Center', 'order' => 5, 'slots' => []],
                ['id' => 'bottom', 'name' => 'Bottom', 'order' => 9, 'slots' => []],
            ],
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/sequential/i');
        $this->validator->validate($vo);
    }

    public function test_single_region_layout_with_center_passes(): void
    {
        $vo = LayoutRegionValueObject::fromArray([
            'schema_version' => 2,
            'regions' => [
                ['id' => 'center', 'name' => 'Center', 'order' => 1, 'slots' => []],
            ],
        ]);

        $this->validator->validate($vo);
        $this->assertTrue(true);
    }

    public function test_passes_valid_layout_with_center_region(): void
    {
        $layout = LayoutRegionValueObject::fromArray($this->validThreeRegionLayout());

        // Must not throw.
        $this->validator->validate($layout);
        $this->assertTrue(true);
    }

    public function test_throws_when_region_ids_are_not_unique(): void
    {
        $data = $this->validThreeRegionLayout();
        $data['regions'][2]['id'] = 'top'; // Duplicate of regions[0]

        $layout = LayoutRegionValueObject::fromArray($data);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/unique/i');
        $this->validator->validate($layout);
    }

    public function test_throws_when_order_values_are_not_unique(): void
    {
        $data = $this->validThreeRegionLayout();
        $data['regions'][1]['order'] = 1; // Duplicate of regions[0]

        $layout = LayoutRegionValueObject::fromArray($data);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/unique/i');
        $this->validator->validate($layout);
    }

    public function test_throws_when_order_values_are_not_sequential(): void
    {
        $data = $this->validThreeRegionLayout();
        $data['regions'][2]['order'] = 5; // Gap: 1, 2, 5 is not sequential

        $layout = LayoutRegionValueObject::fromArray($data);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/sequential/i');
        $this->validator->validate($layout);
    }

    public function test_throws_when_order_does_not_start_from_one(): void
    {
        $data = $this->validThreeRegionLayout();
        $data['regions'][0]['order'] = 2;
        $data['regions'][1]['order'] = 3;
        $data['regions'][2]['order'] = 4;

        $layout = LayoutRegionValueObject::fromArray($data);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/sequential/i');
        $this->validator->validate($layout);
    }

    public function test_passes_with_center_region_only(): void
    {
        $layout = LayoutRegionValueObject::fromArray([
            'schema_version' => 2,
            'regions' => [
                ['id' => 'center', 'name' => 'Content Region', 'order' => 1, 'slots' => []],
            ],
        ]);

        $this->validator->validate($layout);
        $this->assertTrue(true);
    }

    public function test_default_layout_passes_validation(): void
    {
        $layout = LayoutRegionValueObject::default();

        $this->validator->validate($layout);
        $this->assertTrue(true);
    }


    // ── Helpers ───────────────────────────────────────────────────────────────

    private function validThreeRegionLayout(): array
    {
        return [
            'schema_version' => 2,
            'regions' => [
                ['id' => 'top', 'name' => 'Top Region', 'order' => 1, 'slots' => []],
                ['id' => 'center', 'name' => 'Content Region', 'order' => 2, 'slots' => []],
                ['id' => 'bottom', 'name' => 'Footer Region', 'order' => 3, 'slots' => []],
            ],
        ];
    }
}