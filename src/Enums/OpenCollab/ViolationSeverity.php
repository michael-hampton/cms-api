<?php

namespace App\Enums\OpenCollab;

enum ViolationSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    /**
     * Number of unresolved violations of this severity within the rolling
     * window before automatic suspension is triggered.
     */
    public function suspensionThreshold(): int
    {
        return match ($this) {
            self::Low => 5,
            self::Medium => 3,
            self::High => 1,
        };
    }
}