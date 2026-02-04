<?php

namespace App\Services\Billing\Webhooks;

use App\Enums\Subscriptions\SubscriptionStatus;
use App\Framework\Database\Database;
use App\Models\Order;
use App\Repositories\Billing\OrderRepository;
use App\Repositories\Subscriptions\SubscriptionRepository;

class StripeWebhookHandler
{
    public function __construct(
        private readonly OrderRepository        $orderRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly Database               $database
    )
    {
    }

    public function handlePaymentSucceeded(string $paymentIntentId): void
    {
        $this->database->transaction(function () use ($paymentIntentId) {
            $order = $this->orderRepository->findByPaymentIntent($paymentIntentId);

            if (!$order) {
                throw new \Exception("Order not found for payment intent: {$paymentIntentId}");
            }

            // Idempotency check
            if ($order->payment_status === 'paid') {
                return;
            }

            // Mark order as paid
            $order->update([
                'payment_status' => 'paid',
                'status' => 'processing',
                'paid_at' => now()
            ]);

            // Activate all subscriptions
            $subscriptionIds = $this->getSubscriptionIdsFromOrder($order);
            foreach ($subscriptionIds as $subscriptionId) {
                $subscription = $this->subscriptionRepository->find($subscriptionId);
                if ($subscription) {
                    $subscription->update([
                        'status' => SubscriptionStatus::ACTIVE->value,
                        'activated_at' => now()
                    ]);
                }
            }
        });
    }

    private function getSubscriptionIdsFromOrder(Order $order): array
    {
        if ($order->one_time_subscription_id) {
            $ids = [$order->one_time_subscription_id];

            if (isset($order->metadata['subscription_ids'])) {
                $ids = array_merge($ids, $order->metadata['subscription_ids']);
            }

            return array_unique($ids);
        }

        return [];
    }

    public function handlePaymentFailed(string $paymentIntentId): void
    {
        $this->database->transaction(function () use ($paymentIntentId) {
            $order = $this->orderRepository->findByPaymentIntent($paymentIntentId);

            if (!$order) {
                return;
            }

            $order->update([
                'payment_status' => 'failed',
                'status' => 'cancelled'
            ]);

            // Cancel subscriptions
            $subscriptionIds = $this->getSubscriptionIdsFromOrder($order);
            foreach ($subscriptionIds as $subscriptionId) {
                $subscription = $this->subscriptionRepository->find($subscriptionId);
                if ($subscription) {
                    $subscription->update([
                        'status' => SubscriptionStatus::CANCELLED->value
                    ]);
                }
            }
        });
    }
}