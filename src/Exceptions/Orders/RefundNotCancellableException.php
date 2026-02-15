<?php

namespace App\Exceptions\Orders;

use Exception;

class RefundNotCancellableException extends Exception
{
    public static function forStatus(string $status): self
    {
        return new self("Only pending refunds can be cancelled. Current status: {$status}");
    }
}