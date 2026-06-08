<?php

namespace App\Enums\OpenCollab;

enum AccrualStatus: string
{
    case Estimated = 'estimated';
    case Confirmed = 'confirmed';
    case Settled   = 'settled';
    case Withdrawn = 'withdrawn';
    case Reversed  = 'reversed';

    /**
     * Returns the allowed next states from the current state.
     *
     * @return array<AccrualStatus>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Estimated => [self::Confirmed, self::Reversed],
            self::Confirmed => [self::Settled, self::Reversed],
            self::Settled   => [self::Withdrawn, self::Reversed],
            self::Withdrawn => [],  // Terminal — recovery via liabilities, not reversal
            self::Reversed  => [],  // Terminal
        };
    }

    /**
     * Returns true when the given transition is permitted from this state.
     */
    public function canTransitionTo(AccrualStatus $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Returns only the statuses whose earnings are payable (withdrawable).
     *
     * @return array<AccrualStatus>
     */
    public static function withdrawable(): array
    {
        return [self::Settled];
    }

    /**
     * Returns true when this status contributes to the withdrawable balance.
     */
    public function isWithdrawable(): bool
    {
        return in_array($this, self::withdrawable(), true);
    }

    /**
     * Returns statuses that represent active (non-terminal) earnings.
     *
     * @return array<AccrualStatus>
     */
    public static function active(): array
    {
        return [self::Estimated, self::Confirmed, self::Settled];
    }

    public static function activeValues(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::active()
        );
    }

    /**
     * Returns true when the earning is in a terminal state
     * (no further transitions are possible through normal flow).
     */
    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}