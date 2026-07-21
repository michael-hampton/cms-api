<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\InvoicePaymentFailed;
use App\Framework\Support\Logger;
use App\Services\Subscriptions\FulfilmentSuspensionService;

/**
 * Suspends the subscription's pending fulfilments after a payment failure,
 * per the subscription's resolved FulfilmentSuspensionRule (immediate by
 * default, overridable per plan as a days/issues delay since the first
 * issue).
 *
 * Triggered by: InvoicePaymentFailed
 *
 * Explicitly NOT responsible for:
 *   - Setting subscription status to PAST_DUE (done by SubscriptionInvoiceHandler)
 *   - Member notification (done by OnInvoicePaymentFailed / OnInvoicePaymentFailedSendLetter)
 *
 * Failure contract:
 *   The payment-failure record and PAST_DUE status are already committed
 *   before this listener runs. A failure here must not roll that back or
 *   block sibling listeners on the same event — catch and log.
 */
class OnInvoicePaymentFailedSuspendFulfilments
{
    public function __construct(
        private readonly FulfilmentSuspensionService $fulfilmentSuspensionService,
        private readonly Logger $logger,
    ) {
    }

    public function handle(InvoicePaymentFailed $event): void
    {
        try {
            $this->fulfilmentSuspensionService->handleTrigger(
                $event->subscription,
                FulfilmentSuspensionService::REASON_PAYMENT_FAILED,
            );
        } catch (\Throwable $e) {
            $this->logger->error('OnInvoicePaymentFailedSuspendFulfilments: failed to suspend fulfilments', [
                'subscription_id' => $event->subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
