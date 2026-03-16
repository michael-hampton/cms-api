<?php

namespace App\Exceptions\Stock;

use RuntimeException;

/**
 * Thrown when a stock allocation cannot be completed.
 *
 * Critical flow — callers must allow this to propagate so the wrapping
 * transaction rolls back. Do NOT catch-and-swallow in services.
 */
class StockException extends RuntimeException
{
    public static function insufficientStock(string $itemName, int $available, int $requested): self
    {
        return new self(
            "Insufficient stock for '{$itemName}': requested {$requested}, available {$available}."
        );
    }

    public static function itemNotFound(string $context): self
    {
        return new self("Stock item not found: {$context}.");
    }
}