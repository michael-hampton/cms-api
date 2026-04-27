<?php

namespace App\Events\OpenCollab;

use App\Models\Payout;

class PayoutFailedEvent
{
    public function __construct(
        public readonly Payout $payout,
        public readonly int    $adminId,
        public readonly int    $userId,
        public readonly string $reason = ''
    )
    {
    }
}