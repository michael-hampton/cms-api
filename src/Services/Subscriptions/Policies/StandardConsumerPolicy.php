<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Policies;

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
}
