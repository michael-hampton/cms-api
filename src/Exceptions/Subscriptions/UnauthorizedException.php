<?php

namespace App\Exceptions\Subscriptions;

class UnauthorizedException extends SubscriptionException
{
    protected int $statusCode = 403;

    public function __construct(string $message = 'Unauthorized', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}