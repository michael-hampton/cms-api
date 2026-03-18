<?php

declare(strict_types=1);

namespace App\Events\Alerts;

use App\Enums\Alerts\AlertableEntityType;
use App\Enums\Alerts\ExpiryAlertThreshold;

/**
 * Fired once per (entity, threshold) pair after the alert mails have been
 * sent and the tracking row has been recorded.
 *
 * Listener: LogOfferExpiryAlertDispatched
 *
 * Intentionally thin — carries only the identifiers needed for
 * audit logging. The listener must not re-send mails.
 */
final class OfferExpiryAlertDispatched
{
    public function __construct(
        public readonly AlertableEntityType  $entityType,
        public readonly int                  $entityId,
        public readonly ExpiryAlertThreshold $threshold,
        public readonly int                  $merchantAlertsSent,
        public readonly int                  $memberAlertsSent,
    )
    {
    }
}