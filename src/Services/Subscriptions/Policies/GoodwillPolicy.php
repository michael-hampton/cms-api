<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Policies;

use App\DTO\Subscriptions\CancellationPolicyContext;
use App\DTO\Subscriptions\PausePolicyContext;
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
    /**
     * Not admin-facing (see class docblock — this is only ever resolved
     * internally, never assigned to a plan), so it declares no
     * overridable settings. SubscriptionPolicySettingOverrideService
     * rejects any attempt to override a GoodwillPolicy setting as a
     * result.
     */
    public static function overridableSettings(): array
    {
        return [];
    }

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

    /**
     * ASSUMPTION: this ticket never resolves GoodwillPolicy for
     * cancellation/pause — it's only ever reached today via
     * ReplacementPolicyResolver::resolveGoodwill() from
     * IssueResolutionService's business-override path, which
     * SubscriptionCancellationService/SubscriptionPauseService don't call.
     * Implemented as always-allowed for interface completeness only,
     * consistent with this policy's existing "always allow" semantics for
     * replace/extend.
     */
    public function evaluateCancellation(CancellationPolicyContext $context): PolicyEvaluationResult
    {
        return PolicyEvaluationResult::allowed();
    }

    public function evaluatePause(PausePolicyContext $context): PolicyEvaluationResult
    {
        return PolicyEvaluationResult::allowed();
    }
}