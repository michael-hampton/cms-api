<?php

namespace App\Tests\Unit\Services\Shopping;

use App\DTO\Checkout\EligibilityResult;
use App\Models\Member;
use App\Services\Shopping\CartSubscriptionUniquenessRule;
use App\Services\Shopping\CheckoutEligibilityService;
use App\Services\Shopping\SubscriptionEligibilityRule;
use Mockery;
use PHPUnit\Framework\TestCase;

class CheckoutEligibilityServiceTest extends TestCase
{
    private CartSubscriptionUniquenessRule $uniquenessRule;
    private SubscriptionEligibilityRule $subscriptionRule;
    private CheckoutEligibilityService $service;
    private Member $member;

    public function test_returns_all_items_when_no_duplicates(): void
    {
        $items = [
            ['subscription_plan_id' => 5, 'price' => 9.99],
        ];

        $this->uniquenessRule->shouldReceive('filterInvalidItems')
            ->once()
            ->with($items)
            ->andReturn(new EligibilityResult(
                valid: $items,
                removed: [],
            ));

        $this->subscriptionRule->shouldReceive('filterInvalidItems')
            ->once()
            ->with($this->member, $items)
            ->andReturn(new EligibilityResult(
                valid: $items,
                removed: [],
            ));

        $result = $this->service->validate($this->member, $items);

        $this->assertEmpty($result->removed);
        $this->assertCount(1, $result->valid);
    }

    public function test_returns_removed_items_when_duplicates_found(): void
    {
        $items = [
            ['subscription_plan_id' => 5, 'price' => 9.99],
        ];

        $removed = [
            ['subscription_plan_id' => 5, 'price' => 9.99],
        ];

        $this->uniquenessRule->shouldReceive('filterInvalidItems')
            ->once()
            ->with($items)
            ->andReturn(new EligibilityResult(
                valid: [],
                removed: $removed,
            ));

        $this->subscriptionRule->shouldReceive('filterInvalidItems')
            ->once()
            ->with($this->member, [])
            ->andReturn(new EligibilityResult(
                valid: [],
                removed: [],
            ));

        $result = $this->service->validate($this->member, $items);

        $this->assertCount(1, $result->removed);
        $this->assertEmpty($result->valid);
    }

    public function test_merges_removed_from_both_rules(): void
    {
        $duplicate = ['subscription_plan_id' => 10];
        $existing = ['subscription_plan_id' => 20];
        $valid = ['subscription_plan_id' => 30];

        $this->uniquenessRule->shouldReceive('filterInvalidItems')
            ->once()
            ->andReturn(new EligibilityResult(valid: [$existing, $valid], removed: [$duplicate]));

        $this->subscriptionRule->shouldReceive('filterInvalidItems')
            ->once()
            ->andReturn(new EligibilityResult(valid: [$valid], removed: [$existing]));

        $result = $this->service->validate($this->member, [$duplicate, $existing, $valid]);

        $this->assertCount(1, $result->valid);
        $this->assertCount(2, $result->removed);
        $this->assertTrue($result->hasRemovedItems());
    }

    public function test_returns_correct_structure(): void
    {
        $emptyResult = new EligibilityResult(valid: [], removed: []);

        $this->uniquenessRule->shouldReceive('filterInvalidItems')
            ->once()
            ->andReturn($emptyResult);

        $this->subscriptionRule->shouldReceive('filterInvalidItems')
            ->once()
            ->andReturn($emptyResult);

        $result = $this->service->validate($this->member, []);

        $this->assertInstanceOf(EligibilityResult::class, $result);
        $this->assertEmpty($result->valid);
        $this->assertEmpty($result->removed);
    }

    public function test_returns_all_items_when_no_issues(): void
    {
        $items = [['subscription_plan_id' => 5]];

        $this->uniquenessRule->shouldReceive('filterInvalidItems')
            ->once()
            ->andReturn(new EligibilityResult(valid: $items, removed: []));

        $this->subscriptionRule->shouldReceive('filterInvalidItems')
            ->once()
            ->andReturn(new EligibilityResult(valid: $items, removed: []));

        $result = $this->service->validate($this->member, $items);

        $this->assertCount(1, $result->valid);
        $this->assertEmpty($result->removed);
        $this->assertFalse($result->hasRemovedItems());
    }

    public function test_uniqueness_rule_runs_before_subscription_rule(): void
    {
        $afterUniqueness = [['subscription_plan_id' => 5]];

        $this->uniquenessRule->shouldReceive('filterInvalidItems')
            ->once()
            ->andReturn(new EligibilityResult(valid: $afterUniqueness, removed: []));

        $this->subscriptionRule->shouldReceive('filterInvalidItems')
            ->once()
            ->with($this->member, $afterUniqueness)
            ->andReturn(new EligibilityResult(valid: $afterUniqueness, removed: []));

        $this->service->validate($this->member, [['subscription_plan_id' => 5], ['subscription_plan_id' => 5]]);
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->uniquenessRule = Mockery::mock(CartSubscriptionUniquenessRule::class);
        $this->subscriptionRule = Mockery::mock(SubscriptionEligibilityRule::class);
        $this->service = new CheckoutEligibilityService(
            $this->uniquenessRule,
            $this->subscriptionRule
        );

        $this->member = Mockery::mock(Member::class)->makePartial();
        $this->member->id = 1;
    }

    public function test_gift_items_pass_through_eligibility_even_when_buyer_has_plan(): void
    {
        $giftItem = [
            'subscription_plan_id' => 10,
            'is_gift' => true,
            'gift_email' => 'recipient@example.com',
        ];

        // Uniqueness rule passes it through — it's not a duplicate
        $this->uniquenessRule->shouldReceive('filterInvalidItems')
            ->once()
            ->with([$giftItem])
            ->andReturn(new EligibilityResult(valid: [$giftItem], removed: []));

        // Eligibility rule also passes it through — gift items skip buyer check
        $this->subscriptionRule->shouldReceive('filterInvalidItems')
            ->once()
            ->with($this->member, [$giftItem])
            ->andReturn(new EligibilityResult(valid: [$giftItem], removed: []));

        $result = $this->service->validate($this->member, [$giftItem]);

        $this->assertCount(1, $result->valid);
        $this->assertEmpty($result->removed);
        $this->assertEquals('recipient@example.com', $result->valid[0]['gift_email']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

}