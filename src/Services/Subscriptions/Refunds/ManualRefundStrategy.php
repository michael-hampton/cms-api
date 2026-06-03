<?php

namespace App\Services\Subscriptions\Refunds;

use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;
use Exception;

class ManualRefundStrategy implements RefundStrategy
{
    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly float             $overrideAmount,
        private readonly string            $reason = 'manual_override',
    )
    {
    }

    public function calculate(Subscription $subscription): RefundResult
    {
        $payment = $this->payments->getLastSubscriptionPayment($subscription->id);

        if (!$payment) {
            throw new Exception('No payment found for refund');
        }

        if ($this->overrideAmount <= 0) {
            throw new Exception('Refund amount must be greater than zero');
        }

        if ($this->overrideAmount > $payment->amount) {
            throw new Exception('Refund amount cannot exceed original payment');
        }

        return new RefundResult(
            amount: $this->overrideAmount,
            type: 'manual',
            meta: [
                'original_payment_id' => $payment->id,
                'original_amount' => $payment->amount,
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
