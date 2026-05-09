<?php

namespace App\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;
use App\Services\Billing\PaymentProviders\StripePaymentProcessor;
use App\Services\Subscriptions\Refunds\FullRefundStrategy;
use App\Services\Subscriptions\Refunds\ProRatedRefundStrategy;
use App\Services\Subscriptions\Refunds\RefundResult;
use App\Services\Subscriptions\Refunds\RefundStrategy;
use Exception;

class SubscriptionRefundService
{
    public function __construct(
        private readonly PaymentRepository      $paymentRepository,
        private readonly StripePaymentProcessor $stripeProcessor,
        private readonly Database $database,
    )
    {
    }

    /**
     * Create a full refund for a subscription.
     * Public signature is stable — delegates to FullRefundStrategy internally.
     */
    public function createFullRefund(
        Subscription $subscription,
        string $reason = 'customer_request',
    ): array
    {
        $strategy = new FullRefundStrategy($this->paymentRepository, $reason);
        return $this->executeRefund($subscription, $strategy);
    }

    /**
     * Create a pro-rated refund based on unused subscription time.
     * Public signature is stable — delegates to ProRatedRefundStrategy internally.
     *
     * Date guards fire before any transaction is opened; zero-refund
     * scenarios exit cleanly without touching the database or provider.
     */
    public function createProRatedRefund(
        Subscription $subscription,
        string $reason = 'early_cancellation',
    ): array
    {
        $strategy = new ProRatedRefundStrategy($this->paymentRepository, $reason);

        // Pre-calculate outside the transaction so we can exit early for
        // no-refund-due without opening (and immediately rolling back) a
        // transaction. The strategy is stateless so recalculating inside
        // the transaction is safe and cheap.
        $preview = $strategy->calculate($subscription);

        if ($preview->noRefundDue) {
            return [
                'success' => false,
                'message' => $preview->meta['reason'],
                'unused_days' => 0,
            ];
        }

        return $this->executeRefund($subscription, $strategy);
    }

    /**
     * Execute a refund using an explicitly resolved strategy.
     * Entry point for SubscriptionCancellationService when it needs to apply
     * a strategy it has already selected (e.g. ManualRefundStrategy).
     */
    public function executeWithStrategy(
        Subscription   $subscription,
        RefundStrategy $strategy,
    ): array
    {
        return $this->executeRefund($subscription, $strategy);
    }

    // -------------------------------------------------------------------------
    // Internal execution
    // -------------------------------------------------------------------------

    private function executeRefund(
        Subscription   $subscription,
        RefundStrategy $strategy,
    ): array
    {
        return $this->database->transaction(function () use ($subscription, $strategy) {
            $result = $strategy->calculate($subscription);
            return $this->persistRefund($subscription, $result);
        });
    }

    /**
     * Issue the provider refund (when applicable) and write the local payment
     * record. This is the only location that performs I/O — no strategy touches
     * this layer directly.
     *
     * All values come from scalar fields in RefundResult::meta. No ORM objects
     * are read from meta here; the strategy is responsible for populating the
     * required scalar fields.
     */
    private function persistRefund(Subscription $subscription, RefundResult $result): array
    {
        $transactionId = $result->meta['transaction_id'] ?? null;
        $paymentMethod = $result->meta['payment_method'] ?? 'stripe';
        $paymentProvider = $result->meta['payment_provider'] ?? 'stripe';

        $providerRefund = null;

        if ($subscription->hasStripeSubscription() && $transactionId) {
            $refundOptions = ['reason' => $result->meta['reason'] ?? 'customer_request'];

            if ($result->type === 'pro_rated') {
                $refundOptions['metadata'] = [
                    'unused_days' => $result->meta['unused_days'],
                    'total_days' => $result->meta['total_days'],
                ];
            }

            $providerRefund = $this->stripeProcessor->refund(
                $transactionId,
                $result->amount,
                $refundOptions,
            );

            if (!$providerRefund['success']) {
                throw new Exception(
                    'Provider refund failed: ' . ($providerRefund['message'] ?? 'Unknown error')
                );
            }
        }

        $refundType = match ($result->type) {
            'pro_rated' => 'pro_rated_cancellation',
            default => $result->type,
        };

        $auditMeta = [
            'refund_type' => $refundType,
            'original_payment_id' => $result->meta['original_payment_id'] ?? null,
            'reason' => $result->meta['reason'] ?? null,
            'provider_refund' => $providerRefund !== null,
            'strategy' => $result->type,
            'final_amount' => $result->amount,
            'override_amount' => $result->type === 'manual' ? $result->amount : null,
            'calculated_amount' => $result->meta['original_amount'] ?? $result->amount,
        ];

        if ($result->type === 'pro_rated') {
            $auditMeta['unused_days'] = $result->meta['unused_days'];
            $auditMeta['total_days'] = $result->meta['total_days'];
        }

        $refundPayment = $this->paymentRepository->create([
            'subscription_id' => $subscription->id,
            'site_id' => $subscription->site_id,
            'payment_method' => $paymentMethod,
            'payment_provider' => $paymentProvider,
            'amount' => -$result->amount,
            'currency' => $subscription->currency,
            'status' => 'completed',
            'paid_at' => date('Y-m-d H:i:s'),
            'transaction_id' => $providerRefund['refund_id'] ?? null,
            'metadata' => $auditMeta,
        ]);

        Logger::info('Refund processed', [
            'subscription_id' => $subscription->id,
            'refund_payment_id' => $refundPayment->id,
            'strategy' => $result->type,
            'calculated_amount' => $result->meta['original_amount'] ?? $result->amount,
            'final_amount' => $result->amount,
            'override_amount' => $result->type === 'manual' ? $result->amount : null,
            'provider_refund_id' => $providerRefund['refund_id'] ?? null,
        ]);

        $response = [
            'success' => true,
            'refund_payment' => $refundPayment,
            'amount' => $result->amount,
            'provider_refund' => $providerRefund,
        ];

        if ($result->type === 'pro_rated') {
            $response['unused_days'] = $result->meta['unused_days'];
            $response['total_days'] = $result->meta['total_days'];
        }

        return $response;
    }
}