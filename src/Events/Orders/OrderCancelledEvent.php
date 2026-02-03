<?php

namespace App\Events\Orders;

use App\Models\Order;

class OrderCancelledEvent
{
    public function __construct(
        public readonly Order $order
    )
    {
    }
}