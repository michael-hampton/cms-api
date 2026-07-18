<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Enums\Subscriptions\PaymentCommunicationEventType;
use App\Events\Subscriptions\InvoicePaymentFailed;
use App\Framework\Support\Logger;
use App\Services\Subscriptions\Communications\PaymentCommunicationDispatchService;

/**
 * Adds the letter-only payment-failed notice alongside the existing
 * OnInvoicePaymentFailed listener (grace-period email). That listener's
 * email path is untouched; this only covers members without an email.
 *
 * Non-critical: a missed letter must never block webhook processing or the
 * subscription's PAST_DUE transition already committed upstream.
 */
final class OnInvoicePaymentFailedSendLetter
{
    public function __construct(
        private readonly PaymentCommunicationDispatchService $dispatcher,
        private readonly Logger $logger,
    ) {
    }

    public function handle(InvoicePaymentFailed $event): void
    {
        try {
            $this->dispatcher->dispatch(
                eventType: PaymentCommunicationEventType::PAYMENT_FAILED,
                subscription: $event->subscription,
                metadata: [
                    'failure_reason' => $event->failureReason,
                    'failure_code' => $event->failureCode,
                ],
            );
        } catch (\Throwable $e) {
            $this->logger->error('OnInvoicePaymentFailedSendLetter: dispatch failed', [
                'subscription_id' => $event->subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
