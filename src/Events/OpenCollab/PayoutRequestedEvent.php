<?php

namespace App\Events\OpenCollab;

use App\Models\Payout;

/**
 * Fired when a contributor requests a payout.
 * Listeners: notify admins.
 */
class PayoutRequestedEvent
{
    public function __construct(
        public readonly Payout $payout,
        public readonly int    $contributorId,
    )
    {
    }
}