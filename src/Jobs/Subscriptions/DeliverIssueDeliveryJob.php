<?php

namespace App\Jobs\Subscriptions;

use App\Enums\Subscriptions\SubscriptionType;
use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;
use App\Services\Subscriptions\DeliveryChannels\EmailDeliveryChannel;
use App\Services\Subscriptions\DeliveryService;

class DeliverIssueDeliveryJob extends BaseJob
{
    public function __construct(
        private readonly IssuesDeliveredRepository $issuesDeliveredRepository,
        private readonly DeliveryService           $deliveryService,
        private readonly Database             $database,
        private readonly EmailDeliveryChannel $emailDeliveryChannel,
        private readonly Logger               $logger,
    )
    {
    }

    public function handle(int $issuesDeliveredId): void
    {
        $issuesDelivered = $this->issuesDeliveredRepository->find($issuesDeliveredId);

        if (!$issuesDelivered) {
            die('a');
            $this->logger->error('IssuesDelivered not found', ['id' => $issuesDeliveredId]);
            return;
        }

        if ($issuesDelivered->isDelivered()) {
            $this->logger->info('Delivery already completed, skipping', [
                'issues_delivered_id' => $issuesDelivered->id,
            ]);
            return;
        }

        // Register channels once, outside the transaction — this is pure
        // in-memory configuration and has no DB side effect.
        $this->deliveryService->registerChannel(
            SubscriptionType::DIGITAL->value,
            $this->emailDeliveryChannel
        );

        try {
            $this->database->transaction(function () use ($issuesDelivered): void {
                $subscription = $issuesDelivered->subscription(true)->first();
                $issueDelivery = $issuesDelivered->issueDelivery(true)->first();

                if (!$subscription || !$issueDelivery) {
                    throw new \RuntimeException('Missing subscription or issue delivery for IssuesDelivered #' . $issuesDelivered->id);
                }

                $this->deliveryService->send($subscription, $issueDelivery);
                $issuesDelivered->markAsDelivered();

                $this->logger->info('Issue delivered successfully', [
                    'issues_delivered_id' => $issuesDelivered->id,
                    'subscription_id' => $subscription->id,
                    'issue_delivery_id' => $issueDelivery->id,
                ]);
            });
        } catch (\Throwable $e) {
            // Record the failure outside the (rolled-back) transaction so the
            // status persists. Guarded with its own try/catch so a persistence
            // failure here doesn't swallow the original exception.
            try {
                $issuesDelivered->markAsFailed($e->getMessage());
            } catch (\Throwable $markException) {
                $this->logger->error('Could not persist delivery failure status', [
                    'issues_delivered_id' => $issuesDelivered->id,
                    'mark_error' => $markException->getMessage(),
                ]);
            }

            $this->logger->error('Issue delivery failed', [
                'issues_delivered_id' => $issuesDelivered->id,
                'error' => $e->getMessage(),
                'attempts' => $issuesDelivered->attempts ?? null,
            ]);

            throw $e;
        }
    }
}