<?php

namespace App\Exceptions\Subscriptions;

class InactiveSubscriptionException extends SubscriptionException
{
    protected int $statusCode = 400;

    public function __construct(string $message = 'Subscription is not active', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}