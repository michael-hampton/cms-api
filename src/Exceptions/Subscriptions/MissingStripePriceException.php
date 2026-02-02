<?php

namespace App\Exceptions\Subscriptions;

class MissingStripePriceException extends SubscriptionException
{
    protected int $statusCode = 400;

    public function __construct(string $message = 'Stripe price ID is missing', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}