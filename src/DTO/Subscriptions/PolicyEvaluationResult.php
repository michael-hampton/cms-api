<?php

declare(strict_types=1);

namespace App\DTO\Subscriptions;

use App\Enums\Subscriptions\PolicyEvaluationOutcome;

/**
 * Result of ReplacementPolicyInterface::evaluate() — does the policy
 * entitle the requested replacement/extension right now?
 *
 * See PolicyEvaluationOutcome for how the three non-ALLOWED outcomes are
 * currently treated identically by IssueResolutionService.
 */
final class PolicyEvaluationResult
{
    private function __construct(
        public readonly PolicyEvaluationOutcome $outcome,
        public readonly ?string $blockedReason = null,
    ) {
    }

    public static function allowed(): self
    {
        return new self(PolicyEvaluationOutcome::ALLOWED);
    }

    public static function denied(string $reason): self
    {
        return new self(PolicyEvaluationOutcome::DENIED, $reason);
    }

    public static function requiresManagerApproval(string $reason): self
    {
        return new self(PolicyEvaluationOutcome::REQUIRES_MANAGER_APPROVAL, $reason);
    }

    public static function businessOverrideRequired(string $reason): self
    {
        return new self(PolicyEvaluationOutcome::BUSINESS_OVERRIDE_REQUIRED, $reason);
    }

    public function isAllowed(): bool
    {
        return $this->outcome->isGranted();
    }
}
