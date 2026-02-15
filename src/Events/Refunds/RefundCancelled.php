<?php

namespace App\Events\Refunds;

use App\Models\Refund;

class RefundCancelled
{
    public function __construct(
        public readonly Refund $refund,
        public readonly ?int   $cancelledBy
    )
    {
    }
}