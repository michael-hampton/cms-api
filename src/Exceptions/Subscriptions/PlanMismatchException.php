<?php

namespace App\Exceptions\Subscriptions;

class PlanMismatchException extends SubscriptionException
{
    protected int $statusCode = 400;

    public function __construct(string $message = 'Upgrade plan does not match current subscription', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}