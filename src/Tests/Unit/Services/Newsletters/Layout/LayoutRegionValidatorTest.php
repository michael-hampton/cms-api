<?php

namespace App\Tests\Unit\Services\Newsletters\Layout;

use App\DTO\Newsletters\Layout\LayoutRegionValueObject;
use App\Services\Newsletter\Layout\LayoutRegionValidator;
use PHPUnit\Framework\TestCase;

class LayoutRegionValidatorTest extends TestCase
{
    private LayoutRegionValidator $validator;

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

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new LayoutRegionValidator();
    }
}