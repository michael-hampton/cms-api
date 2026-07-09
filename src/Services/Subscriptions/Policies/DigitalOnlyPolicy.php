<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Policies;

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
}
