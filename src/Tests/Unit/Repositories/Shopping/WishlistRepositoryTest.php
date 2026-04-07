<?php

namespace App\Tests\Unit\Repositories\Shopping;

use App\Models\ProductOfferBundle;
use App\Models\Wishlist;
use App\Repositories\Shopping\WishlistRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class WishlistRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private WishlistRepository $repository;
    private string $sessionId;

    public function test_find_by_session_returns_wishlist_items(): void
    {
        $product = $this->createProduct();
        $product2 = $this->createProduct();

        Wishlist::create([
            'session_id' => $this->sessionId,
            'user_id' => null,
            'product_id' => $product->id,
            'site_id' => $this->siteId,
        ]);

        Wishlist::create([
            'session_id' => $this->sessionId,
            'user_id' => null,
            'product_id' => $product2->id,
            'site_id' => $this->siteId,
        ]);

        $items = $this->repository->findBySessionOrUser(null, $this->sessionId);

        $this->assertCount(2, $items);
    }

    public function test_find_by_user_returns_wishlist_items(): void
    {
        $member = $this->createMember();
        $product = $this->createProduct();

        Wishlist::create([
            'session_id' => $this->sessionId,
            'user_id' => $member->id,
            'product_id' => $product->id,
            'site_id' => $this->siteId,
        ]);

        $items = $this->repository->findBySessionOrUser($member->id, $this->sessionId);

        $this->assertCount(1, $items);
        $this->assertEquals($member->id, $items->first()->user_id);
    }

    public function test_find_by_session_does_not_return_other_session_items(): void
    {
        $product = $this->createProduct();

        Wishlist::create([
            'session_id' => 'other-session',
            'user_id' => null,
            'product_id' => $product->id,
            'site_id' => $this->siteId,
        ]);

        $items = $this->repository->findBySessionOrUser(null, $this->sessionId);

        $this->assertCount(0, $items);
    }

    public function test_exists_by_product_returns_true_when_exists(): void
    {
        $product = $this->createProduct();

        Wishlist::create([
            'session_id' => $this->sessionId,
            'user_id' => null,
            'product_id' => $product->id,
            'site_id' => $this->siteId,
        ]);

        $exists = $this->repository->existsByProduct($product->id, null, $this->sessionId);

        $this->assertTrue($exists);
    }

    public function test_exists_by_product_returns_false_when_not_exists(): void
    {
        $product = $this->createProduct();

        $exists = $this->repository->existsByProduct($product->id, null, $this->sessionId);

        $this->assertFalse($exists);
    }

    public function test_delete_by_product_removes_item(): void
    {
        $product = $this->createProduct();

        Wishlist::create([
            'session_id' => $this->sessionId,
            'user_id' => null,
            'product_id' => $product->id,
            'site_id' => $this->siteId,
        ]);

        $result = $this->repository->deleteByProduct($product->id, null, $this->sessionId);

        $this->assertTrue($result);

        $exists = $this->repository->existsByProduct($product->id, null, $this->sessionId);
        $this->assertFalse($exists);
    }

    public function test_delete_by_product_returns_false_when_not_found(): void
    {
        $product = $this->createProduct();

        $result = $this->repository->deleteByProduct($product->id, null, $this->sessionId);

        $this->assertFalse($result);
    }

    public function test_get_count_returns_correct_count(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        Wishlist::create([
            'session_id' => $this->sessionId,
            'user_id' => null,
            'product_id' => $product1->id,
            'site_id' => $this->siteId,
        ]);

        Wishlist::create([
            'session_id' => $this->sessionId,
            'user_id' => null,
            'product_id' => $product2->id,
            'site_id' => $this->siteId,
        ]);

        $count = $this->repository->getCountBySessionOrUser(null, $this->sessionId);

        $this->assertEquals(2, $count);
    }

    public function test_get_count_returns_zero_when_empty(): void
    {
        $count = $this->repository->getCountBySessionOrUser(null, $this->sessionId);

        $this->assertEquals(0, $count);
    }

    public function test_get_bundles_returns_bundles_for_session(): void
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
            'site_id' => $this->siteId
        ]);

        Wishlist::create([
            'session_id' => $this->sessionId,
            'user_id' => null,
            'product_id' => null,
            'item_type' => 'bundle',
            'item_id' => $bundle->id,
            'site_id' => $this->siteId,
        ]);

        $bundles = $this->repository->getBundles($bundle->id, null, $this->sessionId);

        $this->assertCount(1, $bundles);
        $this->assertEquals($bundle->id, $bundles->first()->item_id);
    }

    public function test_get_bundles_returns_bundles_for_user(): void
    {
        $member = $this->createMember();
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
            'site_id' => $this->siteId
        ]);

        Wishlist::create([
            'session_id' => $this->sessionId,
            'user_id' => $member->id,
            'product_id' => null,
            'item_type' => 'bundle',
            'item_id' => $bundle->id,
            'site_id' => $this->siteId,
        ]);

        $bundles = $this->repository->getBundles($bundle->id, $member->id, null);

        $this->assertCount(1, $bundles);
        $this->assertEquals($member->id, $bundles->first()->user_id);
    }

    public function test_get_bundles_returns_empty_when_none_exist(): void
    {
        $bundles = $this->repository->getBundles(999, null, $this->sessionId);

        $this->assertCount(0, $bundles);
    }

    public function test_get_offers_returns_offers_for_session(): void
    {
        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id);

        Wishlist::create([
            'session_id' => $this->sessionId,
            'user_id' => null,
            'product_id' => $product->id,
            'item_type' => 'offer',
            'item_id' => $offer->id,
            'site_id' => $this->siteId,
        ]);

        $offers = $this->repository->getOffers($offer->id, null, $this->sessionId);

        $this->assertCount(1, $offers);
        $this->assertEquals($offer->id, $offers->first()->item_id);
    }

    public function test_get_offers_returns_offers_for_user(): void
    {
        $member = $this->createMember();
        $product = $this->createProduct();
        $offer = $this->createProductOffer($product->id);

        Wishlist::create([
            'session_id' => $this->sessionId,
            'user_id' => $member->id,
            'product_id' => $product->id,
            'item_type' => 'offer',
            'item_id' => $offer->id,
            'site_id' => $this->siteId,
        ]);

        $offers = $this->repository->getOffers($offer->id, $member->id, null);

        $this->assertCount(1, $offers);
        $this->assertEquals($member->id, $offers->first()->user_id);
    }

    public function test_get_offers_returns_empty_when_none_exist(): void
    {
        $offers = $this->repository->getOffers(999, null, $this->sessionId);

        $this->assertCount(0, $offers);
    }

    public function test_get_product_ids_by_session_when_user_is_null(): void
    {
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        Wishlist::create([
            'session_id' => $this->sessionId,
            'user_id' => null,
            'product_id' => $product1->id,
            'site_id' => $this->siteId
        ]);

        Wishlist::create([
            'session_id' => $this->sessionId,
            'user_id' => null,
            'product_id' => $product2->id,
            'site_id' => $this->siteId
        ]);

        $result = $this->repository->getProductIdsBySessionOrUser(null, $this->sessionId);

        $this->assertCount(2, $result);
        $this->assertEqualsCanonicalizing(
            [$product1->id, $product2->id],
            $result
        );
    }

    public function test_get_product_ids_by_user_when_user_is_present(): void
    {
        $user = $this->createMember();

        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        // Belongs to user
        Wishlist::create([
            'session_id' => 'different-session',
            'user_id' => $user->id,
            'product_id' => $product1->id,
            'site_id' => $this->siteId
        ]);

        Wishlist::create([
            'session_id' => 'another-session',
            'user_id' => $user->id,
            'product_id' => $product2->id,
            'site_id' => $this->siteId
        ]);

        // Should be ignored (session match but wrong user)
        Wishlist::create([
            'session_id' => $this->sessionId,
            'user_id' => null,
            'product_id' => $this->createProduct()->id,
            'site_id' => $this->siteId
        ]);

        $result = $this->repository->getProductIdsBySessionOrUser($user->id, $this->sessionId);

        $this->assertCount(2, $result);
        $this->assertEqualsCanonicalizing(
            [$product1->id, $product2->id],
            $result
        );
    }

    public function test_get_product_ids_returns_empty_array_when_no_matches(): void
    {
        $result = $this->repository->getProductIdsBySessionOrUser(null, $this->sessionId);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_get_product_ids_are_cast_to_integers(): void
    {
        $product = $this->createProduct();

        Wishlist::create([
            'session_id' => $this->sessionId,
            'user_id' => null,
            'product_id' => (string)$product->id,
            'site_id' => $this->siteId
        ]);

        $result = $this->repository->getProductIdsBySessionOrUser(null, $this->sessionId);

        $this->assertIsInt($result[0]);
    }

    public function test_user_id_takes_priority_over_session(): void
    {
        $user = $this->createMember();

        $productUser = $this->createProduct();
        $productSession = $this->createProduct();

        Wishlist::create([
            'session_id' => $this->sessionId,
            'user_id' => $user->id,
            'product_id' => $productUser->id,
            'site_id' => $this->siteId
        ]);

        Wishlist::create([
            'session_id' => $this->sessionId,
            'user_id' => null,
            'product_id' => $productSession->id,
            'site_id' => $this->siteId
        ]);

        $result = $this->repository->getProductIdsBySessionOrUser($user->id, $this->sessionId);

        $this->assertCount(1, $result);
        $this->assertEquals([$productUser->id], $result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new WishlistRepository();
        $this->sessionId = 'test-session-' . uniqid();
    }
}