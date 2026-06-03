<?php

namespace App\Services\Subscriptions\Refunds;

use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;
use Exception;

class FullRefundStrategy implements RefundStrategy
{
    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly string            $reason = 'customer_request',
    )
    {
    }

    public function calculate(Subscription $subscription): RefundResult
    {
        $payment = $this->payments->getLastSubscriptionPayment($subscription->id);

        if (!$payment) {
            throw new Exception('No payment found for refund');
        }

        return new RefundResult(
            amount: $payment->amount,
            type: 'full',
            meta: [
                'original_payment_id' => $payment->id,
                'transaction_id' => $payment->transaction_id,
                'payment_intent_id' => $payment->payment_intent_id,
                'stripe_invoice_id' => $payment->stripe_invoice_id,
                'provider_transaction_id' => $payment->payment_intent_id ?: $payment->transaction_id,
                'payment_method' => $payment->payment_method,
                'payment_provider' => $payment->payment_provider,
                'reason' => $this->reason,
            ],
        );
    }
}
