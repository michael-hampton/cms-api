<?php

namespace App\Events\Orders;

use App\Models\Order;

class OrderUpdatedEvent
{
    public function __construct(
        public readonly Order $order
    )
    {
    }
}