<?php

namespace App\Events\Orders;

use App\Models\Order;

class OrderRefundedEvent
{
    public function __construct(
        public readonly Order $order
    )
    {
    }
}