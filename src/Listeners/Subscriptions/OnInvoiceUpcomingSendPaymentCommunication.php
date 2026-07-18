<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Enums\Subscriptions\PaymentCommunicationEventType;
use App\Events\Subscriptions\InvoiceUpcoming;
use App\Framework\Support\Logger;
use App\Services\Subscriptions\Communications\PaymentCommunicationDispatchService;

/**
 * Renewal intent-to-debit letter for members without an email address.
 * Non-critical: a missed advance notice must never block webhook processing.
 */
final class OnInvoiceUpcomingSendPaymentCommunication
{
    public function __construct(
        private readonly PaymentCommunicationDispatchService $dispatcher,
        private readonly Logger $logger,
    ) {
    }

    public function handle(InvoiceUpcoming $event): void
    {
        try {
            $this->dispatcher->dispatch(
                eventType: PaymentCommunicationEventType::RENEWAL_INTENT_TO_DEBIT,
                subscription: $event->subscription,
                metadata: [
                    'amount_due' => $event->amountDue,
                    'currency' => $event->currency,
                ],
            );
        } catch (\Throwable $e) {
            $this->logger->error('OnInvoiceUpcomingSendPaymentCommunication: dispatch failed', [
                'subscription_id' => $event->subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
