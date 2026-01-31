<?php

namespace App\Tests\Unit\Models;

use App\Models\ProductOfferBundle;
use App\Models\ProductOfferBundleItem;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use Exception;

class ProductOfferBundleItemModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testBundleRelationship(): void
    {
        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id);

        $item = ProductOfferBundleItem::create([
            'bundle_id' => $bundle->id,
            'product_offer_id' => $offer->id,
            'quantity' => 2,
        ]);

        $item = $item->fresh(['bundle']);

        $this->assertNotNull($item->bundle);
        $this->assertEquals($bundle->id, $item->bundle->id);
    }

    public function testProductOfferRelationship(): void
    {
        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id);

        $item = ProductOfferBundleItem::create([
            'bundle_id' => $bundle->id,
            'product_offer_id' => $offer->id,
            'quantity' => 2,
        ]);

        $item = $item->fresh(['productOffer']);

        $this->assertNotNull($item->productOffer);
        $this->assertEquals($offer->id, $item->productOffer->id);
    }

    public function testProductRelationship(): void
    {
        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $product = $this->createProduct();

        $item = ProductOfferBundleItem::create([
            'bundle_id' => $bundle->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $item = $item->fresh(['product']);

        $this->assertNotNull($item->product);
        $this->assertEquals($product->id, $item->product->id);
    }

    public function testCannotHaveBothProductAndOffer(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Bundle item must have either product or product offer');

        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id);

        $item = ProductOfferBundleItem::create([
            'bundle_id' => $bundle->id,
            'product_id' => $product->id,
            'product_offer_id' => $offer->id,
            'quantity' => 1,
        ]);

        $item->validate();
    }

    public function testMustHaveEitherProductOrOffer(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Bundle item must have either product or product offer');

        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $item = ProductOfferBundleItem::create([
            'bundle_id' => $bundle->id,
            'quantity' => 1,
        ]);

        $item->validate();
    }

    public function testGetEffectivePriceFromProduct(): void
    {
        $product = $this->createProduct(['price' => 100.00]);

        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $item = ProductOfferBundleItem::create([
            'bundle_id' => $bundle->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $item = $item->fresh(['product']);

        $this->assertEquals(100.00, $item->getEffectivePrice());
    }

    public function testGetEffectivePriceFromOffer(): void
    {
        $product = $this->createProduct(['price' => 100.00]);
        $offer = $this->createProductOffer($product->id, ['sale_price' => 80.00]);

        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $item = ProductOfferBundleItem::create([
            'bundle_id' => $bundle->id,
            'product_offer_id' => $offer->id,
            'quantity' => 1,
        ]);

        $item = $item->fresh(['productOffer']);

        $this->assertEquals(80.00, $item->getEffectivePrice());
    }

    public function testGetEffectiveProduct(): void
    {
        $product = $this->createProduct();

        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
        ]);

        $item = ProductOfferBundleItem::create([
            'bundle_id' => $bundle->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $item = $item->fresh(['product']);

        $effectiveProduct = $item->getEffectiveProduct();
        $this->assertNotNull($effectiveProduct);
        $this->assertEquals($product->id, $effectiveProduct->id);
    }

}