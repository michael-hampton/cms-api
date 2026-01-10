<?php

namespace App\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Repositories\SubscriptionRepository;
use App\Services\Payment\StripePaymentProcessor;

class SubscriptionBillingService
{
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly StripePaymentProcessor $stripeProcessor,
        private readonly Database               $database
    )
    {
    }

    /**
     * Update billing date for a subscription
     */
    public function updateBillingDate(
        int  $subscriptionId,
        int  $dayOfMonth,
        bool $prorate = true
    ): array
    {
        return $this->database->transaction(function () use ($subscriptionId, $dayOfMonth, $prorate) {
            $subscription = $this->subscriptionRepository->find($subscriptionId);

            if (!$subscription) {
                throw new \Exception('Subscription not found');
            }

            if (!$subscription->hasStripeSubscription()) {
                throw new \Exception('Can only update billing date for Stripe subscriptions');
            }

            if ($subscription->status !== 'active') {
                throw new \Exception('Can only update billing date for active subscriptions');
            }

            // Validate day of month
            if ($dayOfMonth < 1 || $dayOfMonth > 31) {
                throw new \Exception('Day must be between 1 and 31');
            }

            // Update in Stripe
            $result = $this->stripeProcessor->updateBillingCycleAnchor(
                $subscription->getStripeSubscriptionId(),
                $dayOfMonth,
                $prorate
            );

            if (!$result['success']) {
                throw new \Exception($result['message'] ?? 'Failed to update billing date');
            }

            // Update local subscription record
            $newBillingDate = new \DateTime($result['new_billing_date']);

            $this->subscriptionRepository->update($subscriptionId, [
                'next_billing_date' => $newBillingDate->format('Y-m-d H:i:s'),
                'metadata' => array_merge($subscription->metadata ?? [], [
                    'billing_day_of_month' => $dayOfMonth,
                    'last_billing_update' => date('Y-m-d H:i:s')
                ])
            ]);

            return [
                'success' => true,
                'new_billing_date' => $result['new_billing_date'],
                'message' => 'Billing date updated successfully'
            ];
        });
    }

    /**
     * Preview billing date change
     */
    public function previewBillingDateChange(int $subscriptionId, int $dayOfMonth): array
    {
        $subscription = $this->subscriptionRepository->find($subscriptionId);

        if (!$subscription) {
            return [
                'success' => false,
                'message' => 'Subscription not found'
            ];
        }

        if (!$subscription->hasStripeSubscription()) {
            return [
                'success' => false,
                'message' => 'Can only preview billing date changes for Stripe subscriptions'
            ];
        }

        return $this->stripeProcessor->calculateBillingDateProration(
            $subscription->getStripeSubscriptionId(),
            $dayOfMonth
        );
    }
}