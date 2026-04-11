<?php

namespace App\Events\OpenCollab;

use App\Models\Payout;

/**
 * Fired when an admin marks a payout as paid.
 * Listeners: notify contributor, write activity event.
 */
class PayoutProcessedEvent
{
    public function __construct(
        public readonly Payout $payout,
        public readonly int    $adminId,
    )
    {
    }
}