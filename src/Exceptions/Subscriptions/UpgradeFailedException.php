<?php

namespace App\Exceptions\Subscriptions;

class UpgradeFailedException extends SubscriptionException
{
    protected int $statusCode = 500;

    public function __construct(string $message = 'Failed to upgrade subscription', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}