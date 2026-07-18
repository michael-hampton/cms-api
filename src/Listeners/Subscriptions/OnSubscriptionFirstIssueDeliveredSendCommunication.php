<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionFirstIssueDelivered;
use App\Framework\Support\Logger;
use App\Services\Subscriptions\Communications\FirstIssueCommunicationDispatchService;

/**
 * Non-critical: a missed first-issue communication must never block or
 * undo the delivery confirmation that already committed.
 */
final class OnSubscriptionFirstIssueDeliveredSendCommunication
{
    public function __construct(
        private readonly FirstIssueCommunicationDispatchService $dispatcher,
        private readonly Logger $logger,
    ) {
    }

    public function handle(SubscriptionFirstIssueDelivered $event): void
    {
        try {
            $this->dispatcher->dispatch($event->subscription);
        } catch (\Throwable $e) {
            $this->logger->error('OnSubscriptionFirstIssueDeliveredSendCommunication: dispatch failed', [
                'subscription_id' => $event->subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
