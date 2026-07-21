<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\InvoicePaymentSucceeded;
use App\Framework\Support\Logger;
use App\Services\Subscriptions\FulfilmentSuspensionService;

/**
 * Releases any fulfilments suspended by a prior payment failure once a
 * payment succeeds again — the counterpart to
 * OnInvoicePaymentFailedSuspendFulfilments.
 *
 * A no-op when the subscription has no suspended fulfilments (release()
 * only ever touches SUSPENDED, undispatched rows), so it is safe to run on
 * every successful invoice payment, not only recovery ones.
 *
 * Failure contract:
 *   Non-critical relative to the payment itself — catch and log.
 */
class OnInvoicePaymentSucceededReleaseFulfilments
{
    public function __construct(
        private readonly FulfilmentSuspensionService $fulfilmentSuspensionService,
        private readonly Logger $logger,
    ) {
    }

    public function handle(InvoicePaymentSucceeded $event): void
    {
        try {
            $this->fulfilmentSuspensionService->release($event->subscription);
        } catch (\Throwable $e) {
            $this->logger->error('OnInvoicePaymentSucceededReleaseFulfilments: failed to release fulfilments', [
                'subscription_id' => $event->subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
