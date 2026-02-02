<?php

namespace App\Exceptions\Subscriptions;

class SubscriptionNotFoundException extends SubscriptionException
{
    protected int $statusCode = 404;

    public function __construct(string $message = 'Subscription not found', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}