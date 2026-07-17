<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Policies;

use App\DTO\Subscriptions\CancellationPolicyContext;
use App\DTO\Subscriptions\PausePolicyContext;
use App\DTO\Subscriptions\PolicyContext;
use App\DTO\Subscriptions\PolicyEvaluationResult;
use App\Enums\Subscriptions\PolicySettingKey;
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
    private const PAUSE_ALLOWED = true;
    private const PAUSE_LIMIT_PER_TERM = null; // unlimited by default
    private const PAUSE_REQUIRES_MANAGER_APPROVAL = true;
    private const CANCELLATION_ALLOWED = true;
    private const CANCELLATION_REQUIRES_MANAGER_APPROVAL = true;

    public static function overridableSettings(): array
    {
        return [
            PolicySettingKey::PAUSE_ALLOWED->value => self::PAUSE_ALLOWED,
            PolicySettingKey::PAUSE_LIMIT_PER_TERM->value => self::PAUSE_LIMIT_PER_TERM,
            PolicySettingKey::PAUSE_REQUIRES_MANAGER_APPROVAL->value => self::PAUSE_REQUIRES_MANAGER_APPROVAL,
            PolicySettingKey::CANCELLATION_ALLOWED->value => self::CANCELLATION_ALLOWED,
            PolicySettingKey::CANCELLATION_REQUIRES_MANAGER_APPROVAL->value => self::CANCELLATION_REQUIRES_MANAGER_APPROVAL,
        ];
    }

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
     * requires manager approval by default, so the outcome is
     * distinguishable in code/audit records. As with evaluate() above,
     * the manager-approval workflow itself is out of scope for this
     * ticket — SubscriptionCancellationService currently treats any
     * non-ALLOWED outcome as blocking, so this denies the self-service
     * cancellation pending that future workflow. Per-site admins can
     * override any of pause_allowed/cancellation_allowed/the
     * manager-approval gates — e.g. to let a specific corporate account
     * self-serve cancel despite the contract default.
     */
    public function evaluateCancellation(CancellationPolicyContext $context): PolicyEvaluationResult
    {
        $allowed = (bool) $context->settingOverrides->get(PolicySettingKey::CANCELLATION_ALLOWED, self::CANCELLATION_ALLOWED);

        if (!$allowed) {
            return PolicyEvaluationResult::denied('Cancellation is not permitted on this plan.');
        }

        $requiresApproval = (bool) $context->settingOverrides->get(
            PolicySettingKey::CANCELLATION_REQUIRES_MANAGER_APPROVAL,
            self::CANCELLATION_REQUIRES_MANAGER_APPROVAL
        );

        if ($requiresApproval) {
            return PolicyEvaluationResult::requiresManagerApproval(
                'This plan requires manager approval before a cancellation can be processed.'
            );
        }

        return PolicyEvaluationResult::allowed();
    }

    /**
     * Corporate pause: "subject to contractual agreement" per the ticket
     * — requires manager approval by default, same reasoning as
     * cancellation above, since no automated way to check an individual
     * contract's terms exists in this codebase. Overridable per site for
     * accounts whose contract already permits self-service pausing.
     */
    public function evaluatePause(PausePolicyContext $context): PolicyEvaluationResult
    {
        $allowed = (bool) $context->settingOverrides->get(PolicySettingKey::PAUSE_ALLOWED, self::PAUSE_ALLOWED);

        if (!$allowed) {
            return PolicyEvaluationResult::denied('Pausing is not available on this plan.');
        }

        $requiresApproval = (bool) $context->settingOverrides->get(
            PolicySettingKey::PAUSE_REQUIRES_MANAGER_APPROVAL,
            self::PAUSE_REQUIRES_MANAGER_APPROVAL
        );

        if ($requiresApproval) {
            return PolicyEvaluationResult::requiresManagerApproval(
                'Pausing this plan is subject to the account\'s contractual agreement and requires manager approval.'
            );
        }

        /** @var int|null $limit null means unlimited */
        $limit = $context->settingOverrides->get(PolicySettingKey::PAUSE_LIMIT_PER_TERM, self::PAUSE_LIMIT_PER_TERM);

        if ($limit !== null && $context->pausesUsedThisTerm >= $limit) {
            return PolicyEvaluationResult::denied(
                "This plan allows {$limit} pause(s) per subscription term, which has already been used."
            );
        }

        return PolicyEvaluationResult::allowed();
    }
}