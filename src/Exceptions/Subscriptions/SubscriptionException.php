<?php

namespace App\Exceptions\Subscriptions;

use Exception;

/**
 * Base exception for all subscription-related errors
 */
abstract class SubscriptionException extends Exception
{
    protected int $statusCode = 400;

    /**
     * Get HTTP status code for this exception
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get error context for logging
     */
    public function getContext(): array
    {
        return [
            'exception' => static::class,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }
}