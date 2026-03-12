<?php

namespace App\Tests\Unit\Repositories\Offers;

use App\Enums\Offers\OfferStatus;
use App\Models\ProductOffer;
use App\Repositories\Offers\ProductOfferRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class ProductOfferRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private ProductOfferRepository $repository;

    public function testFindReturnsOfferWithRelations(): void
    {
        $product = $this->createProduct();
        $merchant = $this->createMerchant();

        $offer = $this->createProductOffer($product->id, [], $merchant->id);

        $found = $this->repository->find($offer->id);

        $this->assertNotNull($found);
        $this->assertEquals($offer->id, $found->id);
        $this->assertNotNull($found->product);
        $this->assertNotNull($found->merchant);
    }

    public function testGetActiveOffersForProduct(): void
    {
        $product = $this->createProduct();

        // Active offer
        $activeOffer = $this->createProductOffer($product->id);

        $this->createProductOffer($product->id, [
            'start_date' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'end_date' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        $this->createProductOffer($product->id, [
            'start_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'end_date' => date('Y-m-d H:i:s', strtotime('+2 days')),
        ]);

        $offers = $this->repository->getActiveOffersForProduct($product->id);

        $this->assertCount(1, $offers);
        $this->assertContains($activeOffer->id, array_column($offers->toArray(), 'id'));
    }

    public function testGetActiveOffersForCategory(): void
    {
        $category = $this->createCategory();
        $product1 = $this->createProduct(['category_id' => $category->id]);
        $product2 = $this->createProduct(['category_id' => $category->id]);

        $this->createProductOffer($product1->id);
        $this->createProductOffer($product2->id);

        $offers = $this->repository->getActiveOffersForCategory($category->id);

        $this->assertCount(2, $offers);
    }

    public function testCreateOffer(): void
    {
        $product = $this->createProduct();
        $merchant = $this->createMerchant();

        $data = [
            'product_id' => $product->id,
            'merchant_id' => $merchant->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+1 day')),
            'is_active' => true,
            'original_price' => 0,
            'site_id' => $this->siteId
        ];

        $offer = $this->repository->create($data);

        $this->assertNotNull($offer);
        $this->assertEquals($product->id, $offer->product_id);
        $this->assertEquals(79.99, $offer->sale_price);
    }

    public function testCreateDeactivatesOtherOffers(): void
    {
        $product = $this->createProduct();

        $existingOffer = $this->createProductOffer($product->id);

        $data = [
            'product_id' => $product->id,
            'sale_price' => 79.99,
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+2 days')),
            'is_active' => true,
            'original_price' => 0,
            'site_id' => $this->siteId
        ];

        $this->repository->create($data);

        $existingOffer = $existingOffer->fresh();
        $this->assertFalse($existingOffer->is_active);
    }

    public function testUpdateOffer(): void
    {
        $product = $this->createProduct();

        $offer = $this->createProductOffer($product->id);

        $updated = $this->repository->update($offer->id, [
            'sale_price' => 69.99,
        ]);

        $this->assertNotNull($updated);
        $this->assertEquals(69.99, $updated->sale_price);
    }

    public function testUpdateReturnsNullForNonexistentOffer(): void
    {
        $updated = $this->repository->update(9999, ['sale_price' => 69.99]);

        $this->assertNull($updated);
    }

    public function testDeleteOffer(): void
    {
        $product = $this->createProduct();

        $offer = $this->createProductOffer($product->id);

        $deleted = $this->repository->delete($offer->id);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('product_offers', ['id' => $offer->id]);
    }

    public function testDeleteReturnsFalseForNonexistentOffer(): void
    {
        $deleted = $this->repository->delete(9999);

        $this->assertFalse($deleted);
    }

    public function testDeactivateOtherOffers(): void
    {
        $product = $this->createProduct();

        $offer1 = $this->createProductOffer($product->id);
        $offer2 = $this->createProductOffer($product->id);

        $this->repository->deactivateOtherOffers($product->id, $offer1->id);

        $offer1 = $offer1->fresh();
        $offer2 = $offer2->fresh();

        $this->assertTrue($offer1->is_active);
        $this->assertFalse($offer2->is_active);
    }

    public function testHasActiveOffer(): void
    {
        $product = $this->createProduct();

        $this->createProductOffer($product->id);

        $this->assertTrue($this->repository->hasActiveOffer($product->id));
    }

    public function testHasActiveOfferReturnsFalse(): void
    {
        $product = $this->createProduct();

        $this->assertFalse($this->repository->hasActiveOffer($product->id));
    }

    public function testGetByStatus(): void
    {
        $product = $this->createProduct();

        $publishedOffer = $this->createProductOffer($product->id, ['status' => OfferStatus::PUBLISHED->value]);
        $pendingOffer = $this->createProductOffer($product->id, ['status' => OfferStatus::PENDING->value]);

        $published = $this->repository->getByStatus('published');
        $pending = $this->repository->getByStatus('pending');

        $this->assertCount(1, $published);
        $this->assertCount(1, $pending);
        $this->assertEquals($publishedOffer->id, $published->first()->id);
        $this->assertEquals($pendingOffer->id, $pending->first()->id);
    }

    public function testPublish(): void
    {
        $user = $this->createUser();
        $product = $this->createProduct();

        $offer = $this->createProductOffer($product->id);

        $result = $this->repository->publish($offer->id, $user->id);

        $this->assertInstanceOf(ProductOffer::class, $result);
        $updated = $offer->fresh();
        $this->assertEquals('published', $updated->status);
        $this->assertEquals($user->id, $updated->published_by);
        $this->assertNotNull($updated->published_at);
    }

    public function testReject(): void
    {
        $user = $this->createUser();
        $product = $this->createProduct();

        $offer = $this->createProductOffer($product->id);

        $reason = 'Price too low';
        $result = $this->repository->reject($offer->id, $user->id, $reason);

        $this->assertInstanceOf(ProductOffer::class, $result);
        $updated = $offer->fresh();
        $this->assertEquals('rejected', $updated->status);
        $this->assertEquals($user->id, $updated->rejected_by);
        $this->assertEquals($reason, $updated->rejection_reason);
        $this->assertNotNull($updated->rejected_at);
    }

    public function testTrackClick(): void
    {
        $product = $this->createProduct();
        $member = $this->createMember();

        $offer = $this->createProductOffer($product->id);

        $this->repository->trackClick(
            $offer->id,
            $member->id,
            'click',
            '127.0.0.1',
            'Mozilla/5.0'
        );

        $this->assertDatabaseHas('offer_clicks', [
            'offer_id' => $offer->id,
            'member_id' => $member->id,
            'action' => 'click',
            'ip_address' => '127.0.0.1'
        ]);
    }

    public function testGetClickStatistics(): void
    {
        $product = $this->createProduct();
        $member1 = $this->createMember();
        $member2 = $this->createMember();

        $offer = $this->createProductOffer($product->id);

        // Create clicks
        $this->repository->trackClick($offer->id, $member1->id, 'view');
        $this->repository->trackClick($offer->id, $member1->id, 'click');
        $this->repository->trackClick($offer->id, $member2->id, 'view');

        $stats = $this->repository->getClickStatistics([$offer->id]);

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(2, $stats['unique']);
        $this->assertEquals(3, $stats['by_offer'][$offer->id]);
        $this->assertEquals(2, $stats['by_action']['view']);
        $this->assertEquals(1, $stats['by_action']['click']);
    }

    public function testGetClickStatisticsWithEmptyArray(): void
    {
        $stats = $this->repository->getClickStatistics([]);

        $this->assertEquals(0, $stats['total']);
        $this->assertEquals(0, $stats['unique']);
        $this->assertEmpty($stats['by_offer']);
    }

    public function testSearchOffersWithFilters(): void
    {
        $product = $this->createProduct(['name' => 'Test Product']);
        $merchant = $this->createMerchant();

        $offer = $this->createProductOffer($product->id, [
            'sale_price' => 79.99,
            'original_price' => 99.99,
            'merchant_id' => $merchant->id,
            'status' => OfferStatus::PUBLISHED->value
        ]);

        $filters = [
            'merchant_id' => $merchant->id,
            'status' => 'published',
            'min_discount' => 15,
        ];

        $results = $this->repository->searchOffersWithFilters($filters);

        $this->assertCount(1, $results['data']);
        $this->assertEquals(1, $results['total']);
        $this->assertEquals($offer->id, $results['data']->first()->id);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ProductOfferRepository();
    }
}