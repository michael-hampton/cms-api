<?php

namespace App\Listeners\Refunds;

use App\Events\Refunds\RefundCreated;
use App\Services\Billing\OrderHistoryService;

class LogRefundHistory
{
    public function __construct(
        private readonly OrderHistoryService $historyService
    )
    {
    }

    public function handle(RefundCreated $event): void
    {
        $this->historyService->logRefundCreated(
            $event->order->id,
            $event->refund->id,
            $event->order->user_id,
            $event->reason
        );
    }
}