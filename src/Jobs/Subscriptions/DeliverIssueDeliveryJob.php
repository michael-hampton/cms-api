<?php

namespace App\Jobs\Subscriptions;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Jobs\BaseJob;
use App\Repositories\Subscriptions\SubscriptionIssueFulfilmentRepository;
use App\Services\Subscriptions\DeliveryChannelInterface;
use App\Services\Subscriptions\DeliveryService;

class DeliverIssueDeliveryJob extends BaseJob
{
    private SubscriptionIssueFulfilmentRepository $subscriptionIssueFulfilmentRepository;
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
        private readonly int   $subscriptionIssueFulfilmentId,
        private readonly array $channelMap = [],
    )
    {
    }

    public function handle(): void
    {
        $subscriptionIssueFulfilment = $this->subscriptionIssueFulfilmentRepository->find($this->subscriptionIssueFulfilmentId);

        if (!$subscriptionIssueFulfilment) {
            $this->logger->error('SubscriptionIssueFulfilment not found', ['id' => $this->subscriptionIssueFulfilmentId]);
            return;
        }

        if ($subscriptionIssueFulfilment->isDelivered()) {
            $this->logger->info('Delivery already completed, skipping', [
                'subscription_issue_fulfilment_id' => $subscriptionIssueFulfilment->id,
            ]);
            return;
        }

        // Register channels once, outside the transaction — pure in-memory
        // configuration with no DB side effect.
        foreach ($this->channelMap as $type => $channel) {
            $this->deliveryService->registerChannel($type, $channel);
        }

        try {
            $this->database->transaction(function () use ($subscriptionIssueFulfilment): void {
                $subscription = $subscriptionIssueFulfilment->subscription(true)->first();
                $issueDelivery = $subscriptionIssueFulfilment->issueDelivery(true)->first();

                if (!$subscription || !$issueDelivery) {
                    throw new \RuntimeException(
                        'Missing subscription or issue delivery for SubscriptionIssueFulfilment #' . $subscriptionIssueFulfilment->id
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
                        'subscription_issue_fulfilment_id' => $subscriptionIssueFulfilment->id,
                        'subscription_type' => $subscriptionType,
                    ]);
                    return;
                }

                $this->deliveryService->send($subscription, $issueDelivery);
                $subscriptionIssueFulfilment->markAsDelivered();

                $this->logger->info('Issue delivered successfully', [
                    'subscription_issue_fulfilment_id' => $subscriptionIssueFulfilment->id,
                    'subscription_id' => $subscription->id,
                    'issue_delivery_id' => $issueDelivery->id,
                ]);
            });
        } catch (\Throwable $e) {
            try {
                $subscriptionIssueFulfilment->markAsFailed($e->getMessage());
            } catch (\Throwable $markException) {
                $this->logger->error('Could not persist delivery failure status', [
                    'subscription_issue_fulfilment_id' => $subscriptionIssueFulfilment->id,
                    'mark_error' => $markException->getMessage(),
                ]);
            }

            $this->logger->error('Issue delivery failed', [
                'subscription_issue_fulfilment_id' => $subscriptionIssueFulfilment->id,
                'error' => $e->getMessage(),
                'attempts' => $subscriptionIssueFulfilment->attempts ?? null,
            ]);

            throw $e;
        }
    }
}