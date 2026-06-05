<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions;

final class ReplacementEligibilityResult
{
    public function __construct(
        public readonly bool $canRequestReplacement,
        public readonly ?string $blockedReason = null,
    ) {}

    public static function allowed(): self
    {
        return new self(true);
    }

    public static function denied(string $reason): self
    {
        return new self(false, $reason);
    }
}