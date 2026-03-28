<?php

declare(strict_types=1);

namespace App\Events\Subscriptions;

use App\Models\PrintFulfillment;

/**
 * Fired after a PrintFulfillment record is persisted and its outer
 * transaction has committed.
 *
 * Currently unused by any listener — reserved for future side effects
 * such as supplier notification or fulfilment analytics.
 *
 * Rule: every event must have at least one listener before shipping.
 * Wire this up or remove it when the use case is confirmed.
 */
final class PrintFulfillmentCreated
{
    public function __construct(
        public readonly PrintFulfillment $fulfillment,
    )
    {
    }
}