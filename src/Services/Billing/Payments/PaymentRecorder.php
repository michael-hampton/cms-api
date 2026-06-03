<?php

namespace App\Services\Billing\Payments;

use App\Models\Model;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Repositories\Billing\PaymentRepository;

class PaymentRecorder
{
    public function __construct(
        private readonly PaymentRepository $paymentRepository,
    )
    {
    }

    public function recordSubscriptionStripePayment(
        Subscription     $subscription,
        SubscriptionPlan $plan,
        array            $paymentData,
    ): Model
    {
        $amountCents = $paymentData['amount_cents']
            ?? $subscription->price_paid_cents
            ?? (int)round(((float)$subscription->price) * 100);

        $currency = strtoupper($subscription->currency ?: $plan->currency);

        return $this->paymentRepository->create([
            'subscription_id' => $subscription->id,
            'member_id' => $subscription->member_id,
            'site_id' => $subscription->site_id,
            'payment_method' => 'stripe',
            'payment_provider' => 'stripe',
            'transaction_id' => $paymentData['transaction_id'],
            'payment_intent_id' => $paymentData['payment_intent_id'],
            'stripe_invoice_id' => $paymentData['stripe_invoice_id'],
            'status' => $paymentData['status'],
            'amount' => $amountCents / 100,
            'currency' => $currency,
            'metadata' => [
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'billing_period' => $plan->billing_period,
                'stripe_subscription_id' => $paymentData['stripe_subscription_id'],
                'stripe_customer_id' => $paymentData['stripe_customer_id'],
                'invoice_tax_cents' => $paymentData['invoice_tax_cents'] ?? null,
                'invoice_tax' => isset($paymentData['invoice_tax_cents'])
                    ? $paymentData['invoice_tax_cents'] / 100
                    : null,
            ],
            'order_id' => $paymentData['order_id'] ?? null,
        ]);
    }

    public function markCompleted(Payment $payment): void
    {
        $this->paymentRepository->update($payment->id, [
            'status' => 'completed',
            'paid_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
