<?php

namespace App\Events\Orders;

use App\Models\Order;

class OrderCreatedEvent
{
    public function __construct(
        public readonly Order   $order,
        public readonly ?string $customerEmail = null
    )
    {
    }
}