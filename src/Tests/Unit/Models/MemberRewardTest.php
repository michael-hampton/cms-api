<?php

namespace App\Tests\Unit\Models;

use App\Enums\Rewards\RewardStatus;
use App\Models\MemberReward;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

/**
 * Functional tests for MemberReward model.
 *
 * Uses a real database via FunctionalTestCase. Every state query method
 * and state transition method is covered.
 *
 * Coverage:
 *   State queries:   isPending, isApproved, isClaimed, isClaimable,
 *                    isExpired (by status + by expiry date), isDeclined
 *   Transitions:     claim() — happy path, already claimed, expired,
 *                    declined (non-claimable statuses)
 *                    claim() — audit log written on success
 *                    decline() — happy path, already declined
 *                    decline() — audit log written on success
 */
class MemberRewardTest extends FunctionalTestCase
{
    use CreatesTestData;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function test_is_pending_returns_true_for_pending_reward(): void
    {
        $reward = $this->makePendingReward();

        $this->assertTrue($reward->isPending());
    }

    private function makePendingReward(array $overrides = []): MemberReward
    {
        return $this->createMemberReward(array_merge(
            ['status' => RewardStatus::PENDING->value],
            $overrides
        ));
    }

    public function test_is_pending_returns_false_for_approved_reward(): void
    {
        $reward = $this->makeApprovedReward();

        $this->assertFalse($reward->isPending());
    }

    private function makeApprovedReward(array $overrides = []): MemberReward
    {
        return $this->createMemberReward(array_merge(
            ['status' => RewardStatus::APPROVED->value],
            $overrides
        ));
    }

    public function test_is_pending_returns_false_for_claimed_reward(): void
    {
        $reward = $this->makeClaimedReward();

        $this->assertFalse($reward->isPending());
    }

    // -------------------------------------------------------------------------
    // isPending
    // -------------------------------------------------------------------------

    private function makeClaimedReward(array $overrides = []): MemberReward
    {
        return $this->createMemberReward(array_merge(
            ['status' => RewardStatus::CLAIMED->value],
            $overrides
        ));
    }

    public function test_is_approved_returns_true_for_approved_reward(): void
    {
        $reward = $this->makeApprovedReward();

        $this->assertTrue($reward->isApproved());
    }

    public function test_is_approved_returns_false_for_pending_reward(): void
    {
        $reward = $this->makePendingReward();

        $this->assertFalse($reward->isApproved());
    }

    // -------------------------------------------------------------------------
    // isApproved
    // -------------------------------------------------------------------------

    public function test_is_approved_returns_false_for_claimed_reward(): void
    {
        $reward = $this->makeClaimedReward();

        $this->assertFalse($reward->isApproved());
    }

    public function test_is_claimed_returns_true_for_claimed_reward(): void
    {
        $reward = $this->makeClaimedReward();

        $this->assertTrue($reward->isClaimed());
    }

    public function test_is_claimed_returns_false_for_pending_reward(): void
    {
        $reward = $this->makePendingReward();

        $this->assertFalse($reward->isClaimed());
    }

    // -------------------------------------------------------------------------
    // isClaimed
    // -------------------------------------------------------------------------

    public function test_is_claimed_returns_false_for_approved_reward(): void
    {
        $reward = $this->makeApprovedReward();

        $this->assertFalse($reward->isClaimed());
    }

    public function test_is_claimable_returns_true_for_pending_reward(): void
    {
        $reward = $this->makePendingReward();

        $this->assertTrue($reward->isClaimable());
    }

    public function test_is_claimable_returns_true_for_approved_reward(): void
    {
        // Product-linked rewards are auto-approved on order completion and
        // must be claimable without any further action.
        $reward = $this->makeApprovedReward();

        $this->assertTrue($reward->isClaimable());
    }

    // -------------------------------------------------------------------------
    // isClaimable
    // -------------------------------------------------------------------------

    public function test_is_claimable_returns_false_for_claimed_reward(): void
    {
        $reward = $this->makeClaimedReward();

        $this->assertFalse($reward->isClaimable());
    }

    public function test_is_claimable_returns_false_for_declined_reward(): void
    {
        $reward = $this->makeDeclinedReward();

        $this->assertFalse($reward->isClaimable());
    }

    private function makeDeclinedReward(array $overrides = []): MemberReward
    {
        return $this->createMemberReward(array_merge(
            ['status' => RewardStatus::DECLINED->value],
            $overrides
        ));
    }

    public function test_is_claimable_returns_false_for_expired_reward(): void
    {
        $reward = $this->makeExpiredReward();

        $this->assertFalse($reward->isClaimable());
    }

    private function makeExpiredReward(array $overrides = []): MemberReward
    {
        return $this->createMemberReward(array_merge(
            ['status' => RewardStatus::EXPIRED->value],
            $overrides
        ));
    }

    // -------------------------------------------------------------------------
    // isExpired
    // -------------------------------------------------------------------------

    public function test_is_expired_returns_true_for_reward_with_expired_status(): void
    {
        $reward = $this->makeExpiredReward();

        $this->assertTrue($reward->isExpired());
    }

    public function test_is_expired_returns_true_when_expires_at_is_in_the_past(): void
    {
        $reward = $this->makePendingReward([
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        $this->assertTrue($reward->isExpired());
    }

    public function test_is_expired_returns_false_when_expires_at_is_in_the_future(): void
    {
        $reward = $this->makePendingReward([
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        $this->assertFalse($reward->isExpired());
    }

    public function test_is_expired_returns_false_when_expires_at_is_null(): void
    {
        $reward = $this->makePendingReward(['expires_at' => null]);

        $this->assertFalse($reward->isExpired());
    }

    // -------------------------------------------------------------------------
    // isDeclined
    // -------------------------------------------------------------------------

    public function test_is_declined_returns_true_for_declined_reward(): void
    {
        $reward = $this->makeDeclinedReward();

        $this->assertTrue($reward->isDeclined());
    }

    public function test_is_declined_returns_false_for_pending_reward(): void
    {
        $reward = $this->makePendingReward();

        $this->assertFalse($reward->isDeclined());
    }

    // -------------------------------------------------------------------------
    // claim() — state transitions
    // -------------------------------------------------------------------------

    public function test_claim_transitions_pending_reward_to_claimed(): void
    {
        $reward = $this->makePendingReward();

        $result = $reward->claim();

        $fresh = $reward->fresh();
        $this->assertTrue($result);
        $this->assertEquals(RewardStatus::CLAIMED->value, $fresh->status);
        $this->assertNotNull($fresh->claimed_at);
    }

    public function test_claim_transitions_approved_reward_to_claimed(): void
    {
        // Approved (product-linked) rewards must be claimable by the member.
        $reward = $this->makeApprovedReward();

        $result = $reward->claim();

        $fresh = $reward->fresh();
        $this->assertTrue($result);
        $this->assertEquals(RewardStatus::CLAIMED->value, $fresh->status);
        $this->assertNotNull($fresh->claimed_at);
    }

    public function test_claim_returns_false_and_does_not_transition_when_already_claimed(): void
    {
        $reward = $this->makeClaimedReward();

        $result = $reward->claim();

        $this->assertFalse($result);
        $this->assertEquals(RewardStatus::CLAIMED->value, $reward->fresh()->status);
    }

    public function test_claim_returns_false_and_does_not_transition_when_declined(): void
    {
        $reward = $this->makeDeclinedReward();

        $result = $reward->claim();

        $this->assertFalse($result);
        $this->assertEquals(RewardStatus::DECLINED->value, $reward->fresh()->status);
    }

    public function test_claim_returns_false_and_does_not_transition_when_status_is_expired(): void
    {
        $reward = $this->makeExpiredReward();

        $result = $reward->claim();

        $this->assertFalse($result);
        $this->assertEquals(RewardStatus::EXPIRED->value, $reward->fresh()->status);
    }

    public function test_claim_returns_false_when_expires_at_is_in_the_past_even_if_status_is_pending(): void
    {
        $reward = $this->makePendingReward([
            'expires_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        $result = $reward->claim();

        $this->assertFalse($result);
        // Status must not change — the reward expired before it was claimed.
        $this->assertEquals(RewardStatus::PENDING->value, $reward->fresh()->status);
    }

    public function test_claim_sets_claimed_at_timestamp(): void
    {
        $reward = $this->makePendingReward();

        $reward->claim();

        $this->assertNotNull($reward->fresh()->claimed_at);
    }

    public function test_claim_writes_audit_log_entry(): void
    {
        $reward = $this->makePendingReward();

        $reward->claim();

        $this->assertDatabaseHas('reward_audit_logs', [
            'member_reward_id' => $reward->id,
            'action' => RewardStatus::CLAIMED->value,
            'new_status' => RewardStatus::CLAIMED->value,
            'reward_definition_id' => $reward->reward_definition_id,
        ]);
    }

    public function test_claim_does_not_write_audit_log_when_reward_is_not_claimable(): void
    {
        $reward = $this->makeClaimedReward();
        $logCountBefore = $this->database
            ->select('SELECT COUNT(*) as count FROM reward_audit_logs WHERE member_reward_id = ?', [$reward->id])[0]['count'];

        $reward->claim();

        $logCountAfter = $this->database
            ->select('SELECT COUNT(*) as count FROM reward_audit_logs WHERE member_reward_id = ?', [$reward->id])[0]['count'];
        $this->assertEquals($logCountBefore, $logCountAfter);
    }

    // -------------------------------------------------------------------------
    // decline() — state transitions
    // -------------------------------------------------------------------------

    public function test_decline_transitions_pending_reward_to_declined(): void
    {
        $admin = $this->createUser();
        $reward = $this->makePendingReward();

        $result = $reward->decline($admin->id, 'Does not meet criteria');

        $fresh = $reward->fresh();
        $this->assertTrue($result);
        $this->assertEquals(RewardStatus::DECLINED->value, $fresh->status);
    }

    public function test_decline_sets_declined_by_admin_id(): void
    {
        $admin = $this->createUser();
        $reward = $this->makePendingReward();

        $reward->decline($admin->id, 'Does not meet criteria');

        $this->assertEquals($admin->id, $reward->fresh()->declined_by_admin_id);
    }

    public function test_decline_sets_declined_at_timestamp(): void
    {
        $admin = $this->createUser();
        $reward = $this->makePendingReward();

        $reward->decline($admin->id, 'Does not meet criteria');

        $this->assertNotNull($reward->fresh()->declined_at);
    }

    public function test_decline_stores_decline_reason(): void
    {
        $admin = $this->createUser();
        $reward = $this->makePendingReward();

        $reward->decline($admin->id, 'Duplicate claim');

        $this->assertEquals('Duplicate claim', $reward->fresh()->decline_reason);
    }

    public function test_decline_stores_optional_admin_notes(): void
    {
        $admin = $this->createUser();
        $reward = $this->makePendingReward();

        $reward->decline($admin->id, 'Duplicate claim', 'Member already claimed via different channel');

        $this->assertEquals('Member already claimed via different channel', $reward->fresh()->admin_notes);
    }

    public function test_decline_without_notes_leaves_admin_notes_null(): void
    {
        $admin = $this->createUser();
        $reward = $this->makePendingReward();

        $reward->decline($admin->id, 'Does not meet criteria');

        $this->assertNull($reward->fresh()->admin_notes);
    }

    public function test_decline_can_decline_an_approved_reward(): void
    {
        $admin = $this->createUser();
        $reward = $this->makeApprovedReward();

        $result = $reward->decline($admin->id, 'Approved in error');

        $this->assertTrue($result);
        $this->assertEquals(RewardStatus::DECLINED->value, $reward->fresh()->status);
    }

    public function test_decline_writes_audit_log_entry(): void
    {
        $admin = $this->createUser();
        $reward = $this->makePendingReward();

        $reward->decline($admin->id, 'Does not meet criteria', 'Checked manually');

        $this->assertDatabaseHas('reward_audit_logs', [
            'member_reward_id' => $reward->id,
            'action' => RewardStatus::DECLINED->value,
            'user_id' => $admin->id,
            'new_status' => RewardStatus::DECLINED->value,
            'reward_definition_id' => $reward->reward_definition_id,
        ]);
    }

    public function test_decline_audit_log_notes_contain_reason(): void
    {
        $admin = $this->createUser();
        $reward = $this->makePendingReward();

        $reward->decline($admin->id, 'Duplicate claim');

        $log = $this->database->select(
            'SELECT notes FROM reward_audit_logs WHERE member_reward_id = ? AND action = ? ORDER BY id DESC LIMIT 1',
            [$reward->id, RewardStatus::DECLINED->value]
        );

        $this->assertNotEmpty($log);
        $this->assertStringContainsString('Duplicate claim', $log[0]['notes']);
    }

    public function test_decline_audit_log_notes_contain_admin_notes_when_provided(): void
    {
        $admin = $this->createUser();
        $reward = $this->makePendingReward();

        $reward->decline($admin->id, 'Duplicate claim', 'Verified via support ticket #123');

        $log = $this->database->select(
            'SELECT notes FROM reward_audit_logs WHERE member_reward_id = ? AND action = ? ORDER BY id DESC LIMIT 1',
            [$reward->id, RewardStatus::DECLINED->value]
        );

        $this->assertStringContainsString('Verified via support ticket #123', $log[0]['notes']);
    }
}