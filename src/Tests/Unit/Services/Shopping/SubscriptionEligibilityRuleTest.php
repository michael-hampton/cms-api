<?php

namespace App\Tests\Unit\Services\Shopping;

use App\Models\Member;
use App\Repositories\Subscriptions\SubscriptionRepository;
use App\Services\Shopping\SubscriptionEligibilityRule;
use Mockery;
use PHPUnit\Framework\TestCase;

class SubscriptionEligibilityRuleTest extends TestCase
{
    private SubscriptionRepository $repository;
    private SubscriptionEligibilityRule $rule;
    private Member $member;

    public function test_removes_duplicate_subscription_item(): void
    {
        $cartItems = [
            ['subscription_plan_id' => 10, 'product_name' => 'Plan A'],
        ];

        $this->repository->shouldReceive('getActivePlanIds')
            ->once()
            ->with(1, [10])
            ->andReturn([10]);

        $result = $this->rule->filterInvalidItems($this->member, $cartItems);

        $this->assertCount(1, $result->removed);
        $this->assertEquals(10, $result->removed[0]['subscription_plan_id']);
        $this->assertEmpty($result->valid);
    }

    public function test_does_not_remove_valid_subscription(): void
    {
        $cartItems = [
            ['subscription_plan_id' => 10, 'product_name' => 'Plan A'],
        ];

        $this->repository->shouldReceive('getActivePlanIds')
            ->once()
            ->with(1, [10])
            ->andReturn([]);

        $result = $this->rule->filterInvalidItems($this->member, $cartItems);

        $this->assertEmpty($result->removed);
        $this->assertCount(1, $result->valid);
    }

    public function test_ignores_non_subscription_items(): void
    {
        $cartItems = [
            ['product_id' => 5, 'product_name' => 'A Book'],
        ];

        $this->repository->shouldReceive('getActivePlanIds')->never();

        $result = $this->rule->filterInvalidItems($this->member, $cartItems);

        $this->assertEmpty($result->removed);
        $this->assertCount(1, $result->valid);
    }

    public function test_removes_only_duplicate_from_mixed_cart(): void
    {
        $cartItems = [
            ['subscription_plan_id' => 10],
            ['product_id' => 5],
            ['subscription_plan_id' => 20],
        ];

        $this->repository->shouldReceive('getActivePlanIds')
            ->once()
            ->with(1, [10, 20])
            ->andReturn([10]);

        $result = $this->rule->filterInvalidItems($this->member, $cartItems);

        $this->assertCount(1, $result->removed);
        $this->assertEquals(10, $result->removed[0]['subscription_plan_id']);

        $this->assertCount(2, $result->valid);
        $this->assertEquals(5, $result->valid[0]['product_id']);
        $this->assertEquals(20, $result->valid[1]['subscription_plan_id']);
    }

    public function test_removes_multiple_duplicate_subscriptions(): void
    {
        $cartItems = [
            ['subscription_plan_id' => 10],
            ['subscription_plan_id' => 20],
        ];

        $this->repository->shouldReceive('getActivePlanIds')
            ->once()
            ->with(1, [10, 20])
            ->andReturn([10, 20]);

        $result = $this->rule->filterInvalidItems($this->member, $cartItems);

        $this->assertCount(2, $result->removed);
        $this->assertEmpty($result->valid);
    }

    public function test_returns_empty_removed_when_cart_is_empty(): void
    {
        $cartItems = [];

        $this->repository->shouldReceive('getActivePlanIds')->never();

        $result = $this->rule->filterInvalidItems($this->member, $cartItems);

        $this->assertEmpty($result->removed);
        $this->assertEmpty($result->valid);
    }

    public function test_valid_items_are_reindexed(): void
    {
        $cartItems = [
            ['subscription_plan_id' => 10],
            ['product_id' => 5],
        ];

        $this->repository->shouldReceive('getActivePlanIds')
            ->once()
            ->with(1, [10])
            ->andReturn([10]);

        $result = $this->rule->filterInvalidItems($this->member, $cartItems);

        $this->assertArrayHasKey(0, $result->valid);
        $this->assertArrayNotHasKey(1, $result->valid);
    }

    public function test_removes_subscription_user_already_has(): void
    {
        $cartItems = [
            ['subscription_plan_id' => 10, 'product_name' => 'Plan A', 'price' => 9.99],
        ];

        $this->repository->shouldReceive('getActivePlanIds')
            ->once()
            ->with(1, [10])
            ->andReturn([10]);

        $result = $this->rule->filterInvalidItems($this->member, $cartItems);

        $this->assertCount(1, $result->removed);
        $this->assertEquals(10, $result->removed[0]['subscription_plan_id']);
        $this->assertEmpty($result->valid);
    }

    public function test_keeps_subscription_user_does_not_have(): void
    {
        $cartItems = [
            ['subscription_plan_id' => 10, 'product_name' => 'Plan A', 'price' => 9.99],
        ];

        $this->repository->shouldReceive('getActivePlanIds')
            ->once()
            ->with(1, [10])
            ->andReturn([]);

        $result = $this->rule->filterInvalidItems($this->member, $cartItems);

        $this->assertEmpty($result->removed);
        $this->assertCount(1, $result->valid);
    }

    public function test_removes_only_duplicate_in_mixed_cart(): void
    {
        $cartItems = [
            ['subscription_plan_id' => 10, 'product_name' => 'Plan A', 'price' => 9.99],
            ['product_id' => 5, 'product_name' => 'A Book', 'price' => 14.99],
            ['subscription_plan_id' => 20, 'product_name' => 'Plan B', 'price' => 19.99],
        ];

        $this->repository->shouldReceive('getActivePlanIds')
            ->once()
            ->with(1, [10, 20])
            ->andReturn([10]);

        $result = $this->rule->filterInvalidItems($this->member, $cartItems);

        $this->assertCount(1, $result->removed);
        $this->assertEquals(10, $result->removed[0]['subscription_plan_id']);
        $this->assertCount(2, $result->valid);
        $this->assertEquals(5, $result->valid[0]['product_id']);
        $this->assertEquals(20, $result->valid[1]['subscription_plan_id']);
    }

    public function test_removes_multiple_active_subscriptions(): void
    {
        $cartItems = [
            ['subscription_plan_id' => 10, 'product_name' => 'Plan A'],
            ['subscription_plan_id' => 20, 'product_name' => 'Plan B'],
        ];

        $this->repository->shouldReceive('getActivePlanIds')
            ->once()
            ->with(1, [10, 20])
            ->andReturn([10, 20]);

        $result = $this->rule->filterInvalidItems($this->member, $cartItems);

        $this->assertCount(2, $result->removed);
        $this->assertEmpty($result->valid);
    }

    public function test_returns_empty_removed_for_empty_cart(): void
    {
        $this->repository->shouldReceive('getActivePlanIds')->never();

        $result = $this->rule->filterInvalidItems($this->member, []);

        $this->assertEmpty($result->removed);
        $this->assertEmpty($result->valid);
    }

    public function test_makes_single_bulk_query_for_multiple_subscription_items(): void
    {
        $cartItems = [
            ['subscription_plan_id' => 10],
            ['subscription_plan_id' => 20],
            ['subscription_plan_id' => 30],
        ];

        // Assert ONE call with all plan IDs, not three separate calls
        $this->repository->shouldReceive('getActivePlanIds')
            ->once()
            ->with(1, [10, 20, 30])
            ->andReturn([]);

        $this->rule->filterInvalidItems($this->member, $cartItems);
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(SubscriptionRepository::class);
        $this->rule = new SubscriptionEligibilityRule($this->repository);

        $this->member = Mockery::mock(Member::class)->makePartial();
        $this->member->id = 1;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}