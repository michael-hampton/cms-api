<?php

namespace App\Tests\Unit\Services\Vouchers\Providers;

use App\Models\Member;
use App\Models\MemberReward;
use App\Models\RewardDefinition;
use App\Repositories\Rewards\RewardsRepository;
use App\Services\Vouchers\DiscountContext;
use App\Services\Vouchers\Providers\RewardDiscountProvider;
use Mockery;
use PHPUnit\Framework\TestCase;

class RewardDiscountProviderTest extends TestCase
{
    public function test_priority_is_correct(): void
    {
        $provider = new RewardDiscountProvider();

        $this->assertEquals(40, $provider->priority());
    }

    public function test_supports_returns_false_when_no_reward_id(): void
    {
        $provider = new RewardDiscountProvider();

        $context = new DiscountContext(
            items: [],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: Mockery::mock(Member::class)
        );

        $this->assertFalse($provider->supports($context));
    }

    public function test_supports_returns_false_when_no_member(): void
    {
        $repository = Mockery::mock(RewardsRepository::class);
        $provider = new RewardDiscountProvider(123, $repository);

        $context = new DiscountContext(
            items: [],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: null
        );

        $this->assertFalse($provider->supports($context));
    }

    public function test_supports_returns_true_with_reward_and_member(): void
    {
        $repository = Mockery::mock(RewardsRepository::class);
        $provider = new RewardDiscountProvider(123, $repository);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $context = new DiscountContext(
            items: [],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: $member
        );

        $this->assertTrue($provider->supports($context));
    }

    public function test_apply_returns_null_when_reward_not_found(): void
    {
        $repository = Mockery::mock(RewardsRepository::class);
        $repository->shouldReceive('find')->with(123)->andReturn(null);

        $provider = new RewardDiscountProvider(123, $repository);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $context = new DiscountContext(
            items: [],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: $member
        );

        $result = $provider->apply($context);

        $this->assertNull($result);
    }

    public function test_apply_returns_null_when_reward_not_pending(): void
    {
        $reward = Mockery::mock(Memberreward::class)->makePartial();
        $reward->shouldReceive('isPending')->andReturn(false);

        $repository = Mockery::mock(RewardsRepository::class);
        $repository->shouldReceive('find')->with(123)->andReturn($reward);

        $provider = new RewardDiscountProvider(123, $repository);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $context = new DiscountContext(
            items: [],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: $member
        );

        $result = $provider->apply($context);

        $this->assertNull($result);
    }

    public function test_apply_returns_null_when_reward_expired(): void
    {
        $reward = Mockery::mock(Memberreward::class)->makePartial();
        $reward->shouldReceive('isPending')->andReturn(true);
        $reward->shouldReceive('isExpired')->andReturn(true);

        $repository = Mockery::mock(RewardsRepository::class);
        $repository->shouldReceive('find')->with(123)->andReturn($reward);

        $provider = new RewardDiscountProvider(123, $repository);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $context = new DiscountContext(
            items: [],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: $member
        );

        $result = $provider->apply($context);

        $this->assertNull($result);
    }

    public function test_apply_returns_null_when_member_mismatch(): void
    {
        $reward = Mockery::mock(Memberreward::class)->makePartial();
        $reward->shouldReceive('isPending')->andReturn(true);
        $reward->shouldReceive('isExpired')->andReturn(false);
        $reward->member_id = 999;

        $repository = Mockery::mock(RewardsRepository::class);
        $repository->shouldReceive('find')->with(123)->andReturn($reward);

        $provider = new RewardDiscountProvider(123, $repository);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $context = new DiscountContext(
            items: [],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: $member
        );

        $result = $provider->apply($context);

        $this->assertNull($result);
    }

    public function test_apply_calculates_percentage_discount(): void
    {
        $definition = Mockery::mock(RewardDefinition::class)->makePartial();
        $definition->id = 456;
        $definition->reward_type = 'percentage_discount';
        $definition->reward_config = ['percentage' => 15];

        $reward = Mockery::mock(Memberreward::class)->makePartial();
        $reward->shouldReceive('isPending')->andReturn(true);
        $reward->shouldReceive('isExpired')->andReturn(false);
        $reward->member_id = 1;
        $reward->rewardDefinition = $definition;

        $repository = Mockery::mock(RewardsRepository::class);
        $repository->shouldReceive('find')->with(123)->andReturn($reward);

        $provider = new RewardDiscountProvider(123, $repository);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $context = new DiscountContext(
            items: [['id' => 1, 'price' => 100]],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: $member
        );

        $result = $provider->apply($context);

        $this->assertNotNull($result);
        $this->assertEquals(1500, $result->discountAmountCents); // 15% of 10000
        $this->assertEquals('reward', $result->type);
        $this->assertEquals('platform', $result->fundingSource);
    }

    public function test_apply_calculates_fixed_discount(): void
    {
        $definition = Mockery::mock(Rewarddefinition::class)->makePartial();
        $definition->id = 456;
        $definition->reward_type = 'fixed_discount';
        $definition->reward_config = ['amount' => 25];

        $reward = Mockery::mock(Memberreward::class)->makePartial();
        $reward->shouldReceive('isPending')->andReturn(true);
        $reward->shouldReceive('isExpired')->andReturn(false);
        $reward->member_id = 1;
        $reward->rewardDefinition = $definition;

        $repository = Mockery::mock(RewardsRepository::class);
        $repository->shouldReceive('find')->with(123)->andReturn($reward);

        $provider = new RewardDiscountProvider(123, $repository);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $context = new DiscountContext(
            items: [['id' => 1, 'price' => 100]],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: $member
        );

        $result = $provider->apply($context);

        $this->assertNotNull($result);
        $this->assertEquals(2500, $result->discountAmountCents);
    }

    public function test_apply_caps_fixed_discount_at_subtotal(): void
    {
        $definition = Mockery::mock(Rewarddefinition::class)->makePartial();
        $definition->id = 456;
        $definition->reward_type = 'fixed_discount';
        $definition->reward_config = ['amount' => 200]; // More than subtotal

        $reward = Mockery::mock(memberreward::class)->makePartial();
        $reward->shouldReceive('isPending')->andReturn(true);
        $reward->shouldReceive('isExpired')->andReturn(false);
        $reward->member_id = 1;
        $reward->rewardDefinition = $definition;

        $repository = Mockery::mock(RewardsRepository::class);
        $repository->shouldReceive('find')->with(123)->andReturn($reward);

        $provider = new RewardDiscountProvider(123, $repository);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $context = new DiscountContext(
            items: [['id' => 1, 'price' => 100]],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: $member
        );

        $result = $provider->apply($context);

        $this->assertEquals(10000, $result->discountAmountCents);
    }

    public function test_apply_returns_null_for_subscription_when_not_applicable(): void
    {
        $definition = Mockery::mock(Rewarddefinition::class)->makePartial();
        $definition->id = 456;
        $definition->reward_type = 'percentage_discount';
        $definition->reward_config = ['percentage' => 15, 'applies_to' => 'one_time'];

        $reward = Mockery::mock(Memberreward::class)->makePartial();
        $reward->shouldReceive('isPending')->andReturn(true);
        $reward->shouldReceive('isExpired')->andReturn(false);
        $reward->member_id = 1;
        $reward->rewardDefinition = $definition;

        $repository = Mockery::mock(RewardsRepository::class);
        $repository->shouldReceive('find')->with(123)->andReturn($reward);

        $provider = new RewardDiscountProvider(123, $repository);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $context = new DiscountContext(
            items: [],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: $member,
            isSubscription: true
        );

        $result = $provider->apply($context);

        $this->assertNull($result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}