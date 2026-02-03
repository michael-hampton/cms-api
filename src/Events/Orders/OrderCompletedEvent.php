<?php

namespace App\Events\Orders;

use App\Models\Order;

class OrderCompletedEvent
{
    public function __construct(
        public readonly Order $order
    )
    {
    }
}