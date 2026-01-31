<?php

namespace App\Tests\Functional\Controllers\Front;

use App\Models\ProductOfferBundle;
use App\Models\ProductOfferBundleItem;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class BundleListControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testShowPageReturnsViewForValidBundle(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();
        $offer1 = $this->createProductOffer($product1->id);
        $offer2 = $this->createProductOffer($product2->id);

        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'discount_percentage' => 25,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'status' => 'published',
        ]);

        ProductOfferBundleItem::create([
            'bundle_id' => $bundle->id,
            'product_offer_id' => $offer1->id,
            'quantity' => 1,
        ]);

        ProductOfferBundleItem::create([
            'bundle_id' => $bundle->id,
            'product_offer_id' => $offer2->id,
            'quantity' => 1,
        ]);

        $response = $this->getForSite("/bundles/{$bundle->id}");

        $this->assertResponseOk($response);
    }

    public function testShowPageReturns404ForInvalidBundle(): void
    {
        $response = $this->getForSite("/bundles/99999");

        $this->assertResponseStatus(404, $response); // Returns 404 view
    }

    public function testShowPageReturns404ForUnpublishedBundle(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();
        $offer1 = $this->createProductOffer($product1->id);
        $offer2 = $this->createProductOffer($product2->id);

        $bundle = ProductOfferBundle::create([
            'name' => 'Unpublished Bundle',
            'slug' => 'unpublished-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'discount_percentage' => 25,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'status' => 'pending',
        ]);

        $response = $this->getForSite("/bundles/{$bundle->id}");

        $this->assertResponseStatus(404, $response); // Returns 404 view
    }

    public function testShowPageReturns404ForInactiveBundle(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();
        $offer1 = $this->createProductOffer($product1->id);
        $offer2 = $this->createProductOffer($product2->id);

        $bundle = ProductOfferBundle::create([
            'name' => 'Inactive Bundle',
            'slug' => 'inactive-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'discount_percentage' => 25,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => false,
            'status' => 'published',
        ]);

        $response = $this->getForSite("/bundles/{$bundle->id}");

        $this->assertResponseStatus(404, $response); // Returns 404 view
    }
}