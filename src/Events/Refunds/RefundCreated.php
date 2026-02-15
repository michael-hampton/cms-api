<?php

namespace App\Events\Refunds;

use App\Models\Order;
use App\Models\Refund;

class RefundCreated
{
    public function __construct(
        public readonly Refund $refund,
        public readonly Order  $order,
        public readonly string $reason
    )
    {
    }
}