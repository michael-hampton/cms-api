<?php

namespace App\Services\Billing\Payments;

use App\Models\Member;
use App\Models\Order;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;

class PaymentIntentService
{
    public function __construct(
        private readonly StripePaymentProcessor $stripe
    )
    {
    }

    /**
     * Create Stripe PaymentIntent for order
     * This happens OUTSIDE the database transaction
     */
    public function createForOrder(
        Order  $order,
        array  $subscriptions,
        Member $member,
        int    $siteId
    ): array
    {
        $subscriptionIds = array_map(fn($s) => $s['subscription']->id, $subscriptions);

        return $this->stripe->createPaymentIntentWithCustomer([
            'amount' => $order->total,
            'currency' => strtolower($order->currency),
            'order_id' => $order->id,
            'subscription_id' => count($subscriptionIds) === 1 ? $subscriptionIds[0] : null,
            'site_id' => $siteId,
            'member' => $member,
            'metadata' => [
                'order_id' => $order->id,
                'subscription_count' => count($subscriptions),
                'subscription_ids' => implode(',', $subscriptionIds),
                'member_id' => $member->id,
                'multiple_subscriptions' => count($subscriptionIds) > 1
            ]
        ]);
    }
}