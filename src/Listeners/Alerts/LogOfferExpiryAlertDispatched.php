<?php

declare(strict_types=1);

namespace App\Listeners\Alerts;

use App\Events\Alerts\OfferExpiryAlertDispatched;
use Psr\Log\LoggerInterface;

/**
 * Writes an audit log entry each time an expiry alert batch completes.
 *
 * This is the sole listener for OfferExpiryAlertDispatched.
 * Mail sending is NOT done here — it is done synchronously in the service
 * before the event is fired, so the event represents a completed fact.
 */
final class LogOfferExpiryAlertDispatched
{
    public function __construct(
        private readonly LoggerInterface $logger,
    )
    {
    }

    public function handle(OfferExpiryAlertDispatched $event): void
    {
        $this->logger->info('Offer expiry alert dispatched', [
            'entity_type' => $event->entityType->value,
            'entity_id' => $event->entityId,
            'threshold_hours' => $event->threshold->value,
            'merchant_alerts_sent' => $event->merchantAlertsSent,
            'member_alerts_sent' => $event->memberAlertsSent,
        ]);
    }
}