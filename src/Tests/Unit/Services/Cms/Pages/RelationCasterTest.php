<?php

namespace App\Tests\Unit\Services\Cms\Pages;

use App\Services\Cms\Pages\RelationCaster;
use DateTime;
use PHPUnit\Framework\TestCase;

class RelationCasterTest extends TestCase
{
    private RelationCaster $caster;

    protected function setUp(): void
    {
        parent::setUp();
        $this->caster = new RelationCaster();
    }


    public function testCastForDuplicationRemovesMetaFields(): void
    {
        $data = [
            'id' => 1,
            'page_id' => 123,
            'featured' => 1,
            'created_at' => '2024-01-01',
            'updated_at' => '2024-01-02'
        ];

        $result = $this->caster->castForDuplication('metadata', $data);

        $this->assertArrayNotHasKey('id', $result);
        $this->assertArrayNotHasKey('page_id', $result);
        $this->assertArrayNotHasKey('created_at', $result);
        $this->assertArrayNotHasKey('updated_at', $result);
        $this->assertArrayHasKey('featured', $result);
    }

    public function testCastsBooleanFields(): void
    {
        $data = [
            'featured' => '1',
            'allow_comments' => 0,
            'is_reusable_block' => 'true'
        ];

        $result = $this->caster->castForDuplication('metadata', $data);

        $this->assertIsBool($result['featured']);
        $this->assertTrue($result['featured']);
        $this->assertIsBool($result['allow_comments']);
        $this->assertFalse($result['allow_comments']);
    }

    public function testCastsDateTimeFields(): void
    {
        $datetime = new DateTime('2024-12-25 10:30:00');
        $data = [
            'publish_date' => $datetime,
            'expiry_date' => '2025-01-01 00:00:00'
        ];

        $result = $this->caster->castForDuplication('metadata', $data);

        $this->assertIsString($result['publish_date']);
        $this->assertEquals('2024-12-25 10:30:00', $result['publish_date']);
        $this->assertIsString($result['expiry_date']);
        $this->assertEquals('2025-01-01 00:00:00', $result['expiry_date']);
    }

    public function testCastsIntegerFields(): void
    {
        $data = [
            'menu_order' => '5'
        ];

        $result = $this->caster->castForDuplication('settings', $data);

        $this->assertIsInt($result['menu_order']);
        $this->assertEquals(5, $result['menu_order']);
    }

    public function testCastsFloatFields(): void
    {
        $data = [
            'latitude' => '51.5074',
            'longitude' => '-0.1278',
            'price' => '99.99'
        ];

        $result = $this->caster->castForDuplication('settings', $data);

        $this->assertIsFloat($result['latitude']);
        $this->assertEquals(51.5074, $result['latitude']);
        $this->assertIsFloat($result['price']);
        $this->assertEquals(99.99, $result['price']);
    }

    public function testCastsJsonFields(): void
    {
        $data = [
            'platforms' => ['facebook', 'twitter'],
            'pixel_ids' => json_encode(['123', '456'])
        ];

        $result = $this->caster->castForDuplication('social', $data);

        $this->assertIsString($result['platforms']);
        $this->assertEquals('["facebook","twitter"]', $result['platforms']);
        $this->assertIsString($result['pixel_ids']);
    }

    public function testHandlesNullValues(): void
    {
        $data = [
            'publish_date' => null,
            'featured' => null,
            'menu_order' => null
        ];

        $result = $this->caster->castForDuplication('metadata', $data);

        $this->assertNull($result['publish_date']);
        $this->assertNull($result['featured']);
    }

    public function testAddCastingRules(): void
    {
        $this->caster->addCastingRules('custom_relation', [
            'custom_field' => 'boolean'
        ]);

        $data = ['custom_field' => '1'];
        $result = $this->caster->castForDuplication('custom_relation', $data);

        $this->assertTrue($result['custom_field']);
    }

    public function testGetRulesForRelation(): void
    {
        $rules = $this->caster->getRulesForRelation('metadata');

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('publish_date', $rules);
        $this->assertEquals('datetime', $rules['publish_date']);
    }

    public function testHandlesUnknownRelationType(): void
    {
        $data = ['some_field' => 'value'];
        $result = $this->caster->castForDuplication('unknown_relation', $data);

        $this->assertEquals($data, $result);
    }

    public function testHandlesInvalidDateTime(): void
    {
        $data = ['publish_date' => 'invalid-date'];
        $result = $this->caster->castForDuplication('metadata', $data);

        $this->assertNull($result['publish_date']);
    }
}