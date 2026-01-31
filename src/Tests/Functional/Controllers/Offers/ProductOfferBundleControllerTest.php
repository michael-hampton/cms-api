<?php

namespace App\Tests\Functional\Controllers\Offers;

use App\Models\ProductOfferBundle;
use App\Models\ProductOfferBundleItem;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ProductOfferBundleControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    public function testIndexReturnsBundles(): void
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
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
        ]);

        ProductOfferBundleItem::create([
            'bundle_id' => $bundle->id,
            'product_offer_id' => $offer1->id,
            'quantity' => 1,
        ]);

        $response = $this->getForSite('/api/bundles');

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('bundles', $data);
    }

    public function testStoreCreatesBundle(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();
        $offer1 = $this->createProductOffer($product1->id);
        $offer2 = $this->createProductOffer($product2->id);

        $data = [
            'name' => 'New Bundle',
            'slug' => 'new-bundle',
            'description' => 'Test description',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'items' => [
                [
                    'product_offer_id' => $offer1->id,
                    'quantity' => 1,
                ],
                [
                    'product_offer_id' => $offer2->id,
                    'quantity' => 2,
                ],
            ],
        ];

        $response = $this->postForSite('/api/bundles', $data);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Bundle created successfully', $responseData['message']);
    }

    public function testShowReturnsBundle(): void
    {
        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
        ]);

        $response = $this->getForSite("/api/bundles/{$bundle->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertEquals($bundle->id, $data['bundle']['id']);
    }

    public function testUpdateModifiesBundle(): void
    {
        $bundle = ProductOfferBundle::create([
            'name' => 'Original Bundle',
            'slug' => 'original-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
        ]);

        $data = [
            'name' => 'Updated Bundle',
            'bundle_price' => 140.00,
        ];

        $response = $this->putForSite("/api/bundles/{$bundle->id}", $data);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Updated Bundle', $responseData['bundle']['name']);
    }

    public function testDestroyDeletesBundle(): void
    {
        $bundle = ProductOfferBundle::create([
            'name' => 'Test Bundle',
            'slug' => 'test-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
        ]);

        $response = $this->deleteForSite("/api/bundles/{$bundle->id}");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertDatabaseMissing('product_offer_bundles', ['id' => $bundle->id]);
    }

    public function testPublishBundleSuccessfully(): void
    {
        $bundle = ProductOfferBundle::create([
            'name' => 'Pending Bundle',
            'slug' => 'pending-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'status' => 'pending',
        ]);

        $response = $this->postForSite("/api/bundles/{$bundle->id}/publish");
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertEquals('Bundle published successfully', $data['message']);

        $updatedBundle = ProductOfferBundle::find($bundle->id);
        $this->assertEquals('published', $updatedBundle->status);
    }

    public function testRejectBundleWithReason(): void
    {
        $bundle = ProductOfferBundle::create([
            'name' => 'Pending Bundle',
            'slug' => 'pending-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'status' => 'pending',
        ]);

        $reason = 'Pricing too low';
        $response = $this->postForSite(
            "/api/bundles/{$bundle->id}/reject",
            ['reason' => $reason]
        );
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);

        $updatedBundle = ProductOfferBundle::find($bundle->id);
        $this->assertEquals('rejected', $updatedBundle->status);
        $this->assertEquals($reason, $updatedBundle->rejection_reason);
    }

    public function testRejectBundleRequiresReason(): void
    {
        $bundle = ProductOfferBundle::create([
            'name' => 'Pending Bundle',
            'slug' => 'pending-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'status' => 'pending',
        ]);

        $response = $this->postForSite("/api/bundles/{$bundle->id}/reject", []);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testIndexFiltersByStatus(): void
    {
        ProductOfferBundle::create([
            'name' => 'Published Bundle',
            'slug' => 'published-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'status' => 'published',
        ]);

        ProductOfferBundle::create([
            'name' => 'Pending Bundle',
            'slug' => 'pending-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'status' => 'pending',
        ]);

        $response = $this->getForSite('/api/bundles?status=published');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        foreach ($data['bundles']['items'] as $bundle) {
            $this->assertEquals('published', $bundle['status']);
        }
    }
}