<?php

namespace App\Services\Subscriptions\ValueObjects;

/**
 * UpgradeQuote Value Object
 *
 * Represents a calculated upgrade quote with all relevant pricing information.
 * For Stripe subscriptions, this is an ESTIMATE - actual charges may differ
 * based on Stripe's internal proration calculation.
 */
class UpgradeQuote
{
    private Money $amount;
    private bool $isProrated;
    private ?int $remainingDays;
    private bool $isEstimate;

    /**
     * @param Money $amount The calculated upgrade amount
     * @param bool $isProrated Whether proration was applied
     * @param int|null $remainingDays Remaining days in billing period (if applicable)
     * @param bool $isEstimate Whether this is an estimate (true for Stripe subscriptions)
     */
    public function __construct(
        Money $amount,
        bool  $isProrated,
        ?int  $remainingDays,
        bool  $isEstimate = true
    )
    {
        $this->amount = $amount;
        $this->isProrated = $isProrated;
        $this->remainingDays = $remainingDays;
        $this->isEstimate = $isEstimate;
    }

    /**
     * Get the calculated upgrade amount
     */
    public function getAmount(): Money
    {
        return $this->amount;
    }

    /**
     * Check if proration was applied to this quote
     */
    public function isProrated(): bool
    {
        return $this->isProrated;
    }

    /**
     * Get remaining days in billing period (if applicable)
     */
    public function getRemainingDays(): ?int
    {
        return $this->remainingDays;
    }

    /**
     * Check if this is an estimate
     *
     * Returns true for Stripe subscriptions where actual charge
     * may differ from calculated amount due to Stripe's proration.
     */
    public function isEstimate(): bool
    {
        return $this->isEstimate;
    }

    /**
     * Get estimate disclaimer text
     */
    public function getEstimateDisclaimer(): ?string
    {
        return $this->isEstimate
            ? 'Final charge may differ based on payment provider proration calculation'
            : null;
    }

    /**
     * Convert to array representation for API responses
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount->toDecimal(),
            'amount_in_cents' => $this->amount->toCents(),
            'currency' => $this->amount->getCurrency(),
            'is_prorated' => $this->isProrated,
            'remaining_days' => $this->remainingDays,
            'is_estimate' => $this->isEstimate,
            'estimate_disclaimer' => $this->getEstimateDisclaimer(),
        ];
    }

    /**
     * Create a summary message for display
     */
    public function getSummaryMessage(): string
    {
        $message = "Upgrade charge: {$this->amount->format()}";

        if ($this->isProrated && $this->remainingDays !== null) {
            $message .= " (prorated for {$this->remainingDays} remaining days)";
        }

        if ($this->isEstimate) {
            $message .= " - estimated amount";
        }

        return $message;
    }
}