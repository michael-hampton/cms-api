<?php

namespace App\Tests\Unit\Models;

use App\Models\Member;
use App\Models\ProductImage;
use App\Models\ProductMerchant;
use App\Models\ProductVariant;
use App\Models\RegionSet;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ProductVariantModelTest extends FunctionalTestCase
{
    use CreatesTestData;
    public function test_variant_belongs_to_product()
    {
        $product = $this->createProduct();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-001',
            'price' => 99.99,
            'is_active' => true,
            'attributes' => '[{"name":"Size","value":"Small"},{"name":"Color","value":"Red"}]',
        ]);
        $this->assertNotNull($variant->product);
        $this->assertEquals($product->id, $variant->product->id);
    }

    public function test_variant_has_images_relationship()
    {
        $product = $this->createProduct();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-001',
            'price' => 99.99,
            'is_active' => true,
            'attributes' => '[{"name":"Size","value":"Small"},{"name":"Color","value":"Red"}]',
        ]);

        $image = ProductImage::create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'url' => 'https://example.com/variant.jpg',
            'is_primary' => true
        ]);

        $this->assertCount(1, $variant->images);
        $this->assertEquals($image->id, $variant->images->first()->id);
    }

    public function test_variant_has_merchants_relationship()
    {
        $product = $this->createProduct();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-001',
            'price' => 99.99,
            'is_active' => true,
            'attributes' => '[{"name":"Size","value":"Small"},{"name":"Color","value":"Red"}]',
        ]);

        $merchant = $this->createMerchant();
        $productMerchant = ProductMerchant::create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'merchant_id' => $merchant->id,
            'url' => 'https://example.com/variant.jpg',
            'price' => 89.99,
            'is_available' => true
        ]);

        $this->assertCount(1, $variant->merchants);
        $this->assertEquals($productMerchant->id, $variant->merchants->first()->id);
    }

    public function test_final_price_returns_variant_price()
    {
        $product = $this->createProduct(['price' => 100]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-001',
            'price' => 99.99,
            'is_active' => true,
            'attributes' => '[{"name":"Size","value":"Small"},{"name":"Color","value":"Red"}]',
        ]);

        $this->assertEquals(99.99, $variant->final_price);
    }

    public function test_discount_percentage_calculates_correctly()
    {
        $product = $this->createProduct();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-001',
            'price' => 100,
            'sale_price' => 80,
            'is_active' => true,
            'attributes' => '[{"name":"Size","value":"Small"},{"name":"Color","value":"Red"}]',
        ]);

        $this->assertEquals(20, $variant->discount_percentage);
    }

    public function test_discount_percentage_returns_zero_when_no_sale_price()
    {
        $product = $this->createProduct();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-001',
            'price' => 100,
            'sale_price' => null,
            'is_active' => true,
            'attributes' => '[{"name":"Size","value":"Small"},{"name":"Color","value":"Red"}]',
        ]);

        $this->assertEquals(0, $variant->discount_percentage);
    }

    public function test_discount_percentage_returns_zero_when_sale_price_higher()
    {
        $product = $this->createProduct();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-001',
            'price' => 100,
            'sale_price' => 120,
            'is_active' => true,
            'attributes' => '[{"name":"Size","value":"Small"},{"name":"Color","value":"Red"}]',
        ]);

        $this->assertEquals(0, $variant->discount_percentage);
    }

    public function test_attributes_cast_to_array()
    {
        $product = $this->createProduct();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-001',
            'price' => 100,
            'attributes' => ['color' => 'red', 'size' => 'large'],
            'is_active' => true
        ]);

        $this->assertIsArray($variant->attributes);

        $this->assertEquals('red', $variant->attributes['color']);
        $this->assertEquals('large', $variant->attributes['size']);
    }

    public function test_variant_without_region_sets_is_visible_to_any_member(): void
    {
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);
        $variant->load('regionSets');

        $member = $this->createMemberWithTerritory(territoryId: 1);

        $this->assertTrue($variant->isVisibleToMember($member));
    }

    public function test_variant_with_region_set_is_visible_to_member_in_covered_territory(): void
    {
        [$regionSet] = $this->createRegionSetWithTerritory(territoryId: 4);
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);
        $variant->regionSets(true)->attach($regionSet->id);
        $variant->load('regionSets.territories');

        $member = $this->createMemberWithTerritory(territoryId: 4);

        $this->assertTrue($variant->isVisibleToMember($member));
    }

    public function test_variant_with_region_set_is_not_visible_to_member_outside_territory(): void
    {
        [$regionSet] = $this->createRegionSetWithTerritory(territoryId: 4);
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);
        $variant->regionSets(true)->attach($regionSet->id);
        $variant->load('regionSets.territories');

        $member = $this->createMemberWithTerritory(territoryId: 77);

        $this->assertFalse($variant->isVisibleToMember($member));
    }

    public function test_variant_visibility_is_independent_of_product_visibility(): void
    {
        // Product has no region restriction.
        // Variant IS restricted to territory 6.
        // A member in territory 9 should see the product but NOT the variant.
        [$regionSet] = $this->createRegionSetWithTerritory(territoryId: 6);
        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);
        $variant->regionSets(true)->attach($regionSet->id);

        $product->load('regionSets');
        $variant->load('regionSets.territories');

        $member = $this->createMemberWithTerritory(territoryId: 9);

        $this->assertTrue($product->isVisibleToMember($member));
        $this->assertFalse($variant->isVisibleToMember($member));
    }

    public function test_variant_region_sets_sync_replaces_existing(): void
    {
        [$regionSetA] = $this->createRegionSetWithTerritory(territoryId: 1);
        [$regionSetB] = $this->createRegionSetWithTerritory(territoryId: 2);

        $product = $this->createProduct();
        $variant = $this->createProductVariant($product->id);
        $variant->regionSets(true)->attach($regionSetA->id);

        $variant->regionSets(true)->sync([$regionSetB->id]);
        $variant->load('regionSets');

        $this->assertCount(1, $variant->regionSets);
        $this->assertEquals($regionSetB->id, $variant->regionSets->first()->id);
    }

    public function test_detaching_all_region_sets_makes_product_globally_visible(): void
    {
        [$regionSet] = $this->createRegionSetWithTerritory(territoryId: 3);
        $product = $this->createProduct();
        $product->regionSets(true)->attach($regionSet->id);

        // Remove all restrictions.
        $product->regionSets(true)->sync([]);
        $product->load('regionSets');

        $member = $this->createMemberWithTerritory(territoryId: 99);
        $this->assertTrue($product->isVisibleToMember($member));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Creates a RegionSet with one Territory attached.
     * Returns [$regionSet, $territory].
     */
    private function createRegionSetWithTerritory(int $territoryId): array
    {
        $regionSet = RegionSet::create(['name' => "Region Set {$territoryId}", 'slug' => 'test-' . uniqid(), 'site_id' => $this->siteId]);

        $territory = $this->createTerritory(['id' => $territoryId, 'region_set_id' => $regionSet->id]);

        return [$regionSet, $territory];
    }

    private function createMemberWithTerritory(int $territoryId): Member
    {
        $member = \Mockery::mock(Member::class)->makePartial();
        $member->shouldReceive('hasTerritoryId')->andReturn(true);
        $member->shouldReceive('getTerritoryId')->andReturn($territoryId);
        return $member;
    }

    private function createMemberWithoutTerritory(): Member
    {
        $member = \Mockery::mock(Member::class)->makePartial();
        $member->shouldReceive('hasTerritoryId')->andReturn(false);
        return $member;
    }

}