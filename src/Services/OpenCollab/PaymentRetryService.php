<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\PaymentStatus;
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
 *   - If the Stripe status is requires_payment_method or requires_confirmation,
 *     the intent is live and can be confirmed with a new card.
 *   - attempt_count is only incremented on actual failure (via recordFailure),
 *     NOT when a retry is initiated — to avoid double-counting.
 *   - Critical path — throws and does NOT silently continue on failure.
 */
class PaymentRetryService
{
    private const MAX_RETRIES = 3;

    private const RETRYABLE_INTENT_STATUSES = [
        'requires_payment_method',
        'requires_confirmation',
    ];

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
     * Does NOT increment attempt_count — that only happens on actual failure
     * in recordFailure() to avoid double-counting.
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

        if (!in_array($intent->status, self::RETRYABLE_INTENT_STATUSES, true)) {
            throw new \DomainException(
                "Cannot retry: payment intent is in status [{$intent->status}]."
            );
        }

        // Reset status to pending inside a transaction so a DB failure
        // doesn't leave the record in an inconsistent state.
        // attempt_count is NOT incremented here — only on actual failure.
        $this->database->transaction(function () use ($payment): void {
            // Re-fetch inside transaction to guard against concurrent retries.
            $fresh = $this->paymentRepository->find($payment->id);

            if ($fresh->hasReachedMaxRetries()) {
                throw new \DomainException(
                    'Maximum retry attempts (' . self::MAX_RETRIES . ') reached. Please contact support.'
                );
            }

            $this->paymentRepository->update($payment->id, [
                'last_attempt_at' => date('Y-m-d H:i:s'),
                'status' => PaymentStatus::Pending->value,
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
     * This is the single place where attempt_count is incremented.
     */
    public function recordFailure(string $paymentIntentId, ?string $failureReason = null): void
    {
        $payment = $this->paymentRepository->findByPaymentIntentId($paymentIntentId);

        if (!$payment) {
            return; // non-critical — already handled by ArticleAccessService
        }

        $this->paymentRepository->update($payment->id, [
            'status' => PaymentStatus::Failed->value,
            'failure_reason' => $failureReason,
            'attempt_count' => ((int)($payment->attempt_count ?? 0)) + 1,
            'last_attempt_at' => date('Y-m-d H:i:s'),
        ]);
    }
}