<?php

namespace App\Listeners\Rewards;

use App\Events\Orders\OrderCompletedEvent;
use App\Services\Rewards\OrderRewardProcessor;

/**
 * Auto-approves any pending MemberRewards that are linked to products in the
 * completed order.
 *
 * Flow:
 *   OrderCompletedEvent fired
 *     → extract product IDs from order items
 *     → find pending rewards for this member that are linked to those products
 *     → approve each one inside a single transaction
 *     → write an audit log entry per approval
 *
 * Registration: add to your EventServiceProvider:
 *   OrderCompletedEvent::class => [ApproveProductLinkedRewardsListener::class]
 */
class ApproveProductLinkedRewardsListener
{
    public function __construct(
        private readonly OrderRewardProcessor $orderRewardProcessor
    )
    {
    }

    public function handle(OrderCompletedEvent $event): void
    {
        $this->orderRewardProcessor->processCompletedOrder($event->order);
    }
}