<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions;

/**
 * Explicit result of PaymentCommunicationEligibilityResolver so callers (and
 * logs) can see *why* a letter was or wasn't sent, rather than a bare bool.
 */
final class PaymentCommunicationEligibilityResult
{
    private function __construct(
        public readonly bool $eligible,
        public readonly ?string $reason,
    ) {
    }

    public static function eligible(): self
    {
        return new self(true, null);
    }

    public static function skipped(string $reason): self
    {
        return new self(false, $reason);
    }
}
