<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Policies;

use App\DTO\Subscriptions\CancellationPolicyContext;
use App\DTO\Subscriptions\PausePolicyContext;
use App\DTO\Subscriptions\PolicyContext;
use App\DTO\Subscriptions\PolicyEvaluationResult;
use App\Enums\Subscriptions\ReplacementLimitScope;
use App\Enums\Subscriptions\ReplacementResolution;

/**
 * Digital-only entitlement: no physical replacements (there's no physical
 * item to replace), extensions permitted within a limit.
 *
 * ASSUMPTION: extension limit is a placeholder, same caveat as
 * StandardConsumerPolicy — confirm the real number with product.
 */
class DigitalOnlyPolicy extends AbstractReplacementPolicy
{
    private const MAX_EXTENSIONS = 3;
    private const EXTENSION_SCOPE = ReplacementLimitScope::PER_SUBSCRIPTION;

    public function evaluate(PolicyContext $context): PolicyEvaluationResult
    {
        if ($context->requestedResolution === ReplacementResolution::REPLACE) {
            return PolicyEvaluationResult::denied('This plan does not support physical replacements.');
        }

        if ($this->limitReached(self::MAX_EXTENSIONS, $context->usageStatistics->extensionsUsed)) {
            return PolicyEvaluationResult::businessOverrideRequired(
                'The extension limit for this plan has been reached.'
            );
        }

        return PolicyEvaluationResult::allowed();
    }

    // Never consulted (replacement is always denied); inert default.
    public function replacementLimitScope(): ReplacementLimitScope
    {
        return ReplacementLimitScope::PER_ISSUE;
    }

    public function extensionLimitScope(): ReplacementLimitScope
    {
        return self::EXTENSION_SCOPE;
    }

    /**
     * ASSUMPTION: digital-only isn't one of the ticket's four named
     * tiers (Standard/Premium/Corporate/Promotional). Cancellation and
     * pause here mirror StandardConsumerPolicy's rules (always-allowed
     * cancellation, one pause per term) as the closest analogue — a
     * digital-only consumer plan, not a promotional/complimentary one.
     * Confirm with product if digital-only should instead follow a
     * different tier's pause rule.
     */
    public function evaluateCancellation(CancellationPolicyContext $context): PolicyEvaluationResult
    {
        return PolicyEvaluationResult::allowed();
    }

    public function evaluatePause(PausePolicyContext $context): PolicyEvaluationResult
    {
        if ($context->pausesUsedThisTerm >= 1) {
            return PolicyEvaluationResult::denied(
                'This plan allows one pause per subscription term, which has already been used.'
            );
        }

        return PolicyEvaluationResult::allowed();
    }
}
