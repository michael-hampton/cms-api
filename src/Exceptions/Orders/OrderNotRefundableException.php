<?php

namespace App\Exceptions\Orders;

use Exception;

class OrderNotRefundableException extends Exception
{
    public static function forOrder(int $orderId): self
    {
        return new self("Order {$orderId} cannot be refunded");
    }
}