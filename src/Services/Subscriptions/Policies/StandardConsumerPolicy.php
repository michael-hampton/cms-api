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
 * Standard consumer entitlement for Bronze/Standard-tier plans: bounded
 * replacements and extensions, no manager approval required.
 *
 * ASSUMPTION: the ticket doesn't specify concrete limits or the counting
 * window — these numbers are a starting point, not a business decision
 * already made. Confirm with product before shipping.
 */
class StandardConsumerPolicy extends AbstractReplacementPolicy
{
    private const MAX_REPLACEMENTS = 2;
    private const MAX_EXTENSIONS = 2;
    private const REPLACEMENT_SCOPE = ReplacementLimitScope::PER_SUBSCRIPTION;
    private const EXTENSION_SCOPE = ReplacementLimitScope::PER_SUBSCRIPTION;

    public function evaluate(PolicyContext $context): PolicyEvaluationResult
    {
        return match ($context->requestedResolution) {
            ReplacementResolution::REPLACE => $this->evaluateReplacement($context),
            ReplacementResolution::EXTEND => $this->evaluateExtension($context),
        };
    }

    private function evaluateReplacement(PolicyContext $context): PolicyEvaluationResult
    {
        if ($this->limitReached(self::MAX_REPLACEMENTS, $context->usageStatistics->replacementsUsed)) {
            return PolicyEvaluationResult::businessOverrideRequired(
                'The replacement limit for this plan has been reached.'
            );
        }

        return PolicyEvaluationResult::allowed();
    }

    private function evaluateExtension(PolicyContext $context): PolicyEvaluationResult
    {
        if ($this->limitReached(self::MAX_EXTENSIONS, $context->usageStatistics->extensionsUsed)) {
            return PolicyEvaluationResult::businessOverrideRequired(
                'The extension limit for this plan has been reached.'
            );
        }

        return PolicyEvaluationResult::allowed();
    }

    public function replacementLimitScope(): ReplacementLimitScope
    {
        return self::REPLACEMENT_SCOPE;
    }

    public function extensionLimitScope(): ReplacementLimitScope
    {
        return self::EXTENSION_SCOPE;
    }

    /**
     * Standard consumer cancellation entitlement: always allowed. Nothing
     * in the ticket suggests standard-tier customers need a block or
     * review step to cancel.
     */
    public function evaluateCancellation(CancellationPolicyContext $context): PolicyEvaluationResult
    {
        return PolicyEvaluationResult::allowed();
    }

    /**
     * Standard consumer pause entitlement: one pause per subscription
     * term (per ticket example). See
     * SubscriptionTermCalculator::pausesUsedThisTerm() for how
     * $context->pausesUsedThisTerm is derived and its limitations.
     */
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
