<?php

namespace App\Tests\Unit\Services\Vouchers\Providers;

use App\Models\Member;
use App\Models\MemberReward;
use App\Models\RewardDefinition;
use App\Repositories\Rewards\RewardsRepository;
use App\Services\Vouchers\DiscountContext\DiscountContext;
use App\Services\Vouchers\DiscountContext\RewardContext;
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

    public function test_apply_calculates_percentage_discount_with_reward_context(): void
    {
        $rewardDefinition = Mockery::mock(RewardDefinition::class)->makePartial();
        $rewardDefinition->id = 456;
        $rewardDefinition->reward_type = 'percentage_discount';
        $rewardDefinition->reward_config = ['percentage' => 15];

        $memberReward = Mockery::mock(MemberReward::class)->makePartial();
        $memberReward->shouldReceive('isPending')->andReturn(true);
        $memberReward->shouldReceive('isExpired')->andReturn(false);
        $memberReward->member_id = 1;
        $memberReward->rewardDefinition = $rewardDefinition;

        $repository = Mockery::mock(RewardsRepository::class);
        $repository->shouldReceive('find')->with(123)->andReturn($memberReward);

        $provider = new RewardDiscountProvider($repository);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $context = new DiscountContext(
            items: [
                ['id' => 1, 'price' => 100, 'quantity' => 1]
            ],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: $member,
            rewardContext: new RewardContext(
                rewardId: 123,
            )
        );

        $result = $provider->apply($context);

        $this->assertNotNull($result);
        $this->assertEquals(1500, $result->discountAmountCents); // 15% of 10000
        $this->assertEquals('reward', $result->type);
        $this->assertEquals('platform', $result->fundingSource);
    }

    public function test_supports_returns_false_when_no_member(): void
    {
        $provider = new RewardDiscountProvider(Mockery::mock(RewardsRepository::class));
        $context = new DiscountContext(items: [], baseSubtotalCents: 10000, currentSubtotalCents: 10000, currentOfferDiscountCents: 0, appliedDiscounts: [], member: null, rewardContext: new RewardContext(rewardId: 123));
        $this->assertFalse($provider->supports($context));
    }

    public function test_supports_returns_true_with_reward_and_member(): void
    {
        $repository = Mockery::mock(RewardsRepository::class);
        $repository->shouldReceive('find')->with(123)->andReturn(Mockery::mock(MemberReward::class));
        $provider = new RewardDiscountProvider($repository);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $context = new DiscountContext(items: [], baseSubtotalCents: 10000, currentSubtotalCents: 10000, currentOfferDiscountCents: 0, appliedDiscounts: [], member: $member, rewardContext: new RewardContext(rewardId: 123));
        $this->assertTrue($provider->supports($context));
    }

    public function test_apply_returns_null_when_reward_not_found(): void
    {
        $repository = Mockery::mock(RewardsRepository::class);
        $repository->shouldReceive('find')->with(123)->andReturnNull();

        $provider = new RewardDiscountProvider($repository);
        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $context = new DiscountContext(items: [], baseSubtotalCents: 10000, currentSubtotalCents: 10000, currentOfferDiscountCents: 0, appliedDiscounts: [], member: $member, rewardContext: new RewardContext(rewardId: 123));
        $this->assertNull($provider->apply($context));
    }

    public function test_apply_returns_null_when_reward_not_pending_or_expired(): void
    {
        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->shouldReceive('isPending')->andReturn(false);
        $reward->shouldReceive('isExpired')->andReturn(true);

        $repository = Mockery::mock(RewardsRepository::class);
        $repository->shouldReceive('find')->with(123)->andReturn($reward);

        $provider = new RewardDiscountProvider($repository);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $context = new DiscountContext(items: [], baseSubtotalCents: 10000, currentSubtotalCents: 10000, currentOfferDiscountCents: 0, appliedDiscounts: [], member: $member, rewardContext: new RewardContext(rewardId: 123));

        $this->assertNull($provider->apply($context));
    }

    public function test_apply_returns_null_when_reward_not_pending(): void
    {
        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->shouldReceive('isPending')->andReturn(false); // not pending
        $reward->shouldReceive('isExpired')->andReturn(false);
        $reward->member_id = 1;

        $repository = Mockery::mock(RewardsRepository::class);
        $repository->shouldReceive('find')->with(123)->andReturn($reward);

        $provider = new RewardDiscountProvider($repository);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $context = new DiscountContext(
            items: [['id' => 1, 'price' => 100]],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: $member,
            rewardContext: new RewardContext(rewardId: 123)
        );

        $this->assertNull($provider->apply($context));
    }

    public function test_apply_returns_null_when_reward_expired(): void
    {
        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->shouldReceive('isPending')->andReturn(true);
        $reward->shouldReceive('isExpired')->andReturn(true); // expired
        $reward->member_id = 1;

        $repository = Mockery::mock(RewardsRepository::class);
        $repository->shouldReceive('find')->with(123)->andReturn($reward);

        $provider = new RewardDiscountProvider($repository);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $context = new DiscountContext(
            items: [['id' => 1, 'price' => 100]],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: $member,
            rewardContext: new RewardContext(rewardId: 123)
        );

        $this->assertNull($provider->apply($context));
    }

    public function test_apply_returns_null_when_member_mismatch(): void
    {
        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->shouldReceive('isPending')->andReturn(true);
        $reward->shouldReceive('isExpired')->andReturn(false);
        $reward->member_id = 999;

        $repository = Mockery::mock(RewardsRepository::class);
        $repository->shouldReceive('find')->with(123)->andReturn($reward);

        $provider = new RewardDiscountProvider($repository);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $context = new DiscountContext(items: [], baseSubtotalCents: 10000, currentSubtotalCents: 10000, currentOfferDiscountCents: 0, appliedDiscounts: [], member: $member, rewardContext: new RewardContext(rewardId: 123));

        $this->assertNull($provider->apply($context));
    }

    public function test_apply_calculates_percentage_discount(): void
    {
        $definition = Mockery::mock(RewardDefinition::class)->makePartial();
        $definition->reward_type = 'percentage_discount';
        $definition->reward_config = ['percentage' => 15];

        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->shouldReceive('isPending')->andReturn(true);
        $reward->shouldReceive('isExpired')->andReturn(false);
        $reward->member_id = 1;
        $reward->rewardDefinition = $definition;

        $repository = Mockery::mock(RewardsRepository::class);
        $repository->shouldReceive('find')->with(123)->andReturn($reward);

        $provider = new RewardDiscountProvider($repository);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $context = new DiscountContext(
            items: [['id' => 1, 'price' => 100]],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: $member,
            rewardContext: new RewardContext(rewardId: 123)
        );

        $result = $provider->apply($context);

        $this->assertNotNull($result);
        $this->assertEquals(1500, $result->discountAmountCents);
        $this->assertEquals('reward', $result->type);
        $this->assertEquals('platform', $result->fundingSource);
    }

    public function test_apply_calculates_fixed_discount(): void
    {
        $definition = Mockery::mock(RewardDefinition::class)->makePartial();
        $definition->reward_type = 'fixed_discount';
        $definition->reward_config = ['amount' => 25]; // $25 fixed discount

        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->shouldReceive('isPending')->andReturn(true);
        $reward->shouldReceive('isExpired')->andReturn(false);
        $reward->member_id = 1;
        $reward->rewardDefinition = $definition;

        $repository = Mockery::mock(RewardsRepository::class);
        $repository->shouldReceive('find')->with(123)->andReturn($reward);

        $provider = new RewardDiscountProvider($repository);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $context = new DiscountContext(
            items: [['id' => 1, 'price' => 100]],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: $member,
            rewardContext: new RewardContext(rewardId: 123)
        );

        $result = $provider->apply($context);

        $this->assertNotNull($result);
        $this->assertEquals(2500, $result->discountAmountCents); // $25 = 2500 cents
    }

    public function test_apply_returns_null_for_zero_discount(): void
    {
        $definition = Mockery::mock(RewardDefinition::class)->makePartial();
        $definition->reward_type = 'percentage_discount';
        $definition->reward_config = ['percentage' => 0];

        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->shouldReceive('isPending')->andReturn(true);
        $reward->shouldReceive('isExpired')->andReturn(false);
        $reward->member_id = 1;
        $reward->rewardDefinition = $definition;

        $repository = Mockery::mock(RewardsRepository::class);
        $repository->shouldReceive('find')->with(123)->andReturn($reward);

        $provider = new RewardDiscountProvider($repository);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $context = new DiscountContext(
            items: [['id' => 1, 'price' => 100]],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: $member,
            rewardContext: new RewardContext(rewardId: 123)
        );

        $this->assertNull($provider->apply($context));
    }

    public function test_apply_caps_fixed_discount_at_subtotal(): void
    {
        $definition = Mockery::mock(RewardDefinition::class)->makePartial();
        $definition->reward_type = 'fixed_discount';
        $definition->reward_config = ['amount' => 20000]; // greater than subtotal

        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->shouldReceive('isPending')->andReturn(true);
        $reward->shouldReceive('isExpired')->andReturn(false);
        $reward->member_id = 1;
        $reward->rewardDefinition = $definition;

        $repository = Mockery::mock(RewardsRepository::class);
        $repository->shouldReceive('find')->with(123)->andReturn($reward);

        $provider = new RewardDiscountProvider($repository);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $context = new DiscountContext(
            items: [['id' => 1, 'price' => 100]],
            baseSubtotalCents: 10000, // subtotal less than fixed amount
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: $member,
            rewardContext: new RewardContext(rewardId: 123)
        );

        $result = $provider->apply($context);

        // Discount should never exceed subtotal
        $this->assertEquals(10000, $result->discountAmountCents);
    }

    public function test_apply_returns_null_for_subscription_when_not_applicable(): void
    {
        $definition = Mockery::mock(RewardDefinition::class)->makePartial();
        $definition->reward_type = 'percentage_discount';
        $definition->reward_config = ['percentage' => 15, 'applies_to' => 'one_time'];

        $reward = Mockery::mock(MemberReward::class)->makePartial();
        $reward->shouldReceive('isPending')->andReturn(true);
        $reward->shouldReceive('isExpired')->andReturn(false);
        $reward->member_id = 1;
        $reward->rewardDefinition = $definition;

        $repository = Mockery::mock(RewardsRepository::class);
        $repository->shouldReceive('find')->with(123)->andReturn($reward);

        $provider = new RewardDiscountProvider($repository);

        $member = Mockery::mock(Member::class)->makePartial();
        $member->id = 1;

        $context = new DiscountContext(
            items: [],
            baseSubtotalCents: 10000,
            currentSubtotalCents: 10000,
            currentOfferDiscountCents: 0,
            appliedDiscounts: [],
            member: $member,
            rewardContext: new RewardContext(rewardId: 123),
            isSubscription: true
        );

        $this->assertNull($provider->apply($context));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}