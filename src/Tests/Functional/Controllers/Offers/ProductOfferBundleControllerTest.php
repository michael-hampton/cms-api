<?php

namespace App\Tests\Functional\Controllers\Offers;

use App\Models\ProductOfferBundle;
use App\Models\ProductOfferBundleItem;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class ProductOfferBundleControllerTest extends FunctionalTestCase
{
    use CreatesTestData;

    // =========================================================================
    // GET /api/bundles
    // =========================================================================

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

    // =========================================================================
    // POST /api/bundles — StoreProductOfferBundleRequest validation
    // =========================================================================

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
                ['product_offer_id' => $offer1->id, 'quantity' => 1],
                ['product_offer_id' => $offer2->id, 'quantity' => 2],
            ],
        ];

        $response = $this->postForSite('/api/bundles', $data);
        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Bundle created successfully', $responseData['message']);
    }

    public function testStoreValidatesRequiredName(): void
    {
        $offer1 = $this->createProductOffer($this->createProduct()->id);
        $offer2 = $this->createProductOffer($this->createProduct()->id);

        $response = $this->postForSite('/api/bundles', [
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'items' => [
                ['product_offer_id' => $offer1->id, 'quantity' => 1],
                ['product_offer_id' => $offer2->id, 'quantity' => 1],
            ],
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('name', $data['errors']);
    }

    public function testStoreValidatesRequiredBundlePrice(): void
    {
        $offer1 = $this->createProductOffer($this->createProduct()->id);
        $offer2 = $this->createProductOffer($this->createProduct()->id);

        $response = $this->postForSite('/api/bundles', [
            'name' => 'Test Bundle',
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'items' => [
                ['product_offer_id' => $offer1->id, 'quantity' => 1],
                ['product_offer_id' => $offer2->id, 'quantity' => 1],
            ],
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('bundle_price', $data['errors']);
    }

    public function testStoreValidatesRequiredStartDate(): void
    {
        $offer1 = $this->createProductOffer($this->createProduct()->id);
        $offer2 = $this->createProductOffer($this->createProduct()->id);

        $response = $this->postForSite('/api/bundles', [
            'name' => 'Test Bundle',
            'bundle_price' => 150.00,
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'items' => [
                ['product_offer_id' => $offer1->id, 'quantity' => 1],
                ['product_offer_id' => $offer2->id, 'quantity' => 1],
            ],
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('start_date', $data['errors']);
    }

    public function testStoreValidatesRequiredEndDate(): void
    {
        $offer1 = $this->createProductOffer($this->createProduct()->id);
        $offer2 = $this->createProductOffer($this->createProduct()->id);

        $response = $this->postForSite('/api/bundles', [
            'name' => 'Test Bundle',
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'items' => [
                ['product_offer_id' => $offer1->id, 'quantity' => 1],
                ['product_offer_id' => $offer2->id, 'quantity' => 1],
            ],
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('end_date', $data['errors']);
    }

    public function testStoreValidatesEndDateAfterStartDate(): void
    {
        $offer1 = $this->createProductOffer($this->createProduct()->id);
        $offer2 = $this->createProductOffer($this->createProduct()->id);

        $response = $this->postForSite('/api/bundles', [
            'name' => 'Test Bundle',
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'end_date' => date('Y-m-d H:i:s'),
            'items' => [
                ['product_offer_id' => $offer1->id, 'quantity' => 1],
                ['product_offer_id' => $offer2->id, 'quantity' => 1],
            ],
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('end_date', $data['errors']);
    }

    public function testStoreValidatesRequiredItems(): void
    {
        $response = $this->postForSite('/api/bundles', [
            'name' => 'Test Bundle',
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('items', $data['errors']);
    }

    public function testStoreValidatesItemsMinimumTwo(): void
    {
        $offer1 = $this->createProductOffer($this->createProduct()->id);

        // Only one item — must have at least 2
        $response = $this->postForSite('/api/bundles', [
            'name' => 'Test Bundle',
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'items' => [
                ['product_offer_id' => $offer1->id, 'quantity' => 1],
            ],
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreValidatesItemRequiresProductIdOrProductOfferId(): void
    {
        // Items with neither product_id nor product_offer_id should fail
        $response = $this->postForSite('/api/bundles', [
            'name' => 'Test Bundle',
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'items' => [
                ['quantity' => 1],
                ['quantity' => 1],
            ],
        ]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testStoreAcceptsItemWithOnlyProductOfferId(): void
    {
        $offer1 = $this->createProductOffer($this->createProduct()->id);
        $offer2 = $this->createProductOffer($this->createProduct()->id);

        $response = $this->postForSite('/api/bundles', [
            'name' => 'Offer-only Bundle',
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'slug' => uniqid(),
            'items' => [
                ['product_offer_id' => $offer1->id, 'quantity' => 1],
                ['product_offer_id' => $offer2->id, 'quantity' => 1],
            ],
        ]);

        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testStoreValidatesItemQuantityMinimumOne(): void
    {
        $offer1 = $this->createProductOffer($this->createProduct()->id);
        $offer2 = $this->createProductOffer($this->createProduct()->id);

        $response = $this->postForSite('/api/bundles', [
            'name' => 'Test Bundle',
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'items' => [
                ['product_offer_id' => $offer1->id, 'quantity' => 0],
                ['product_offer_id' => $offer2->id, 'quantity' => 1],
            ],
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('items.0.quantity', $data['errors']);
    }

    public function testStoreValidatesBundlePriceNotNegative(): void
    {
        $offer1 = $this->createProductOffer($this->createProduct()->id);
        $offer2 = $this->createProductOffer($this->createProduct()->id);

        $response = $this->postForSite('/api/bundles', [
            'name' => 'Test Bundle',
            'bundle_price' => -10.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'items' => [
                ['product_offer_id' => $offer1->id, 'quantity' => 1],
                ['product_offer_id' => $offer2->id, 'quantity' => 1],
            ],
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('bundle_price', $data['errors']);
    }

    public function testStoreValidatesStatusEnum(): void
    {
        $offer1 = $this->createProductOffer($this->createProduct()->id);
        $offer2 = $this->createProductOffer($this->createProduct()->id);

        $response = $this->postForSite('/api/bundles', [
            'name' => 'Test Bundle',
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'status' => 'invalid_status',
            'items' => [
                ['product_offer_id' => $offer1->id, 'quantity' => 1],
                ['product_offer_id' => $offer2->id, 'quantity' => 1],
            ],
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('status', $data['errors']);
    }

    public function testStoreAcceptsValidStatusValues(): void
    {
        foreach (['draft', 'published', 'rejected'] as $status) {
            $offer1 = $this->createProductOffer($this->createProduct()->id);
            $offer2 = $this->createProductOffer($this->createProduct()->id);

            $response = $this->postForSite('/api/bundles', [
                'name' => "Bundle {$status}",
                'slug' => "bundle-{$status}-" . uniqid(),
                'bundle_price' => 150.00,
                'start_date' => date('Y-m-d H:i:s'),
                'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
                'status' => $status,
                'items' => [
                    ['product_offer_id' => $offer1->id, 'quantity' => 1],
                    ['product_offer_id' => $offer2->id, 'quantity' => 1],
                ],
            ]);

            $this->assertEquals(201, $response->getStatusCode(), "Expected 201 for status: {$status}");
        }
    }

    public function testStoreAcceptsProductIdInItems(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $response = $this->postForSite('/api/bundles', [
            'name' => 'Product Bundle',
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'slug' => 'test',
            'items' => [
                ['product_id' => $product1->id, 'quantity' => 1],
                ['product_id' => $product2->id, 'quantity' => 1],
            ],
        ]);

        $this->assertEquals(201, $response->getStatusCode());
    }

    // =========================================================================
    // GET /api/bundles/{id}
    // =========================================================================

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

    public function testShowReturns404ForNonexistentBundle(): void
    {
        $response = $this->getForSite('/api/bundles/99999');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Bundle not found', $data['data']['message']);
    }

    // =========================================================================
    // PUT /api/bundles/{id} — UpdateProductOfferBundleRequest validation
    // =========================================================================

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

        $response = $this->putForSite("/api/bundles/{$bundle->id}", [
            'name' => 'Updated Bundle',
            'bundle_price' => 140.00,
        ]);

        $responseData = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($responseData['success']);
        $this->assertEquals('Updated Bundle', $responseData['bundle']['name']);
    }

    public function testUpdateAllowsPartialUpdate(): void
    {
        $bundle = ProductOfferBundle::create([
            'name' => 'Bundle',
            'slug' => 'bundle',
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
        ]);

        // Only updating is_active — all fields are optional on update
        $response = $this->putForSite("/api/bundles/{$bundle->id}", [
            'is_active' => false,
        ]);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testUpdateValidatesEndDateAfterStartDate(): void
    {
        $bundle = ProductOfferBundle::create([
            'name' => 'Bundle',
            'slug' => 'bundle-date-test',
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
        ]);

        $response = $this->putForSite("/api/bundles/{$bundle->id}", [
            'start_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'end_date' => date('Y-m-d H:i:s'),
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('end_date', $data['errors']);
    }

    public function testUpdateValidatesBundlePriceNotNegative(): void
    {
        $bundle = ProductOfferBundle::create([
            'name' => 'Bundle',
            'slug' => 'bundle-price-test',
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
        ]);

        $response = $this->putForSite("/api/bundles/{$bundle->id}", [
            'bundle_price' => -50.00,
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('bundle_price', $data['errors']);
    }

    public function testUpdateValidatesStatusEnum(): void
    {
        $bundle = ProductOfferBundle::create([
            'name' => 'Bundle',
            'slug' => 'bundle-status-test',
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
        ]);

        $response = $this->putForSite("/api/bundles/{$bundle->id}", [
            'status' => 'invalid_status',
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('status', $data['errors']);
    }

    public function testUpdateValidatesItemsMinimumTwoWhenProvided(): void
    {
        $bundle = ProductOfferBundle::create([
            'name' => 'Bundle',
            'slug' => 'bundle-items-test',
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
        ]);

        $offer = $this->createProductOffer($this->createProduct()->id);

        $response = $this->putForSite("/api/bundles/{$bundle->id}", [
            'items' => [
                ['product_offer_id' => $offer->id, 'quantity' => 1],
            ],
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('items', $data['errors']);
    }

    public function testUpdateReturns404ForNonexistentBundle(): void
    {
        $response = $this->putForSite('/api/bundles/99999', ['name' => 'Updated']);

        $data = json_decode($response->getContent(), true);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('Bundle not found', $data['data']['message']);
    }

    // =========================================================================
    // DELETE /api/bundles/{id}
    // =========================================================================

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

    // =========================================================================
    // POST /api/bundles/{id}/publish
    // =========================================================================

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

    // =========================================================================
    // POST /api/bundles/{id}/reject
    // =========================================================================

    public function testRejectBundleWithReason(): void
    {
        $bundle = ProductOfferBundle::create([
            'name' => 'Pending Bundle',
            'slug' => 'pending-bundle-reject',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'status' => 'pending',
        ]);

        $reason = 'Pricing too low';
        $response = $this->postForSite("/api/bundles/{$bundle->id}/reject", [
            'reason' => $reason,
        ]);
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
            'slug' => 'pending-bundle-no-reason',
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

    // =========================================================================
    // POST /api/bundles/bulk/publish
    // =========================================================================

    public function testBulkPublishPublishesPendingBundles(): void
    {
        $bundle1 = ProductOfferBundle::create([
            'name' => 'Pending Bundle 1',
            'slug' => 'pending-bundle-1',
            'bundle_price' => 100.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'status' => 'pending',
            'total_price' => 100
        ]);

        $bundle2 = ProductOfferBundle::create([
            'name' => 'Pending Bundle 2',
            'slug' => 'pending-bundle-2',
            'bundle_price' => 200.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'status' => 'pending',
            'total_price' => 100
        ]);

        $response = $this->postForSite('/api/bundles/bulk/publish', [
            'ids' => [$bundle1->id, $bundle2->id],
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['published']);
        $this->assertEmpty($data['failed']);

        $this->assertEquals('published', ProductOfferBundle::find($bundle1->id)->status);
        $this->assertEquals('published', ProductOfferBundle::find($bundle2->id)->status);
    }

    public function testBulkPublishReportsNonPendingBundlesAsFailed(): void
    {
        $pending = ProductOfferBundle::create([
            'name' => 'Pending',
            'slug' => 'pending-bulk',
            'bundle_price' => 100.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'status' => 'pending',
            'total_price' => 100
        ]);

        $published = ProductOfferBundle::create([
            'name' => 'Already Published',
            'slug' => 'already-published-bulk',
            'bundle_price' => 100.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'status' => 'published',
            'total_price' => 100
        ]);

        $response = $this->postForSite('/api/bundles/bulk/publish', [
            'ids' => [$pending->id, $published->id],
        ]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $data['published']);
        $this->assertCount(1, $data['failed']);
        $this->assertEquals($published->id, $data['failed'][0]['id']);
    }

    public function testBulkPublishBundlesReturns422WhenIdsAreMissing(): void
    {
        $response = $this->postForSite('/api/bundles/bulk/publish', []);
        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testBulkPublishBundlesReturns422WhenIdsIsEmpty(): void
    {
        $response = $this->postForSite('/api/bundles/bulk/publish', ['ids' => []]);
        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testBulkPublishBundlesHandlesNonExistentIds(): void
    {
        $response = $this->postForSite('/api/bundles/bulk/publish', ['ids' => [99999]]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEmpty($data['published']);
        $this->assertCount(1, $data['failed']);
        $this->assertStringContainsString('not found', $data['failed'][0]['reason']);
    }

    // =========================================================================
    // DELETE /api/bundles/bulk
    // =========================================================================

    public function testBulkDeleteDeletesBundles(): void
    {
        $bundle1 = ProductOfferBundle::create([
            'name' => 'Bundle To Delete 1',
            'slug' => 'bundle-delete-1',
            'bundle_price' => 100.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'total_price' => 100
        ]);

        $bundle2 = ProductOfferBundle::create([
            'name' => 'Bundle To Delete 2',
            'slug' => 'bundle-delete-2',
            'bundle_price' => 200.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'total_price' => 100
        ]);

        $response = $this->postForSite('/api/bundles/bulk/delete', ['ids' => [$bundle1->id, $bundle2->id]]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($data['success']);
        $this->assertCount(2, $data['deleted']);
        $this->assertEmpty($data['failed']);

        $this->assertDatabaseMissing('product_offer_bundles', ['id' => $bundle1->id]);
        $this->assertDatabaseMissing('product_offer_bundles', ['id' => $bundle2->id]);
    }

    public function testBulkDeleteHandlesNonExistentIds(): void
    {
        $response = $this->postForSite('/api/bundles/bulk/delete', ['ids' => [99999]]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEmpty($data['deleted']);
        $this->assertCount(1, $data['failed']);
        $this->assertEquals(99999, $data['failed'][0]['id']);
    }

    public function testBulkDeleteBundlesReturns422WhenIdsIsEmpty(): void
    {
        $response = $this->postForSite('/api/bundles/bulk/delete', ['ids' => []]);
        $this->assertEquals(422, $response->getStatusCode());
    }

    public function testBulkDeletePartialSuccessIsReturned(): void
    {
        $bundle = ProductOfferBundle::create([
            'name' => 'Partial Bundle',
            'slug' => 'partial-bundle',
            'bundle_price' => 100.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'total_price' => 100
        ]);

        $response = $this->postForSite('/api/bundles/bulk/delete', ['ids' => [$bundle->id, 99999]]);
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertCount(1, $data['deleted']);
        $this->assertCount(1, $data['failed']);
        $this->assertEquals(2, $data['total']);
    }
}