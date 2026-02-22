<?php

namespace App\Repositories\Rewards;

use App\Enums\Rewards\RewardStatus;
use App\Framework\Support\Collection;
use App\Models\MemberReward;
use App\Models\ProductRewardDefinition;
use App\Repositories\Repository;

class ProductRewardRepository extends Repository
{
    /**
     * Find all PENDING MemberRewards that belong to a given member and are
     * linked (via the product_reward_definitions pivot) to any of the given
     * product IDs.
     *
     * Called by ApproveProductLinkedRewardsListener after an order completes.
     */
    public function findPendingRewardsForProducts(int $memberId, array $productIds): Collection
    {
        if (empty($productIds)) {
            return new Collection([]);
        }

        return MemberReward::where('member_id', $memberId)
            ->where('status', RewardStatus::PENDING->value)
            ->whereHas('rewardDefinition', function ($q) use ($productIds) {
                $q->whereHas('products', function ($q2) use ($productIds) {
                    $q2->whereIn('products.id', $productIds);
                });
            })
            ->with(['rewardDefinition'])
            ->get();
    }

    /**
     * Transition a single MemberReward from pending → approved.
     *
     * Returns true only when the row was updated (guards against concurrent
     * double-approval by scoping the WHERE to status = 'pending').
     */
    public function approve(int $memberRewardId, string $notes = ''): bool
    {
        return (bool)MemberReward::where('id', $memberRewardId)
            ->where('status', RewardStatus::PENDING->value)
            ->update([
                'status' => RewardStatus::APPROVED->value,
                'admin_notes' => $notes,
            ]);
    }

    // -------------------------------------------------------------------------
    // Pivot management (used by admin UI / seeder)
    // -------------------------------------------------------------------------

    /**
     * Attach a product to a reward. Idempotent — silently skips duplicate.
     */
    public function link(int $rewardId, int $productId): ProductRewardDefinition
    {
        return ProductRewardDefinition::firstOrCreate([
            'reward_definition_id' => $rewardId,
            'product_id' => $productId,
        ]);
    }

    /**
     * Remove the link between a reward and a product.
     */
    public function unlink(int $rewardId, int $productId): void
    {
        ProductRewardDefinition::where('reward_definition_id', $rewardId)
            ->where('product_id', $productId)
            ->delete();
    }

    /**
     * Return all reward IDs that are linked to the given product.
     */
    public function findRewardIdsByProduct(int $productId): Collection
    {
        return ProductRewardDefinition::where('product_id', $productId)
            ->get()
            ->pluck('reward_definition_id');
    }

    /**
     * Return all product IDs linked to the given reward.
     */
    public function findProductIdsByReward(int $rewardId): Collection
    {
        return ProductRewardDefinition::where('reward_definition_id', $rewardId)
            ->get()
            ->pluck('product_id');
    }

    protected function getModelClass(): string
    {
        return MemberReward::class;
    }
}