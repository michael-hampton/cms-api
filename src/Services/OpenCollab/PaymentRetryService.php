<?php

namespace App\Services\OpenCollab;

use App\Framework\Database\Database;
use App\Repositories\OpenCollab\ArticlePaymentRepository;
use App\Services\Billing\PaymentProviders\PaymentIntentGateway;

/**
 * Handles retrying failed article payments.
 *
 * Design decisions:
 *   - Single table (oc_article_payments) — no separate failed_payments table.
 *   - attempt_count tracks retries; max = 3.
 *   - Retry returns the existing client_secret so the frontend re-runs
 *     Stripe Elements without creating a new PaymentIntent.
 *   - If the Stripe status is requires_payment_method, the intent is live and
 *     can be confirmed with a new card.
 *   - Critical path — throws and does NOT silently continue on failure.
 */
class PaymentRetryService
{
    private const MAX_RETRIES = 3;

    public function __construct(
        private readonly ArticlePaymentRepository $paymentRepository,
        private readonly PaymentIntentGateway     $paymentIntentGateway,
        private readonly Database                 $database,
    )
    {
    }

    /**
     * Prepare a retry for the given payment record.
     *
     * Returns the Stripe client_secret so the frontend can re-confirm.
     *
     * @throws \InvalidArgumentException if the payment is not found or not retryable
     * @throws \DomainException          if the max retry limit has been reached
     * @throws \DomainException          if Stripe reports the intent is not in a retryable state
     */
    public function retry(int $paymentId, int $userId): array
    {
        $payment = $this->paymentRepository->find($paymentId);

        if (!$payment || (int)$payment->user_id !== $userId) {
            throw new \InvalidArgumentException("Payment [{$paymentId}] not found.");
        }

        if (!$payment->hasFailed()) {
            throw new \InvalidArgumentException(
                "Payment [{$paymentId}] is not in a failed state (status: {$payment->status})."
            );
        }

        if ($payment->hasReachedMaxRetries()) {
            throw new \DomainException(
                'Maximum retry attempts (' . self::MAX_RETRIES . ') reached. Please contact support.'
            );
        }

        // Confirm with Stripe that the intent is in a retryable state.
        $intent = $this->paymentIntentGateway->retrieve($payment->stripe_payment_intent_id);

        if ($intent->status !== 'requires_payment_method') {
            throw new \DomainException(
                "Cannot retry: payment intent is in status [{$intent->status}]."
            );
        }

        // Increment attempt counter inside a transaction so a DB failure
        // doesn't leave the counter out of sync.
        $this->database->transaction(function () use ($payment): void {
            $this->paymentRepository->update($payment->id, [
                'attempt_count' => ((int)($payment->attempt_count ?? 0)) + 1,
                'last_attempt_at' => date('Y-m-d H:i:s'),
                'status' => 'pending', // reset to pending so webhook can flip to succeeded/failed
            ]);
        });

        return [
            'client_secret' => $intent->client_secret,
            'payment_id' => $payment->id,
        ];
    }

    /**
     * Called by the Stripe webhook handler on payment_intent.payment_failed.
     * Records failure reason and increments attempt_count.
     */
    public function recordFailure(string $paymentIntentId, ?string $failureReason = null): void
    {
        $payment = $this->paymentRepository->findByPaymentIntentId($paymentIntentId);

        if (!$payment) {
            return; // non-critical — already handled by ArticleAccessService
        }

        $this->paymentRepository->update($payment->id, [
            'status' => 'failed',
            'failure_reason' => $failureReason,
            'attempt_count' => ((int)($payment->attempt_count ?? 0)) + 1,
            'last_attempt_at' => date('Y-m-d H:i:s'),
        ]);
    }
}