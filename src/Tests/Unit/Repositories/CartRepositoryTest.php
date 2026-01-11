<?php

namespace App\Tests\Unit\Repositories;

use App\Models\CartItem;
use App\Repositories\Shop\CartRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class CartRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private CartRepository $repository;
    private string $sessionId;

    public function test_find_by_session_returns_cart_items(): void
    {
        $product = $this->createProduct();

        CartItem::create([
            'session_id' => $this->sessionId,
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 25.00,
            'site_id' => $this->siteId,
            'subtotal' => 25.00,
        ]);

        CartItem::create([
            'session_id' => $this->sessionId,
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 30.00,
            'site_id' => $this->siteId,
            'subtotal' => 30.00,
        ]);

        $items = $this->repository->findBySessionOrUser(null, $this->sessionId);

        $this->assertCount(2, $items);
    }

    public function test_find_by_user_returns_cart_items(): void
    {
        $member = $this->createMember();
        $product = $this->createProduct();

        CartItem::create([
            'session_id' => $this->sessionId,
            'user_id' => $member->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'price' => 20.00,
            'site_id' => $this->siteId,
            'subtotal' => 20.00,
        ]);

        $items = $this->repository->findBySessionOrUser($member->id, $this->sessionId);

        $this->assertCount(1, $items);
        $this->assertEquals($member->id, $items->first()->user_id);
    }

    public function test_find_by_session_does_not_return_other_session_items(): void
    {
        $product = $this->createProduct();

        CartItem::create([
            'session_id' => 'other-session',
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 10.00,
            'site_id' => $this->siteId,
            'subtotal' => 10.00,
        ]);

        $items = $this->repository->findBySessionOrUser(null, $this->sessionId);

        $this->assertCount(0, $items);
    }

    public function test_find_item_by_product_returns_correct_item(): void
    {
        $product = $this->createProduct();

        $cartItem = CartItem::create([
            'session_id' => $this->sessionId,
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 25.00,
            'site_id' => $this->siteId,
            'subtotal' => 25.00,
        ]);

        $found = $this->repository->findItemByProduct($product->id, null, $this->sessionId);

        $this->assertNotNull($found);
        $this->assertEquals($cartItem->id, $found->id);
    }

    public function test_find_item_by_product_returns_null_when_not_found(): void
    {
        $product = $this->createProduct();

        $found = $this->repository->findItemByProduct($product->id, null, $this->sessionId);

        $this->assertNull($found);
    }

    public function test_delete_by_session_removes_all_items(): void
    {
        $product = $this->createProduct();

        CartItem::create([
            'session_id' => $this->sessionId,
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 10.00,
            'site_id' => $this->siteId,
            'subtotal' => 10.00,
        ]);

        CartItem::create([
            'session_id' => $this->sessionId,
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 20.00,
            'site_id' => $this->siteId,
            'subtotal' => 20.00,
        ]);

        $result = $this->repository->deleteBySessionOrUser(null, $this->sessionId);

        $this->assertTrue($result);

        $remaining = $this->repository->findBySessionOrUser(null, $this->sessionId);
        $this->assertCount(0, $remaining);
    }

    public function test_delete_by_user_removes_all_items(): void
    {
        $member = $this->createMember();
        $product = $this->createProduct();

        CartItem::create([
            'session_id' => $this->sessionId,
            'user_id' => $member->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 10.00,
            'site_id' => $this->siteId,
            'subtotal' => 10.00,
        ]);

        $result = $this->repository->deleteBySessionOrUser($member->id, $this->sessionId);

        $this->assertTrue($result);

        $remaining = $this->repository->findBySessionOrUser($member->id, $this->sessionId);
        $this->assertCount(0, $remaining);
    }

    public function test_get_count_returns_sum_of_quantities(): void
    {
        $product = $this->createProduct();

        CartItem::create([
            'session_id' => $this->sessionId,
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 3,
            'price' => 10.00,
            'site_id' => $this->siteId,
            'subtotal' => 10.00,
        ]);

        CartItem::create([
            'session_id' => $this->sessionId,
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 5,
            'price' => 20.00,
            'site_id' => $this->siteId,
            'subtotal' => 20.00,
        ]);

        $count = $this->repository->getCountBySessionOrUser(null, $this->sessionId);

        $this->assertEquals(8, $count);
    }

    public function test_get_count_returns_zero_when_empty(): void
    {
        $count = $this->repository->getCountBySessionOrUser(null, $this->sessionId);

        $this->assertEquals(0, $count);
    }

    public function test_find_by_id_returns_correct_item(): void
    {
        $product = $this->createProduct();

        $cartItem = CartItem::create([
            'session_id' => $this->sessionId,
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 15.00,
            'site_id' => $this->siteId,
            'subtotal' => 15.00,
        ]);

        $found = $this->repository->findById($cartItem->id, null, $this->sessionId);

        $this->assertNotNull($found);
        $this->assertEquals($cartItem->id, $found->id);
    }

    public function test_find_by_id_returns_null_for_different_session(): void
    {
        $product = $this->createProduct();

        $cartItem = CartItem::create([
            'session_id' => 'other-session',
            'user_id' => null,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 15.00,
            'site_id' => $this->siteId,
            'subtotal' => 15.00,
        ]);

        $found = $this->repository->findById($cartItem->id, null, $this->sessionId);

        $this->assertNull($found);
    }

    public function test_find_by_subscription_plan_returns_correct_item(): void
    {
        $plan = $this->createSubscriptionPlan();

        $cartItem = CartItem::create([
            'session_id' => $this->sessionId,
            'user_id' => null,
            'product_id' => null,
            'subscription_plan_id' => $plan->id,
            'quantity' => 1,
            'price' => 50.00,
            'site_id' => $this->siteId,
            'subtotal' => 50.00,
        ]);

        $found = $this->repository->findBySubscriptionPlan($plan->id, null, $this->sessionId);

        $this->assertNotNull($found);
        $this->assertEquals($cartItem->id, $found->id);
        $this->assertEquals($plan->id, $found->subscription_plan_id);
    }

    public function test_find_by_subscription_plan_returns_null_when_not_found(): void
    {
        $plan = $this->createSubscriptionPlan();

        $found = $this->repository->findBySubscriptionPlan($plan->id, null, $this->sessionId);

        $this->assertNull($found);
    }

    public function test_find_by_subscription_plan_filters_by_user(): void
    {
        $member = $this->createMember();
        $plan = $this->createSubscriptionPlan();

        CartItem::create([
            'session_id' => $this->sessionId,
            'user_id' => $member->id,
            'product_id' => null,
            'subscription_plan_id' => $plan->id,
            'quantity' => 1,
            'price' => 50.00,
            'site_id' => $this->siteId,
            'subtotal' => 50.00,
        ]);

        $found = $this->repository->findBySubscriptionPlan($plan->id, $member->id, $this->sessionId);

        $this->assertNotNull($found);
        $this->assertEquals($member->id, $found->user_id);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new CartRepository();
        $this->sessionId = 'test-session-' . uniqid();
    }
}