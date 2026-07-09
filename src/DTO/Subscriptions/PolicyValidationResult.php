<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions;

/**
 * Result of ReplacementPolicyInterface::validate() — is the policy itself
 * usable for this request (active, supports the requested resolution
 * type, has the configuration it needs)? This is deliberately separate
 * from entitlement (PolicyEvaluationResult) per the ticket: validate()
 * must not determine entitlement.
 */
final class PolicyValidationResult
{
    private function __construct(
        public readonly bool $valid,
        public readonly ?string $reason = null,
    ) {
    }

    public static function valid(): self
    {
        return new self(true);
    }

    public static function invalid(string $reason): self
    {
        return new self(false, $reason);
    }
}
