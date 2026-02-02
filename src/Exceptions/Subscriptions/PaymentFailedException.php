<?php

namespace App\Exceptions\Subscriptions;

class PaymentFailedException extends SubscriptionException
{
    protected int $statusCode = 402;

    public function __construct(string $message = 'Payment failed', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}