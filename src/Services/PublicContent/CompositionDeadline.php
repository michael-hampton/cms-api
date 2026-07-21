<?php

namespace App\Services\PublicContent;

use App\Services\Resilience\OperationContext;
use Closure;

/**
 * Soft deadline for non-critical composition work.
 *
 * Critical path (page load, access, render) always runs. Secondary sources
 * (recirculation, etc.) check remaining budget and degrade to typed-empty
 * instead of blowing the request past its resilience deadline.
 */
final class CompositionDeadline
{
    /** @param Closure(): int $remainingMilliseconds */
    public function __construct(
        private readonly Closure $remainingMilliseconds,
    ) {
    }

    public static function fromContext(OperationContext $context): self
    {
        return new self(static fn(): int => $context->remainingMilliseconds());
    }

    public static function unlimited(): self
    {
        return new self(static fn(): int => PHP_INT_MAX);
    }

    public function remainingMilliseconds(): int
    {
        return max(0, ($this->remainingMilliseconds)());
    }

    public function hasBudget(int $requiredMilliseconds): bool
    {
        return $this->remainingMilliseconds() >= $requiredMilliseconds;
    }
}
