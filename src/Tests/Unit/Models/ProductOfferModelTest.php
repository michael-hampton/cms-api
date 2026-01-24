<?php

namespace App\Tests\Unit\Models;

use App\Models\ProductOffer;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ProductOfferModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIsCurrentlyActiveReturnsTrueForActiveOffer(): void
    {
        $product = $this->createProduct();

        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $this->assertTrue($offer->isCurrentlyActive());
    }

    public function testIsCurrentlyActiveReturnsFalseForInactiveOffer(): void
    {
        $product = $this->createProduct();

        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => false,
        ]);

        $this->assertFalse($offer->isCurrentlyActive());
    }

    public function testIsCurrentlyActiveReturnsFalseForExpiredOffer(): void
    {
        $product = $this->createProduct();

        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'is_active' => true,
        ]);

        $this->assertFalse($offer->isCurrentlyActive());
    }

    public function testGetDiscountPercentageAttribute(): void
    {
        $product = $this->createProduct(['price' => 100]);

        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 80,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $offer = $offer->fresh(['product']);

        $this->assertEquals(20, $offer->discount_percentage);
    }

    public function testScopeActive(): void
    {
        $product = $this->createProduct();

        // Active offer
        ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        // Expired offer
        ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 69.99,
            'start_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'is_active' => true,
        ]);

        $activeOffers = ProductOffer::active()->get();

        $this->assertCount(1, $activeOffers);
    }

    public function testScopeForProduct(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        ProductOffer::create([
            'product_id' => $product1->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        ProductOffer::create([
            'product_id' => $product2->id,
            'sale_price' => 89.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $offers = ProductOffer::forProduct($product1->id)->get();

        $this->assertCount(1, $offers);
        $this->assertEquals($product1->id, $offers->first()->product_id);
    }
}