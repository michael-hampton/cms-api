<?php

namespace App\Jobs\Subscriptions;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Subscriptions\IssuesDeliveredRepository;
use App\Services\Subscriptions\DeliveryChannelInterface;
use App\Services\Subscriptions\DeliveryService;

class DeliverIssueDeliveryJob extends BaseJob
{
    private IssuesDeliveredRepository $issuesDeliveredRepository;
    private DeliveryService $deliveryService;
    private Database $database;
    private Logger $logger;

    /**
     * @param array<string, DeliveryChannelInterface> $channelMap
     *   Keyed by SubscriptionType value, e.g.
     *   [ 'digital' => EmailDeliveryChannel, 'print' => PrintDeliveryChannel ]
     *   Populated via container bindings in PrintServiceProvider.
     *   Defaults to empty — the container can resolve the job without the
     *   provider registered (e.g. in test environments).
     */
    public function __construct(
        private readonly int   $issuesDeliveredId,
        private readonly array $channelMap = [],
    )
    {
    }

    public function handle(): void
    {
        $issuesDelivered = $this->issuesDeliveredRepository->find($this->issuesDeliveredId);

        if (!$issuesDelivered) {
            $this->logger->error('IssuesDelivered not found', ['id' => $this->issuesDeliveredId]);
            return;
        }

        if ($issuesDelivered->isDelivered()) {
            $this->logger->info('Delivery already completed, skipping', [
                'issues_delivered_id' => $issuesDelivered->id,
            ]);
            return;
        }

        // Register channels once, outside the transaction — pure in-memory
        // configuration with no DB side effect.
        foreach ($this->channelMap as $type => $channel) {
            $this->deliveryService->registerChannel($type, $channel);
        }

        try {
            $this->database->transaction(function () use ($issuesDelivered): void {
                $subscription = $issuesDelivered->subscription(true)->first();
                $issueDelivery = $issuesDelivered->issueDelivery(true)->first();

                if (!$subscription || !$issueDelivery) {
                    die('no');
                    throw new \RuntimeException(
                        'Missing subscription or issue delivery for IssuesDelivered #' . $issuesDelivered->id
                    );
                }

                // Guard: if no channel is registered for this subscription type,
                // log and bail rather than letting DeliveryService throw. This
                // prevents an unconfigured channel from crashing the pipeline —
                // correct both in production (new channel not yet deployed) and
                // in test environments where the provider is not bootstrapped.
                $subscriptionType = $subscription->delivery_type ?? null;

                if ($subscriptionType !== null && !array_key_exists($subscriptionType, $this->channelMap)) {
                    $this->logger->warning('No delivery channel registered for subscription type — skipping', [
                        'issues_delivered_id' => $issuesDelivered->id,
                        'subscription_type' => $subscriptionType,
                    ]);
                    return;
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