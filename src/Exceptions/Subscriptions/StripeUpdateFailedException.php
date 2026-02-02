<?php

namespace App\Exceptions\Subscriptions;

class StripeUpdateFailedException extends SubscriptionException
{
    protected int $statusCode = 500;

    public function __construct(string $message = 'Failed to update Stripe subscription', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}