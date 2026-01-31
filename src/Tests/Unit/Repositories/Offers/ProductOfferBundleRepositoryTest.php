<?php

namespace App\Tests\Unit\Repositories\Offers;

use App\Models\ProductOfferBundle;
use App\Models\ProductOfferBundleItem;
use App\Repositories\Offers\ProductOfferBundleRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class ProductOfferBundleRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private ProductOfferBundleRepository $repository;

    public function testFindReturnsBundleWithRelations(): void
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

        $found = $this->repository->find($bundle->id);

        $this->assertNotNull($found);
        $this->assertEquals($bundle->id, $found->id);
        $this->assertNotNull($found->items);
        $this->assertCount(2, $found->items);
    }

    public function testGetActiveBundles(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();
        $offer1 = $this->createProductOffer($product1->id);
        $offer2 = $this->createProductOffer($product2->id);

        // Active and published bundle
        $activeBundle = ProductOfferBundle::create([
            'name' => 'Active Bundle',
            'slug' => 'active-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'discount_percentage' => 25,
            'start_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'status' => 'published',
        ]);

        ProductOfferBundleItem::create([
            'bundle_id' => $activeBundle->id,
            'product_offer_id' => $offer1->id,
            'quantity' => 1,
        ]);

        // Inactive bundle
        ProductOfferBundle::create([
            'name' => 'Inactive Bundle',
            'slug' => 'inactive-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => false,
            'status' => 'published',
        ]);

        // Pending bundle
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

        $bundles = $this->repository->getActiveBundles();

        $this->assertCount(1, $bundles);
        $this->assertEquals($activeBundle->id, $bundles->first()->id);
    }

    public function testCreateBundle(): void
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
            'discount_percentage' => 25,
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

        $bundle = $this->repository->create($data);

        $this->assertNotNull($bundle);
        $this->assertEquals('New Bundle', $bundle->name);
        $this->assertCount(2, $bundle->items);
        $this->assertEquals(2, $bundle->items->last()->quantity);
    }

    public function testUpdateBundle(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();
        $offer1 = $this->createProductOffer($product1->id);
        $offer2 = $this->createProductOffer($product2->id);

        $bundle = ProductOfferBundle::create([
            'name' => 'Original Bundle',
            'slug' => 'original-bundle',
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

        $updateData = [
            'name' => 'Updated Bundle',
            'bundle_price' => 140.00,
            'items' => [
                [
                    'product_offer_id' => $offer1->id,
                    'quantity' => 2,
                ],
                [
                    'product_offer_id' => $offer2->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $updated = $this->repository->update($bundle->id, $updateData);

        $this->assertNotNull($updated);
        $this->assertEquals('Updated Bundle', $updated->name);
        $this->assertEquals(140.00, $updated->bundle_price);
        $this->assertCount(2, $updated->items);
    }

    public function testUpdateReturnsNullForNonexistentBundle(): void
    {
        $updated = $this->repository->update(9999, ['name' => 'Test']);

        $this->assertNull($updated);
    }

    public function testDeleteBundle(): void
    {
        $bundle = ProductOfferBundle::create([
            'name' => 'Delete Me',
            'slug' => 'delete-me',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
        ]);

        $deleted = $this->repository->delete($bundle->id);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('product_offer_bundles', ['id' => $bundle->id]);
    }

    public function testDeleteReturnsFalseForNonexistentBundle(): void
    {
        $deleted = $this->repository->delete(9999);

        $this->assertFalse($deleted);
    }

    public function testDeleteCascadesItems(): void
    {
        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id);

        $bundle = ProductOfferBundle::create([
            'name' => 'Bundle',
            'slug' => 'bundle',
            'total_price' => 100.00,
            'bundle_price' => 80.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
        ]);

        $item = ProductOfferBundleItem::create([
            'bundle_id' => $bundle->id,
            'product_offer_id' => $offer->id,
            'quantity' => 1,
        ]);

        $this->repository->delete($bundle->id);

        $this->assertDatabaseMissing('product_offer_bundle_items', ['id' => $item->id]);
    }

    public function testPublish(): void
    {
        $user = $this->createUser();
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

        $result = $this->repository->publish($bundle->id, $user->id);

        $this->assertInstanceOf(ProductOfferBundle::class, $result);
        $updated = $bundle->fresh();
        $this->assertEquals('published', $updated->status);
        $this->assertEquals($user->id, $updated->published_by);
        $this->assertNotNull($updated->published_at);
    }

    public function testPublishReturnsNullForNonPendingBundle(): void
    {
        $user = $this->createUser();
        $bundle = ProductOfferBundle::create([
            'name' => 'Published Bundle',
            'slug' => 'published-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'status' => 'published',
        ]);

        $result = $this->repository->publish($bundle->id, $user->id);

        $this->assertNull($result);
    }

    public function testReject(): void
    {
        $user = $this->createUser();
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
        $result = $this->repository->reject($bundle->id, $user->id, $reason);

        $this->assertInstanceOf(ProductOfferBundle::class, $result);
        $updated = $bundle->fresh();
        $this->assertEquals('rejected', $updated->status);
        $this->assertEquals($user->id, $updated->rejected_by);
        $this->assertEquals($reason, $updated->rejection_reason);
        $this->assertNotNull($updated->rejected_at);
    }

    public function testRejectReturnsNullForNonPendingBundle(): void
    {
        $user = $this->createUser();
        $bundle = ProductOfferBundle::create([
            'name' => 'Published Bundle',
            'slug' => 'published-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'status' => 'published',
        ]);

        $result = $this->repository->reject($bundle->id, $user->id, 'Test');

        $this->assertNull($result);
    }

    public function testGetByStatus(): void
    {
        $publishedBundle = ProductOfferBundle::create([
            'name' => 'Published Bundle',
            'slug' => 'published-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'status' => 'published',
        ]);

        $pendingBundle = ProductOfferBundle::create([
            'name' => 'Pending Bundle',
            'slug' => 'pending-bundle',
            'total_price' => 200.00,
            'bundle_price' => 150.00,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'is_active' => true,
            'status' => 'pending',
        ]);

        $published = $this->repository->getByStatus('published');
        $pending = $this->repository->getByStatus('pending');

        $this->assertCount(1, $published);
        $this->assertCount(1, $pending);
        $this->assertEquals($publishedBundle->id, $published->first()->id);
        $this->assertEquals($pendingBundle->id, $pending->first()->id);
    }

    public function testSearchBundles(): void
    {
        $product1 = $this->createProduct(['name' => 'Gaming Mouse']);
        $product2 = $this->createProduct(['name' => 'Gaming Keyboard']);
        $offer1 = $this->createProductOffer($product1->id);
        $offer2 = $this->createProductOffer($product2->id);

        $bundle = ProductOfferBundle::create([
            'name' => 'Gaming Bundle',
            'slug' => 'gaming-bundle',
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

        $filters = [
            'search' => 'Gaming',
            'status' => 'published',
            'is_active' => true,
        ];

        $results = $this->repository->searchBundles($filters);

        $this->assertCount(1, $results['data']);
        $this->assertEquals(1, $results['total']);
        $this->assertEquals($bundle->id, $results['data']->first()->id);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ProductOfferBundleRepository();
    }
}