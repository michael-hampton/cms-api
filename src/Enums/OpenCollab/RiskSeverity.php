<?php

namespace App\Enums\OpenCollab;

enum RiskSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    /**
     * Contribution to a queue entry's risk_score (Ticket 9).
     */
    public function score(): int
    {
        return match ($this) {
            self::Low => 10,
            self::Medium => 30,
            self::High => 60,
            self::Critical => 100,
        };
    }

    public function isBlocking(): bool
    {
        return in_array($this, [self::High, self::Critical], true);
    }
}