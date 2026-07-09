<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Policies;

use App\DTO\Subscriptions\PolicyContext;
use App\DTO\Subscriptions\PolicyEvaluationResult;
use App\Enums\Subscriptions\ReplacementLimitScope;

/**
 * Internal-only policy used when an authorised business override is
 * performed. Never assigned to a subscription plan directly — resolved
 * explicitly via ReplacementPolicyResolver::resolveGoodwill() when the
 * plan's own policy denies a request and the agent supplied an override.
 *
 * Always allows both replacement and extension. Still runs through the
 * normal validate()/evaluate() path (rather than being special-cased in
 * IssueResolutionService) so an inactive/misconfigured goodwill row still
 * fails safe.
 */
class GoodwillPolicy extends AbstractReplacementPolicy
{
    public function evaluate(PolicyContext $context): PolicyEvaluationResult
    {
        return PolicyEvaluationResult::allowed();
    }

    public function replacementLimitScope(): ReplacementLimitScope
    {
        return ReplacementLimitScope::PER_ISSUE;
    }

    public function extensionLimitScope(): ReplacementLimitScope
    {
        return ReplacementLimitScope::PER_SUBSCRIPTION;
    }
}
