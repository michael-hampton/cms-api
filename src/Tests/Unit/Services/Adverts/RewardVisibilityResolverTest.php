<?php

namespace App\Tests\Unit\Services\Adverts;

use App\Enums\Adverts\SuppressionReason;
use App\Repositories\Rewards\RewardAuditLogRepository;
use App\Repositories\Rewards\RewardDefinitionRepository;
use App\Repositories\Rewards\RewardsRepository;
use App\Services\Adverts\RenderContext;
use App\Services\Adverts\RewardVisibilityResolver;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class RewardVisibilityResolverTest extends FunctionalTestCase
{
    use CreatesTestData;

    private RewardVisibilityResolver $resolver;
    private RewardsRepository $repository;

    public function testHidesRewardForUnauthenticated(): void
    {
        $member = $this->createMember();
        $reward = $this->createMemberReward([
            'member_id' => $member->id,
            'status' => 'pending',
        ]);

        $context = RenderContext::forNewsletter(1, null);
        $decision = $this->resolver->resolve($reward, $context);

        $this->assertFalse($decision->shouldRender);
        $this->assertEquals(SuppressionReason::NOT_AUTHENTICATED, $decision->reason);
    }

    public function testShowsPendingRewardToOwner(): void
    {
        $member = $this->createMember();
        $reward = $this->createMemberReward([
            'member_id' => $member->id,
            'status' => 'pending',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        $context = RenderContext::forNewsletter(1, $member);
        $decision = $this->resolver->resolve($reward, $context);

        $this->assertTrue($decision->shouldRender);
        $this->assertEquals($reward->id, $decision->metadata['reward_id']);
    }

    public function testHidesRewardForWrongMember(): void
    {
        $member1 = $this->createMember();
        $member2 = $this->createMember();

        $reward = $this->createMemberReward([
            'member_id' => $member1->id,
            'status' => 'pending',
        ]);

        $context = RenderContext::forNewsletter(1, $member2);
        $decision = $this->resolver->resolve($reward, $context);

        $this->assertFalse($decision->shouldRender);
        $this->assertEquals(SuppressionReason::WRONG_MEMBER, $decision->reason);
    }

    public function testHidesClaimedReward(): void
    {
        $member = $this->createMember();
        $reward = $this->createMemberReward([
            'member_id' => $member->id,
            'status' => 'claimed',
            'claimed_at' => date('Y-m-d H:i:s'),
        ]);

        $context = RenderContext::forNewsletter(1, $member);
        $decision = $this->resolver->resolve($reward, $context);

        $this->assertFalse($decision->shouldRender);
        $this->assertEquals(SuppressionReason::ALREADY_CLAIMED, $decision->reason);
    }

    public function testHidesExpiredReward(): void
    {
        $member = $this->createMember();
        $reward = $this->createMemberReward([
            'member_id' => $member->id,
            'status' => 'pending',
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        $context = RenderContext::forNewsletter(1, $member);
        $decision = $this->resolver->resolve($reward, $context);

        $this->assertFalse($decision->shouldRender);
        $this->assertEquals(SuppressionReason::REWARD_EXPIRED, $decision->reason);
    }

    public function testHidesDeclinedReward(): void
    {
        $member = $this->createMember();
        $admin = $this->createUser();

        $reward = $this->createMemberReward([
            'member_id' => $member->id,
            'status' => 'pending',
        ]);

        $reward->decline($admin->id, 'Test reason');

        $context = RenderContext::forNewsletter(1, $member);
        $decision = $this->resolver->resolve($reward, $context);

        $this->assertFalse($decision->shouldRender);
        $this->assertEquals(SuppressionReason::REWARD_DECLINED, $decision->reason);
    }

    public function testResolveForMemberReturnsOnlyEligible(): void
    {
        $member = $this->createMember();

        $pendingReward = $this->createMemberReward([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'status' => 'pending',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);

        $claimedReward = $this->createMemberReward([
            'member_id' => $member->id,
            'site_id' => $this->siteId,
            'status' => 'claimed',
        ]);

        $context = RenderContext::forNewsletter(1, $member);
        $decisions = $this->resolver->resolveForMember($member->id, $this->siteId, $context);

        $this->assertCount(1, $decisions);
        $this->assertEquals($pendingReward->id, $decisions[0]['reward']->id);
    }

    public function testIncludesVoucherCodeInMetadata(): void
    {
        $member = $this->createMember();
        $definition = $this->createRewardDefinition();

        $reward = $this->createMemberReward([
            'member_id' => $member->id,
            'reward_definition_id' => $definition->id,
            'status' => 'pending',
        ]);

        $voucher = \App\Models\RewardVoucherCode::create([
            'reward_definition_id' => $definition->id,
            'member_reward_id' => $reward->id,
            'site_id' => $this->siteId,
            'voucher_code' => 'TEST123',
            'provider' => 'test',
            'value' => 100,
            'is_used' => false,
        ]);

        $reward = $reward->fresh();
        $context = RenderContext::forNewsletter(1, $member);
        $decision = $this->resolver->resolve($reward, $context);

        $this->assertTrue($decision->shouldRender);
        $this->assertEquals('TEST123', $decision->metadata['voucher_code']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $rewardDefRepo = new RewardDefinitionRepository(new RewardAuditLogRepository());
        $this->repository = new RewardsRepository($rewardDefRepo, new RewardAuditLogRepository());
        $this->resolver = new RewardVisibilityResolver($this->repository);
    }
}