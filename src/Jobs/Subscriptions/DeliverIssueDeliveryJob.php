<?php

namespace App\Jobs\Subscriptions;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;
use App\Services\Subscriptions\DeliveryService;

class DeliverIssueDeliveryJob
{
    public function __construct(
        private readonly IssuesDeliveredRepository $issuesDeliveredRepository,
        private readonly DeliveryService           $deliveryService,
        private readonly Database                  $database
    )
    {
    }

    public function handle(int $issuesDeliveredId): void
    {
        $issuesDelivered = $this->issuesDeliveredRepository->find($issuesDeliveredId);

        if (!$issuesDelivered) {
            Logger::error('IssuesDelivered not found', ['id' => $issuesDeliveredId]);
            return;
        }

        // Skip if already delivered (idempotency)
        if ($issuesDelivered->isDelivered()) {
            Logger::info('Delivery already completed, skipping', [
                'issues_delivered_id' => $issuesDelivered->id,
            ]);
            return;
        }

        try {
            $this->database->transaction(function () use ($issuesDelivered) {
                $subscription = $issuesDelivered->subscription(true)->first();
                $issueDelivery = $issuesDelivered->issueDelivery(true)->first();

                if (!$subscription || !$issueDelivery) {
                    throw new \Exception('Missing subscription or issue delivery');
                }

                $this->deliveryService->send($subscription, $issueDelivery);
                $issuesDelivered->markAsDelivered();

                Logger::info('Issue delivered successfully', [
                    'issues_delivered_id' => $issuesDelivered->id,
                    'subscription_id' => $subscription->id,
                    'issue_delivery_id' => $issueDelivery->id,
                ]);
            });
        } catch (\Exception $e) {
            $issuesDelivered->markAsFailed($e->getMessage());

            Logger::error('Issue delivery failed', [
                'issues_delivered_id' => $issuesDelivered->id,
                'error' => $e->getMessage(),
                'attempts' => $issuesDelivered->attempts,
            ]);

            throw $e;
        }
    }
}