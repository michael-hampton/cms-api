<?php

namespace App\Exceptions\Orders;

use Exception;

class RefundAlreadyProcessedException extends Exception
{
    public static function forId(int $refundId): self
    {
        return new self("Refund {$refundId} has already been processed");
    }
}