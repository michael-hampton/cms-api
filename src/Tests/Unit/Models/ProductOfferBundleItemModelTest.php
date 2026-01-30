<?php

namespace App\Tests\Unit\Models;

use App\Models\ProductOfferBundle;
use App\Models\ProductOfferBundleItem;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

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
}