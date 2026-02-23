<?php

namespace App\Services\Rewards;

use App\Framework\Database\Database;
use App\Framework\Events\EventDispatcher;
use App\Framework\Support\Logger;
use App\Models\MemberReward;
use App\Models\Order;
use App\Repositories\Rewards\ProductRewardRepository;
use App\Repositories\Rewards\RewardAuditLogRepository;
use App\Repositories\Rewards\RewardDefinitionRepository;

/**
 * Processes product-linked rewards when an order completes.
 *
 * Responsibility: for each product in the completed order, find any pending
 * member rewards that are linked to that product and approve them.
 *
 * This is a non-critical flow: failures are caught and logged rather than
 * re-thrown so that order completion is never blocked by reward processing.
 */
class OrderRewardProcessor
{
    public function __construct(
        private readonly RewardDefinitionRepository $memberRewardRepository,
        private readonly ProductRewardRepository    $productRewardRepository,
        private readonly EventDispatcher            $dispatcher,
        private readonly Database                   $database,
        private readonly RewardAuditLogRepository   $auditLogRepository,
    )
    {
    }

    /**
     * Approve all pending rewards that are linked to products in the given order.
     *
     * Called from the OrderCompleted event listener — failures must not propagate.
     */
    public function processCompletedOrder(Order $order): void
    {

        $memberId = $order->user_id;

        if (!$order->user_id) {
            return;
        }

        $productIds = $this->resolveProductIds($order);

        if (!$productIds) {
            return;
        }

        $pendingRewards = $this->productRewardRepository->findPendingRewardsForProducts(
            $order->user_id,
            $productIds
        );

        if ($pendingRewards->isEmpty()) {
            return;
        }

        $this->database->transaction(function () use ($pendingRewards, $order, $memberId) {
            foreach ($pendingRewards as $reward) {
                $this->approveMemberReward($reward, $order);
            }
        });
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve the list of product IDs from the order's line items.
     * Assumes order has an `items` relationship with a `product_id` column.
     */
    private function resolveProductIds(Order $order): array
    {
        if (!$order->items) {
            return [];
        }

        return $order->items
            ->pluck('product_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Find all pending rewards for the member that are linked to this product,
     * approve each, and dispatch a MemberRewardApproved event.
     */
    private function approveMemberReward(
        MemberReward $reward,      // ← correct type
        Order        $order,
    ): void
    {
        try {
            $notes = "Auto-approved: order #{$order->order_number} completed";
            $approved = $this->productRewardRepository->approve($reward->id, $notes);

            if (!$approved) {
                // Row was already changed concurrently — skip silently.
                Logger::info('ProductRewardApproval: skipped concurrent change', [
                    'member_reward_id' => $reward->id,
                    'order_id' => $order->id,
                ]);
                return;
            }

            $this->auditLogRepository->logAction(
                memberRewardId: $reward->id,
                action: 'approved',
                userId: null,
                oldStatus: 'pending',
                newStatus: 'approved',
                notes: $notes,
                rewardDefinitionId: $reward->reward_definition_id,
            );
        } catch (\Throwable $e) {
            // Non-critical: log and continue — never block order completion
            Logger::error('Failed to process reward approval for product purchase', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}