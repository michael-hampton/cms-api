<?php

namespace App\Exceptions\Checkout;

use RuntimeException;

class CheckoutException extends RuntimeException
{
    public function __construct(
        string                 $message,
        private readonly array $context = [],
        int                    $code = 0,
        ?\Throwable            $previous = null,
    )
    {
        parent::__construct($message, $code, $previous);
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function hasContext(string $key): bool
    {
        return array_key_exists($key, $this->context);
    }
}