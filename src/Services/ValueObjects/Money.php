<?php

namespace App\Services\ValueObjects;

use InvalidArgumentException;

/**
 * Money Value Object
 *
 * Handles monetary amounts in minor units (cents) to avoid floating-point precision issues.
 * All calculations are performed on integers representing cents.
 */
class Money
{
    private int $amountInCents;
    private string $currency;

    private function __construct(int $amountInCents, string $currency)
    {
        $this->amountInCents = $amountInCents;
        $this->currency = strtoupper($currency);
    }

    /**
     * Create Money from cents (minor units)
     *
     * @param int $cents Amount in cents (e.g., 1999 for $19.99)
     * @param string $currency Currency code (e.g., 'USD')
     */
    public static function fromCents(int $cents, string $currency): self
    {
        return new self($cents, $currency);
    }

    /**
     * Create Money from decimal amount
     *
     * @param float $amount Decimal amount (e.g., 19.99)
     * @param string $currency Currency code (e.g., 'USD')
     */
    public static function fromDecimal(float $amount, string $currency): self
    {
        // Round to nearest cent to avoid floating-point precision issues
        return new self((int)round($amount * 100), $currency);
    }

    /**
     * Get amount in cents (minor units)
     */
    public function toCents(): int
    {
        return $this->amountInCents;
    }

    /**
     * Get amount as decimal (major units)
     *
     * Note: Use this only for display or output. Never use for calculations.
     */
    public function toDecimal(): float
    {
        return $this->amountInCents / 100;
    }

    /**
     * Get currency code
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Subtract another Money amount
     *
     * @throws InvalidArgumentException if currencies don't match
     */
    public function subtract(Money $other): Money
    {
        $this->ensureSameCurrency($other);
        return new self($this->amountInCents - $other->amountInCents, $this->currency);
    }

    /**
     * Add another Money amount
     *
     * @throws InvalidArgumentException if currencies don't match
     */
    public function add(Money $other): Money
    {
        $this->ensureSameCurrency($other);
        return new self($this->amountInCents + $other->amountInCents, $this->currency);
    }

    /**
     * Multiply by a factor
     *
     * @param float $factor Multiplication factor
     */
    public function multiply(float $factor): Money
    {
        return new self((int)round($this->amountInCents * $factor), $this->currency);
    }

    /**
     * Divide by a divisor
     *
     * @param float $divisor Division factor
     * @throws InvalidArgumentException if divisor is zero
     */
    public function divide(float $divisor): Money
    {
        if ($divisor == 0) {
            throw new InvalidArgumentException('Cannot divide by zero');
        }
        return new self((int)round($this->amountInCents / $divisor), $this->currency);
    }

    /**
     * Check if amount is positive (> 0)
     */
    public function isPositive(): bool
    {
        return $this->amountInCents > 0;
    }

    /**
     * Check if amount is negative (< 0)
     */
    public function isNegative(): bool
    {
        return $this->amountInCents < 0;
    }

    public function ensureNonNegative(): self
    {
        if ($this->isNegative()) {
            return self::fromCents(0, $this->currency);
        }
        return $this;
    }

    /**
     * Check if amount is zero
     */
    public function isZero(): bool
    {
        return $this->amountInCents === 0;
    }

    private function ensureSameCurrency(Money $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException(
                "Cannot operate on different currencies: {$this->currency} vs {$other->currency}"
            );
        }
    }

    /**
     * Compare with another Money amount
     *
     * @return int -1 if less, 0 if equal, 1 if greater
     * @throws InvalidArgumentException if currencies don't match
     */
    public function compareTo(Money $other): int
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot compare {$this->currency} with {$other->currency}"
            );
        }

        if ($this->amountInCents < $other->amountInCents) {
            return -1;
        }
        if ($this->amountInCents > $other->amountInCents) {
            return 1;
        }
        return 0;
    }

    /**
     * Check if equal to another Money amount
     *
     * @throws InvalidArgumentException if currencies don't match
     */
    public function equals(Money $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    /**
     * Check if greater than another Money amount
     *
     * @throws InvalidArgumentException if currencies don't match
     */
    public function greaterThan(Money $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    /**
     * Check if less than another Money amount
     *
     * @throws InvalidArgumentException if currencies don't match
     */
    public function lessThan(Money $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    /**
     * Format as string for display
     */
    public function format(): string
    {
        $symbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
        ];

        $symbol = $symbols[$this->currency] ?? $this->currency . ' ';

        // Format with 2 decimal places
        return $symbol . number_format($this->toDecimal(), 2);
    }

    /**
     * Convert to array representation
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->toDecimal(),
            'cents' => $this->amountInCents,
            'currency' => $this->currency,
        ];
    }

    /**
     * String representation
     */
    public function __toString(): string
    {
        return $this->format();
    }

    public static function convertDollarsToCents(float $dollars): int
    {
        return (int)round($dollars * 100);
    }

    public static function convertCentsToDollars(int $cents): float
    {
        return $cents / 100;
    }
}