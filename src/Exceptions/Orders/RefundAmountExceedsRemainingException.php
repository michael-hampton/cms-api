<?php

namespace App\Exceptions\Orders;

use Exception;

class RefundAmountExceedsRemainingException extends Exception
{
    public static function create(float $requestedAmount, float $availableAmount): self
    {
        return new self(
            "Refund amount {$requestedAmount} exceeds remaining order total. Available: {$availableAmount}"
        );
    }
}