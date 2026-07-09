<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Policies;

use App\DTO\Subscriptions\PolicyContext;
use App\DTO\Subscriptions\PolicyEvaluationResult;
use App\Enums\Subscriptions\ReplacementLimitScope;

/**
 * Premium entitlement for Silver/Gold-tier plans: replacements and
 * extensions always allowed, no manager approval required.
 *
 * ASSUMPTION: "Premium entitlement rules" is not otherwise specified by
 * the ticket. Implemented as unlimited (no cap) since that's the
 * distinguishing feature versus StandardConsumerPolicy. If Premium is
 * meant to have a (larger) cap rather than be unlimited, that's a
 * one-line change to add MAX_REPLACEMENTS/MAX_EXTENSIONS constants
 * mirroring StandardConsumerPolicy.
 */
class PremiumPolicy extends AbstractReplacementPolicy
{
    public function evaluate(PolicyContext $context): PolicyEvaluationResult
    {
        return PolicyEvaluationResult::allowed();
    }

    // Scope is inert while limits are unlimited, but declared for when a
    // cap is introduced.
    public function replacementLimitScope(): ReplacementLimitScope
    {
        return ReplacementLimitScope::PER_YEAR;
    }

    public function extensionLimitScope(): ReplacementLimitScope
    {
        return ReplacementLimitScope::PER_YEAR;
    }
}
