<?php

namespace App\Exceptions\Payments;

use RuntimeException;

/**
 * Thrown when the payment gateway rejects or fails a refund request.
 *
 * This is a critical-flow exception — callers must NOT silently catch it.
 * Wrapping the original Throwable preserves the full Stripe error for logging.
 */
class RefundGatewayException extends RuntimeException
{
    public static function fromStripeError(string $message, \Throwable $previous): self
    {
        return new self('Stripe refund failed: ' . $message, 0, $previous);
    }
}