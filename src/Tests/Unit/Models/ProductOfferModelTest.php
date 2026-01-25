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

    public function testScopePublished(): void
    {
        $product = $this->createProduct();

        ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'published',
        ]);

        ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 69.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'pending',
        ]);

        $published = ProductOffer::published()->get();

        $this->assertCount(1, $published);
        $this->assertEquals('published', $published->first()->status);
    }

    public function testScopePending(): void
    {
        $product = $this->createProduct();

        ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'pending',
        ]);

        ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 69.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'published',
        ]);

        $pending = ProductOffer::pending()->get();

        $this->assertCount(1, $pending);
        $this->assertEquals('pending', $pending->first()->status);
    }

    public function testScopeRejected(): void
    {
        $product = $this->createProduct();

        ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'rejected',
        ]);

        ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 69.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'pending',
        ]);

        $rejected = ProductOffer::rejected()->get();

        $this->assertCount(1, $rejected);
        $this->assertEquals('rejected', $rejected->first()->status);
    }

    public function testVoucherRelationship(): void
    {
        $product = $this->createProduct();
        $voucher = $this->createVoucher();

        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'voucher_id' => $voucher->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $offer = $offer->fresh(['voucher']);

        $this->assertNotNull($offer->voucher);
        $this->assertEquals($voucher->id, $offer->voucher->id);
    }

    public function testPublisherRelationship(): void
    {
        $user = $this->createUser();
        $product = $this->createProduct();

        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'published',
            'published_by' => $user->id,
            'published_at' => now(),
        ]);

        $offer = $offer->fresh(['publisher']);

        $this->assertNotNull($offer->publisher);
        $this->assertEquals($user->id, $offer->publisher->id);
    }

    public function testRejectorRelationship(): void
    {
        $user = $this->createUser();
        $product = $this->createProduct();

        $offer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'rejected',
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'rejection_reason' => 'Test reason',
        ]);

        $offer = $offer->fresh(['rejector']);

        $this->assertNotNull($offer->rejector);
        $this->assertEquals($user->id, $offer->rejector->id);
    }

    public function testCanBePublished(): void
    {
        $product = $this->createProduct();

        $pendingOffer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'pending',
        ]);

        $publishedOffer = ProductOffer::create([
            'product_id' => $product->id,
            'sale_price' => 69.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'status' => 'published',
        ]);

        $this->assertTrue($pendingOffer->canBePublished());
        $this->assertFalse($publishedOffer->canBePublished());
    }

}