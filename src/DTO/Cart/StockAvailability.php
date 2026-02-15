<?php

namespace App\DTO\Cart;

use DateTimeImmutable;

/**
 * Represents stock availability for a product or variant.
 *
 * This value object handles:
 * - Regular in-stock items
 * - Out-of-stock items
 * - Preorder items (available < 0, with expected date)
 * - Backorder items (allow negative)
 * - Unlimited stock (available = null)
 *
 * Future extensions:
 * - Reserved stock
 * - Estimated stock ranges
 * - Supplier availability
 */
readonly class StockAvailability
{
    /**
     * @param int|null $available Null = unlimited, >= 0 = in stock, < 0 = preorder/backorder
     * @param bool $isPreorder True if this is a preorder item
     * @param DateTimeImmutable|null $expectedShipDate Expected ship date for preorders
     * @param bool $allowBackorder True if negatives are allowed until checkout
     */
    public function __construct(
        public ?int               $available,
        public bool               $isPreorder = false,
        public ?DateTimeImmutable $expectedShipDate = null,
        public bool               $allowBackorder = false,
    )
    {
    }

    /**
     * Factory: Create from standard stock quantity.
     */
    public static function fromStockQuantity(?int $stockQuantity): self
    {
        return new self(
            available: $stockQuantity,
            isPreorder: false,
            expectedShipDate: null,
            allowBackorder: false,
        );
    }

    /**
     * Factory: Create for preorder items.
     */
    public static function forPreorder(
        int               $availableQuantity,
        DateTimeImmutable $expectedShipDate
    ): self
    {
        return new self(
            available: $availableQuantity,
            isPreorder: true,
            expectedShipDate: $expectedShipDate,
            allowBackorder: false,
        );
    }

    /**
     * Factory: Create for unlimited stock.
     */
    public static function unlimited(): self
    {
        return new self(
            available: null,
            isPreorder: false,
            expectedShipDate: null,
            allowBackorder: false,
        );
    }

    /**
     * Check if sufficient quantity is available.
     */
    public function hasSufficientStock(int $requestedQuantity): bool
    {
        // Unlimited stock always sufficient
        if ($this->isUnlimited()) {
            return true;
        }

        // Backorder allowed - always sufficient
        if ($this->allowBackorder) {
            return true;
        }

        // Check actual availability
        return $this->available >= $requestedQuantity;
    }

    /**
     * Check if stock is unlimited.
     */
    public function isUnlimited(): bool
    {
        return $this->available === null;
    }

    /**
     * Get remaining stock after deducting quantity.
     */
    public function afterDeducting(int $quantity): self
    {
        // Unlimited remains unlimited
        if ($this->isUnlimited()) {
            return $this;
        }

        return new self(
            available: $this->available - $quantity,
            isPreorder: $this->isPreorder,
            expectedShipDate: $this->expectedShipDate,
            allowBackorder: $this->allowBackorder,
        );
    }
}