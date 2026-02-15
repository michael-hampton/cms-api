<?php

namespace App\Exceptions\Orders;

use Exception;

class OrderNotFoundException extends Exception
{
    public static function forId(int $orderId): self
    {
        return new self("Order with ID {$orderId} not found");
    }
}