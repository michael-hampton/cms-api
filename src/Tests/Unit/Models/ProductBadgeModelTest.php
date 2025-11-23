<?php

namespace App\Tests\Unit\Models;

use App\Models\Product;
use App\Models\ProductBadge;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ProductBadgeModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testCreateProductBadge()
    {
        $product = $this->createProduct();

        $badge = ProductBadge::create([
            'product_id' => $product->id,
            'badge_type' => 'bestseller',
            'label' => 'Bestseller',
            'color' => '#ff0000',
            'is_active' => true
        ]);

        $this->assertInstanceOf(ProductBadge::class, $badge);
        $this->assertEquals('bestseller', $badge->badge_type);
        $this->assertEquals('Bestseller', $badge->label);
    }

    public function testProductBadgeBelongsToProduct()
    {
        $product = $this->createProduct();

        $badge = ProductBadge::create([
            'product_id' => $product->id,
            'badge_type' => 'new',
            'label' => 'New',
            'color' => '#00ff00',
            'is_active' => true
        ]);

        $this->assertInstanceOf(Product::class, $badge->product());
        $this->assertEquals($product->id, $badge->product()->id);
    }

    public function testIsValidReturnsTrueForActiveBadge()
    {
        $product = $this->createProduct();

        $badge = ProductBadge::create([
            'product_id' => $product->id,
            'badge_type' => 'featured',
            'label' => 'Featured',
            'color' => '#0000ff',
            'is_active' => true
        ]);

        $this->assertTrue($badge->isValid());
    }

    public function testIsValidReturnsFalseForInactiveBadge()
    {
        $product = $this->createProduct();

        $badge = ProductBadge::create([
            'product_id' => $product->id,
            'badge_type' => 'sale',
            'label' => 'On Sale',
            'color' => '#ff00ff',
            'is_active' => false
        ]);

        $this->assertFalse($badge->isValid());
    }

    public function testIsValidWithValidFromDate()
    {
        $product = $this->createProduct();

        $badge = ProductBadge::create([
            'product_id' => $product->id,
            'badge_type' => 'trending',
            'label' => 'Trending',
            'color' => '#ffff00',
            'valid_from' => now_datetime()->subDays(5)->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        $this->assertTrue($badge->isValid());
    }

    public function testIsValidWithFutureValidFromDate()
    {
        $product = $this->createProduct();

        $badge = ProductBadge::create([
            'product_id' => $product->id,
            'badge_type' => 'upcoming',
            'label' => 'Coming Soon',
            'color' => '#00ffff',
            'valid_from' => now_datetime()->addDays(5)->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        $this->assertFalse($badge->isValid());
    }

    public function testIsValidWithValidUntilDate()
    {
        $product = $this->createProduct();

        $badge = ProductBadge::create([
            'product_id' => $product->id,
            'badge_type' => 'limited',
            'label' => 'Limited Time',
            'color' => '#ff6600',
            'valid_until' => now_datetime()->addDays(5)->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        $this->assertTrue($badge->isValid());
    }

    public function testIsValidWithExpiredValidUntilDate()
    {
        $product = $this->createProduct();

        $badge = ProductBadge::create([
            'product_id' => $product->id,
            'badge_type' => 'expired',
            'label' => 'Expired',
            'color' => '#666666',
            'valid_until' => now_datetime()->subDays(5)->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        $this->assertFalse($badge->isValid());
    }

    public function testDateCasts()
    {
        $product = $this->createProduct();

        $validFrom = now_datetime()->subDays(10);
        $validUntil = now_datetime()->addDays(10);

        $badge = ProductBadge::create([
            'product_id' => $product->id,
            'badge_type' => 'seasonal',
            'label' => 'Seasonal',
            'color' => '#00cc00',
            'valid_from' => $validFrom->format('Y-m-d H:i:s'),
            'valid_until' => $validUntil->format('Y-m-d H:i:s'),
            'is_active' => true
        ]);

        $this->assertInstanceOf(\DateTime::class, $badge->valid_from);
        $this->assertInstanceOf(\DateTime::class, $badge->valid_until);
    }

    public function testSortOrder()
    {
        $product = $this->createProduct();

        $badge = ProductBadge::create([
            'product_id' => $product->id,
            'badge_type' => 'test',
            'label' => 'Test',
            'color' => '#000000',
            'sort_order' => 5,
            'is_active' => true
        ]);

        $this->assertEquals(5, $badge->sort_order);
    }

    public function testTimestamps()
    {
        $product = $this->createProduct();

        $badge = ProductBadge::create([
            'product_id' => $product->id,
            'badge_type' => 'new',
            'label' => 'New',
            'color' => '#ff0000',
            'is_active' => true
        ]);

        $this->assertNotNull($badge->created_at);
        $this->assertNotNull($badge->updated_at);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }
}