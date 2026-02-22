<?php

namespace App\Tests\Unit\Repositories\Rewards;

use App\Enums\Rewards\RewardStatus;
use App\Models\MemberReward;
use App\Models\ProductRewardDefinition;
use App\Repositories\Rewards\ProductRewardRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

/**
 * Integration tests for ProductRewardRepository.
 *
 * Uses a real database connection — no mocks. Each test runs against a
 * truncated schema so results are deterministic.
 *
 * Known bugs surfaced by these tests (do not "fix" tests to hide them):
 *   - findProductIdsByReward() queries `reward_id` but the column is
 *     `reward_definition_id` → will return an empty collection.
 *   - approve() uses magic strings instead of RewardStatus enum values.
 */
class ProductRewardRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private ProductRewardRepository $repository;

    public function test_find_pending_rewards_for_products_returns_matching_pending_rewards(): void
    {
        $member = $this->createMember();
        $product = $this->createProduct();
        $rewardDef = $this->createRewardDefinition();

        // Link the reward definition to the product via the pivot table.
        $this->repository->link($rewardDef->id, $product->id);

        $memberReward = $this->createMemberReward([
            'member_id' => $member->id,
            'reward_definition_id' => $rewardDef->id,
            'status' => RewardStatus::PENDING->value,
        ]);

        $results = $this->repository->findPendingRewardsForProducts($member->id, [$product->id]);

        $this->assertCount(1, $results);
        $this->assertEquals($memberReward->id, $results->first()->id);
    }

    // -------------------------------------------------------------------------
    // findPendingRewardsForProducts
    // -------------------------------------------------------------------------

    public function test_find_pending_rewards_for_products_excludes_non_pending_statuses(): void
    {
        $member = $this->createMember();
        $product = $this->createProduct();
        $rewardDef = $this->createRewardDefinition();

        $this->repository->link($rewardDef->id, $product->id);

        // Approved reward — must be excluded.
        $this->createMemberReward([
            'member_id' => $member->id,
            'reward_definition_id' => $rewardDef->id,
            'status' => 'approved',
        ]);

        $results = $this->repository->findPendingRewardsForProducts($member->id, [$product->id]);

        $this->assertCount(0, $results);
    }

    public function test_find_pending_rewards_for_products_excludes_other_members(): void
    {
        $member1 = $this->createMember();
        $member2 = $this->createMember();
        $product = $this->createProduct();
        $rewardDef = $this->createRewardDefinition();

        $this->repository->link($rewardDef->id, $product->id);

        // Reward belongs to member2 — member1 query must not return it.
        $this->createMemberReward([
            'member_id' => $member2->id,
            'reward_definition_id' => $rewardDef->id,
            'status' => RewardStatus::PENDING->value,
        ]);

        $results = $this->repository->findPendingRewardsForProducts($member1->id, [$product->id]);

        $this->assertCount(0, $results);
    }

    public function test_find_pending_rewards_for_products_excludes_unlinked_products(): void
    {
        $member = $this->createMember();
        $linkedProduct = $this->createProduct();
        $unlinkedProduct = $this->createProduct();
        $rewardDef = $this->createRewardDefinition();

        // Only linked to $linkedProduct — querying $unlinkedProduct must return nothing.
        $this->repository->link($rewardDef->id, $linkedProduct->id);

        $this->createMemberReward([
            'member_id' => $member->id,
            'reward_definition_id' => $rewardDef->id,
            'status' => RewardStatus::PENDING->value,
        ]);

        $results = $this->repository->findPendingRewardsForProducts($member->id, [$unlinkedProduct->id]);

        $this->assertCount(0, $results);
    }

    public function test_find_pending_rewards_for_products_returns_empty_collection_when_product_ids_empty(): void
    {
        $member = $this->createMember();

        $results = $this->repository->findPendingRewardsForProducts($member->id, []);

        $this->assertTrue($results->isEmpty());
    }

    public function test_find_pending_rewards_for_products_eager_loads_reward_definition(): void
    {
        $member = $this->createMember();
        $product = $this->createProduct();
        $rewardDef = $this->createRewardDefinition();

        $this->repository->link($rewardDef->id, $product->id);

        $this->createMemberReward([
            'member_id' => $member->id,
            'reward_definition_id' => $rewardDef->id,
            'status' => RewardStatus::PENDING->value,
        ]);

        $results = $this->repository->findPendingRewardsForProducts($member->id, [$product->id]);

        // Relation must already be loaded — no lazy-load N+1.
        $this->assertTrue($results->first()->relationLoaded('rewardDefinition'));
        $this->assertEquals($rewardDef->id, $results->first()->rewardDefinition->id);
    }

    public function test_find_pending_rewards_for_products_accepts_multiple_product_ids(): void
    {
        $member = $this->createMember();
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();
        $rewardDef1 = $this->createRewardDefinition();
        $rewardDef2 = $this->createRewardDefinition();

        $this->repository->link($rewardDef1->id, $product1->id);
        $this->repository->link($rewardDef2->id, $product2->id);

        $reward1 = $this->createMemberReward([
            'member_id' => $member->id,
            'reward_definition_id' => $rewardDef1->id,
            'status' => RewardStatus::PENDING->value,
        ]);

        $reward2 = $this->createMemberReward([
            'member_id' => $member->id,
            'reward_definition_id' => $rewardDef2->id,
            'status' => RewardStatus::PENDING->value,
        ]);

        $results = $this->repository->findPendingRewardsForProducts(
            $member->id,
            [$product1->id, $product2->id],
        );

        $this->assertCount(2, $results);
        $ids = $results->pluck('id')->toArray();
        $this->assertContains($reward1->id, $ids);
        $this->assertContains($reward2->id, $ids);
    }

    public function test_approve_transitions_pending_reward_to_approved(): void
    {
        $memberReward = $this->createMemberReward(['status' => RewardStatus::PENDING->value]);

        $result = $this->repository->approve($memberReward->id, 'Auto-approved by test');

        $this->assertTrue($result);

        $fresh = MemberReward::find($memberReward->id);
        $this->assertEquals('approved', $fresh->status);
        $this->assertEquals('Auto-approved by test', $fresh->admin_notes);
    }

    // -------------------------------------------------------------------------
    // approve
    // -------------------------------------------------------------------------

    public function test_approve_returns_false_when_reward_already_approved(): void
    {
        // Simulate a concurrent approval — row is no longer pending.
        $memberReward = $this->createMemberReward(['status' => 'approved']);

        $result = $this->repository->approve($memberReward->id);

        // WHERE status = 'pending' scope means 0 rows updated → false.
        $this->assertFalse($result);
    }

    public function test_approve_returns_false_for_non_existent_id(): void
    {
        $result = $this->repository->approve(999999);

        $this->assertFalse($result);
    }

    public function test_approve_stores_empty_notes_by_default(): void
    {
        $memberReward = $this->createMemberReward(['status' => RewardStatus::PENDING->value]);

        $this->repository->approve($memberReward->id);

        $fresh = MemberReward::find($memberReward->id);
        $this->assertEquals('', $fresh->admin_notes);
    }

    public function test_approve_does_not_affect_other_rewards(): void
    {
        $target = $this->createMemberReward(['status' => RewardStatus::PENDING->value]);
        $bystander = $this->createMemberReward(['status' => RewardStatus::PENDING->value]);

        $this->repository->approve($target->id);

        $bystander->refresh();

        $this->assertEquals(RewardStatus::PENDING->value, $bystander->status);
    }

    public function test_link_creates_pivot_record(): void
    {
        $rewardDef = $this->createRewardDefinition();
        $product = $this->createProduct();

        $pivot = $this->repository->link($rewardDef->id, $product->id);

        $this->assertInstanceOf(ProductRewardDefinition::class, $pivot);
        $this->assertDatabaseHas('product_reward_definitions', [
            'reward_definition_id' => $rewardDef->id,
            'product_id' => $product->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // link / unlink
    // -------------------------------------------------------------------------

    public function test_link_is_idempotent_on_duplicate_call(): void
    {
        $rewardDef = $this->createRewardDefinition();
        $product = $this->createProduct();

        $first = $this->repository->link($rewardDef->id, $product->id);
        $second = $this->repository->link($rewardDef->id, $product->id);

        $this->assertEquals($first->id, $second->id);

        $count = ProductRewardDefinition::where('reward_definition_id', $rewardDef->id)
            ->where('product_id', $product->id)
            ->count();

        $this->assertEquals(1, $count);
    }

    public function test_unlink_removes_pivot_record(): void
    {
        $rewardDef = $this->createRewardDefinition();
        $product = $this->createProduct();

        $this->repository->link($rewardDef->id, $product->id);
        $this->repository->unlink($rewardDef->id, $product->id);

        $this->assertDatabaseMissing('product_reward_definitions', [
            'reward_definition_id' => $rewardDef->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_unlink_is_a_no_op_when_pivot_does_not_exist(): void
    {
        // Must not throw.
        $this->repository->unlink(9999, 9999);
        $this->assertTrue(true);
    }

    public function test_unlink_does_not_affect_other_pivot_records(): void
    {
        $rewardDef = $this->createRewardDefinition();
        $product1 = $this->createProduct();
        $product2 = $this->createProduct();

        $this->repository->link($rewardDef->id, $product1->id);
        $this->repository->link($rewardDef->id, $product2->id);

        $this->repository->unlink($rewardDef->id, $product1->id);

        $this->assertDatabaseHas('product_reward_definitions', [
            'reward_definition_id' => $rewardDef->id,
            'product_id' => $product2->id,
        ]);
    }

    public function test_find_reward_ids_by_product_returns_linked_reward_ids(): void
    {
        $product = $this->createProduct();
        $rewardDef = $this->createRewardDefinition();

        $this->repository->link($rewardDef->id, $product->id);

        $ids = $this->repository->findRewardIdsByProduct($product->id);

        $this->assertContains($rewardDef->id, $ids->toArray());
    }

    // -------------------------------------------------------------------------
    // findRewardIdsByProduct
    // -------------------------------------------------------------------------

    public function test_find_reward_ids_by_product_returns_empty_collection_when_none_linked(): void
    {
        $product = $this->createProduct();

        $ids = $this->repository->findRewardIdsByProduct($product->id);

        $this->assertTrue($ids->isEmpty());
    }

    /**
     * @bug The method queries `reward_id` but the column is `reward_definition_id`.
     *      This test documents the correct *expected* behaviour.
     *      It will fail until the column name is fixed in the repository.
     */
    public function test_find_product_ids_by_reward_returns_linked_product_ids(): void
    {
        $rewardDef = $this->createRewardDefinition();
        $product = $this->createProduct();

        $this->repository->link($rewardDef->id, $product->id);

        $ids = $this->repository->findProductIdsByReward($rewardDef->id);

        $this->assertContains(
            $product->id,
            $ids->toArray(),
            'findProductIdsByReward uses wrong column name (reward_id vs reward_definition_id)',
        );
    }

    // -------------------------------------------------------------------------
    // findProductIdsByReward
    // -------------------------------------------------------------------------

    public function test_find_product_ids_by_reward_returns_empty_when_none_linked(): void
    {
        $rewardDef = $this->createRewardDefinition();

        $ids = $this->repository->findProductIdsByReward($rewardDef->id);

        $this->assertTrue($ids->isEmpty());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ProductRewardRepository();
    }
}