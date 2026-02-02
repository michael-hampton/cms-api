<?php

namespace App\Exceptions\Subscriptions;

class InvalidUpgradePlanException extends SubscriptionException
{
    protected int $statusCode = 400;

    public function __construct(string $message = 'Invalid upgrade plan', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}