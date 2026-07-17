<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Policies;

use App\DTO\Subscriptions\CancellationPolicyContext;
use App\DTO\Subscriptions\PausePolicyContext;
use App\DTO\Subscriptions\PolicyContext;
use App\DTO\Subscriptions\PolicyEvaluationResult;
use App\Enums\Subscriptions\PolicySettingKey;
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
    private const PAUSE_ALLOWED = true;
    private const PAUSE_LIMIT_PER_TERM = null; // unlimited by default
    private const PAUSE_REQUIRES_MANAGER_APPROVAL = false;
    private const CANCELLATION_ALLOWED = true;
    private const CANCELLATION_REQUIRES_MANAGER_APPROVAL = false;

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

    /**
     * NAMING NOTE: the ticket's testing strategy refers to this tier as
     * "PremiumConsumerPolicy". This class is the existing Premium/Gold
     * policy in the codebase (PremiumPolicy) — treating it as the same
     * tier rather than introducing a duplicate class, since the ticket
     * doesn't describe any behaviour distinguishing a separate
     * "PremiumConsumerPolicy" from the existing PremiumPolicy.
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
     * Premium pause entitlement: unlimited pauses by default (per ticket
     * example) — overridable per site the same as every other tier.
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
                'Pausing this plan requires manager approval.'
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