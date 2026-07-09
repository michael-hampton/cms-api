<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Policies;

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
}
