<?php

namespace App\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use Exception;

class SubscriptionRefundService
{
    public function __construct(
        private readonly PaymentRepository      $paymentRepository,
        private readonly StripePaymentProcessor $stripeProcessor,
        private readonly Database               $database
    )
    {
    }

    /**
     * Create a full refund for a subscription
     */
    public function createFullRefund(
        Subscription $subscription,
        string       $reason = 'customer_request'
    ): array
    {
        return $this->database->transaction(function () use ($subscription, $reason) {
            $lastPayment = $this->paymentRepository->getLastSubscriptionPayment($subscription->id);

            if (!$lastPayment) {
                throw new Exception('No payment found for refund');
            }

            // Issue refund with payment provider
            $providerRefund = null;
            if ($subscription->hasStripeSubscription() && $lastPayment->transaction_id) {
                $providerRefund = $this->stripeProcessor->refund(
                    $lastPayment->transaction_id,
                    $lastPayment->amount,
                    ['reason' => $reason]
                );

                if (!$providerRefund['success']) {
                    throw new Exception('Provider refund failed: ' . ($providerRefund['message'] ?? 'Unknown error'));
                }
            }

            // Create local refund payment record
            $refundPayment = $this->paymentRepository->create([
                'subscription_id' => $subscription->id,
                'site_id' => $subscription->site_id,
                'payment_method' => $lastPayment->payment_method,
                'payment_provider' => $lastPayment->payment_provider,
                'amount' => -$lastPayment->amount,
                'currency' => $subscription->currency,
                'status' => 'completed',
                'paid_at' => now_datetime()->format('Y-m-d H:i:s'),
                'transaction_id' => $providerRefund['refund_id'] ?? null,
                'metadata' => [
                    'refund_type' => 'full',
                    'original_payment_id' => $lastPayment->id,
                    'reason' => $reason,
                    'provider_refund' => $providerRefund !== null
                ]
            ]);

            Logger::info('Full refund created', [
                'subscription_id' => $subscription->id,
                'refund_payment_id' => $refundPayment->id,
                'amount' => $lastPayment->amount,
                'provider_refund_id' => $providerRefund['refund_id'] ?? null
            ]);

            return [
                'success' => true,
                'refund_payment' => $refundPayment,
                'amount' => $lastPayment->amount,
                'provider_refund' => $providerRefund
            ];
        });
    }

    /**
     * Create a pro-rated refund based on unused time
     */
    public function createProRatedRefund(
        Subscription $subscription,
        string       $reason = 'early_cancellation'
    ): array
    {
        if (!$subscription->end_date || !$subscription->last_payment_date) {
            throw new Exception('Cannot calculate pro-rated refund: missing dates');
        }

        $now = new \DateTime();
        $endDate = $subscription->end_date;
        $lastPayment = $subscription->last_payment_date;

        // Calculate unused days
        $totalDays = $lastPayment->diff($endDate)->days;
        $usedDays = $lastPayment->diff($now)->days;
        $unusedDays = max(0, $totalDays - $usedDays);

        if ($unusedDays <= 0) {
            return [
                'success' => false,
                'message' => 'No unused time remaining',
                'unused_days' => 0
            ];
        }

        // Calculate refund amount
        $refundAmount = ($subscription->price / $totalDays) * $unusedDays;

        // Create payment record for refund
        $lastCompletedPayment = $this->paymentRepository->getLastSubscriptionPayment($subscription->id);

        if (!$lastCompletedPayment) {
            throw new Exception('No payment found for refund');
        }

        // Issue refund with payment provider
        $providerRefund = null;
        if ($subscription->hasStripeSubscription() && $lastCompletedPayment->transaction_id) {
            $providerRefund = $this->stripeProcessor->refund(
                $lastCompletedPayment->transaction_id,
                $refundAmount,
                [
                    'reason' => $reason,
                    'metadata' => [
                        'unused_days' => $unusedDays,
                        'total_days' => $totalDays
                    ]
                ]
            );

            if (!$providerRefund['success']) {
                throw new Exception('Provider refund failed: ' . ($providerRefund['message'] ?? 'Unknown error'));
            }
        }

        // Create local refund payment record
        $refundPayment = $this->paymentRepository->create([
            'subscription_id' => $subscription->id,
            'site_id' => $subscription->site_id,
            'payment_method' => 'stripe',
            'payment_provider' => 'stripe',
            'amount' => -$refundAmount, // Negative for refund
            'currency' => $subscription->currency,
            'status' => 'completed',
            'paid_at' => date('Y-m-d H:i:s'),
            'metadata' => [
                'refund_type' => 'pro_rated_cancellation',
                'original_payment_id' => $lastCompletedPayment->id,
                'unused_days' => $unusedDays,
                'total_days' => $totalDays,
                'reason' => $reason,
                'provider_refund' => $providerRefund !== null
            ]
        ]);

        Logger::info('Pro-rated refund created', [
            'subscription_id' => $subscription->id,
            'refund_payment_id' => $refundPayment->id,
            'refund_amount' => $refundAmount,
            'unused_days' => $unusedDays,
            'provider_refund_id' => $providerRefund['refund_id'] ?? null
        ]);

        return [
            'success' => true,
            'refund_payment' => $refundPayment,
            'amount' => $refundAmount,
            'unused_days' => $unusedDays,
            'total_days' => $totalDays,
            'provider_refund' => $providerRefund
        ];
    }
}