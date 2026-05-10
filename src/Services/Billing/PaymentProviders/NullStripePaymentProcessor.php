<?php

namespace App\Services\Billing\PaymentProviders;

class NullStripePaymentProcessor extends StripePaymentProcessor
{
    public function processSubscriptionPayment(
        mixed $subscription,
        mixed $plan,
        array $data,
    ): array
    {
        return [
            'success' => true,
            'subscription_id' => 'sub_null_test',
            'message' => 'Null processor — no real charge made.',
        ];
    }
}