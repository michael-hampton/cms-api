<?php

namespace App\Exceptions\Orders;

use Exception;

class RefundNotFoundException extends Exception
{
    public static function forId(int $refundId): self
    {
        return new self("Refund with ID {$refundId} not found");
    }
}