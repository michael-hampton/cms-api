<?php

declare(strict_types=1);

namespace App\Enums\Subscriptions;

enum ReplacementResolution: string
{
    case REPLACE = 'replace';
    case EXTEND = 'extend';

    public static function fromRequest(string $value): self
    {
        $normalised = strtolower(trim($value));

        foreach (self::cases() as $case) {
            if ($case->value === $normalised) {
                return $case;
            }
        }

        throw new \InvalidArgumentException("decision must be 'replace' or 'extend'.");
    }
}
