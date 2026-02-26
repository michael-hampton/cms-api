<?php

namespace App\Tests\Unit\Services\Shopping;

use App\Services\Shopping\CartSubscriptionUniquenessRule;
use PHPUnit\Framework\TestCase;

class CartSubscriptionUniquenessRuleTest extends TestCase
{
    private CartSubscriptionUniquenessRule $rule;

    public function test_removes_duplicate_plan_in_cart(): void
    {
        $cartItems = [
            ['subscription_plan_id' => 10, 'product_name' => 'Plan A'],
            ['subscription_plan_id' => 10, 'product_name' => 'Plan A'],
        ];

        $result = $this->rule->filterInvalidItems($cartItems);

        $this->assertCount(1, $result->valid);
        $this->assertCount(1, $result->removed);
        $this->assertEquals(10, $result->valid[0]['subscription_plan_id']);
    }

    public function test_keeps_unique_plans(): void
    {
        $cartItems = [
            ['subscription_plan_id' => 10],
            ['subscription_plan_id' => 20],
        ];

        $result = $this->rule->filterInvalidItems($cartItems);

        $this->assertCount(2, $result->valid);
        $this->assertEmpty($result->removed);
    }

    public function test_non_subscription_items_always_pass_through(): void
    {
        $cartItems = [
            ['product_id' => 1],
            ['product_id' => 1], // duplicate physical product — not our concern
        ];

        $result = $this->rule->filterInvalidItems($cartItems);

        $this->assertCount(2, $result->valid);
        $this->assertEmpty($result->removed);
    }

    public function test_mixed_cart_removes_only_duplicate_subscription(): void
    {
        $cartItems = [
            ['subscription_plan_id' => 10],
            ['product_id' => 5],
            ['subscription_plan_id' => 10], // duplicate
        ];

        $result = $this->rule->filterInvalidItems($cartItems);

        $this->assertCount(2, $result->valid);
        $this->assertCount(1, $result->removed);
    }

    public function test_handles_empty_cart(): void
    {
        $result = $this->rule->filterInvalidItems([]);

        $this->assertEmpty($result->valid);
        $this->assertEmpty($result->removed);
    }

    public function test_valid_items_are_reindexed(): void
    {
        $cartItems = [
            ['subscription_plan_id' => 10],
            ['subscription_plan_id' => 10],
        ];

        $result = $this->rule->filterInvalidItems($cartItems);

        $this->assertArrayHasKey(0, $result->valid);
        $this->assertArrayNotHasKey(1, $result->valid);
    }

    public function test_has_removed_items_returns_true_when_items_removed(): void
    {
        $cartItems = [
            ['subscription_plan_id' => 10],
            ['subscription_plan_id' => 10],
        ];

        $result = $this->rule->filterInvalidItems($cartItems);

        $this->assertTrue($result->hasRemovedItems());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new CartSubscriptionUniquenessRule();
    }
}