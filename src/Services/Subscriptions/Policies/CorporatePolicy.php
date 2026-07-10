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
 * Corporate/institutional entitlement (Corporate, Schools, Libraries).
 *
 * Per the ticket, manager approval workflow is out of scope here — this
 * policy only returns REQUIRES_MANAGER_APPROVAL so the outcome is
 * distinguishable in code/audit records for that future work.
 * IssueResolutionService currently treats this the same as a denial: it
 * throws unless the agent supplies a business override, at which point
 * GoodwillPolicy is substituted (decision_source = BUSINESS_OVERRIDE, not
 * MANAGER_OVERRIDE — that source is reserved for when the approval
 * workflow actually exists).
 *
 * ASSUMPTION: "Return a manager approval required result when
 * applicable" is implemented as *always* applicable (every corporate
 * replacement/extension needs approval), since no conditional trigger is
 * specified. Revisit if approval should only kick in past some
 * threshold.
 */
class CorporatePolicy extends AbstractReplacementPolicy
{
    public function evaluate(PolicyContext $context): PolicyEvaluationResult
    {
        return PolicyEvaluationResult::requiresManagerApproval(match ($context->requestedResolution) {
            ReplacementResolution::REPLACE => 'This plan requires manager approval before a replacement can be issued.',
            ReplacementResolution::EXTEND => 'This plan requires manager approval before an extension can be issued.',
        });
    }

    public function replacementLimitScope(): ReplacementLimitScope
    {
        return ReplacementLimitScope::LIFETIME;
    }

    public function extensionLimitScope(): ReplacementLimitScope
    {
        return ReplacementLimitScope::LIFETIME;
    }

    /**
     * Corporate cancellation, same pattern as replace/extend above:
     * returns REQUIRES_MANAGER_APPROVAL rather than a hard denial, so the
     * outcome is distinguishable in code/audit records. As with
     * evaluate() above, the manager-approval workflow itself is out of
     * scope for this ticket — SubscriptionCancellationService currently
     * treats any non-ALLOWED outcome as blocking, so this denies the
     * self-service cancellation pending that future workflow.
     */
    public function evaluateCancellation(CancellationPolicyContext $context): PolicyEvaluationResult
    {
        return PolicyEvaluationResult::requiresManagerApproval(
            'This plan requires manager approval before a cancellation can be processed.'
        );
    }

    /**
     * Corporate pause: "subject to contractual agreement" per the ticket
     * — implemented as requiring manager approval, same reasoning as
     * cancellation above, since no automated way to check an individual
     * contract's terms exists in this codebase.
     */
    public function evaluatePause(PausePolicyContext $context): PolicyEvaluationResult
    {
        return PolicyEvaluationResult::requiresManagerApproval(
            'Pausing this plan is subject to the account\'s contractual agreement and requires manager approval.'
        );
    }
}
