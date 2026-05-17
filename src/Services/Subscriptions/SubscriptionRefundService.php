<?php

namespace App\Services\Subscriptions;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Subscription;
use App\Repositories\Billing\PaymentRepository;
use App\Services\Billing\Stripe\Contracts\StripeRefundGatewayInterface;
use App\Services\Subscriptions\Refunds\RefundResult;
use App\Services\Subscriptions\Refunds\RefundStrategy;
use App\Services\Subscriptions\Refunds\FullRefundStrategy;
use App\Services\Subscriptions\Refunds\ProRatedRefundStrategy;
use Exception;

/**
 * Orchestrates subscription refunds.
 *
 * Uses StripeRefundGateway for provider I/O — no direct Stripe SDK calls here.
 * Strategy pattern keeps refund calculation separate from persistence.
 */
class SubscriptionRefundService
{
    public function __construct(
        private readonly PaymentRepository        $paymentRepository,
        private readonly StripeRefundGatewayInterface $refundGateway,
        private readonly Database                 $database,
    ) {}

    public function createFullRefund(
        Subscription $subscription,
        string       $reason = 'customer_request',
    ): array {
        $strategy = new FullRefundStrategy($this->paymentRepository, $reason);
        return $this->executeRefund($subscription, $strategy);
    }

    public function createProRatedRefund(
        Subscription $subscription,
        string       $reason = 'early_cancellation',
    ): array {
        $strategy = new ProRatedRefundStrategy($this->paymentRepository, $reason);

        $preview = $strategy->calculate($subscription);

        if ($preview->noRefundDue) {
            return [
                'success'     => false,
                'message'     => $preview->meta['reason'],
                'unused_days' => 0,
            ];
        }

        return $this->executeRefund($subscription, $strategy);
    }

    public function executeWithStrategy(
        Subscription   $subscription,
        RefundStrategy $strategy,
    ): array {
        return $this->executeRefund($subscription, $strategy);
    }

    // ── Internal ─────────────────────────────────────────────────────────────

    private function executeRefund(Subscription $subscription, RefundStrategy $strategy): array
    {
        return $this->database->transaction(function () use ($subscription, $strategy) {
            $result = $strategy->calculate($subscription);
            return $this->persistRefund($subscription, $result);
        });
    }

    private function persistRefund(Subscription $subscription, RefundResult $result): array
    {
        $transactionId  = $result->meta['transaction_id']   ?? null;
        $paymentMethod  = $result->meta['payment_method']   ?? 'stripe';
        $paymentProvider = $result->meta['payment_provider'] ?? 'stripe';

        $providerRefund = null;

        if ($subscription->hasStripeSubscription() && $transactionId) {
            $refundOptions = ['reason' => $result->meta['reason'] ?? 'customer_request'];

            if ($result->type === 'pro_rated') {
                $refundOptions['metadata'] = [
                    'unused_days' => $result->meta['unused_days'],
                    'total_days'  => $result->meta['total_days'],
                ];
            }

            $providerRefund = $this->refundGateway->refund(
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
            default     => $result->type,
        };

        $auditMeta = [
            'refund_type'          => $refundType,
            'original_payment_id'  => $result->meta['original_payment_id'] ?? null,
            'reason'               => $result->meta['reason'] ?? null,
            'provider_refund'      => $providerRefund !== null,
            'strategy'             => $result->type,
            'final_amount'         => $result->amount,
            'override_amount'      => $result->type === 'manual' ? $result->amount : null,
            'calculated_amount'    => $result->meta['original_amount'] ?? $result->amount,
        ];

        if ($result->type === 'pro_rated') {
            $auditMeta['unused_days'] = $result->meta['unused_days'];
            $auditMeta['total_days']  = $result->meta['total_days'];
        }

        $refundPayment = $this->paymentRepository->create([
            'subscription_id' => $subscription->id,
            'site_id'         => $subscription->site_id,
            'payment_method'  => $paymentMethod,
            'payment_provider'=> $paymentProvider,
            'amount'          => -$result->amount,
            'currency'        => $subscription->currency,
            'status'          => 'completed',
            'paid_at'         => date('Y-m-d H:i:s'),
            'transaction_id'  => $providerRefund['refund_id'] ?? null,
            'metadata'        => $auditMeta,
        ]);

        Logger::info('Refund processed', [
            'subscription_id'    => $subscription->id,
            'refund_payment_id'  => $refundPayment->id,
            'strategy'           => $result->type,
            'amount'             => $result->amount,
            'provider_refund_id' => $providerRefund['refund_id'] ?? null,
        ]);

        $response = [
            'success'        => true,
            'refund_payment' => $refundPayment,
            'amount'         => $result->amount,
            'provider_refund'=> $providerRefund,
        ];

        if ($result->type === 'pro_rated') {
            $response['unused_days'] = $result->meta['unused_days'];
            $response['total_days']  = $result->meta['total_days'];
        }

        return $response;
    }
}