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

    private const PAUSE_ALLOWED = true;
    private const PAUSE_LIMIT_PER_TERM = 1;
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

    /**
     * ASSUMPTION: digital-only isn't one of the ticket's four named
     * tiers (Standard/Premium/Corporate/Promotional). Cancellation and
     * pause here mirror StandardConsumerPolicy's rules (always-allowed
     * cancellation, one pause per term) as the closest analogue — a
     * digital-only consumer plan, not a promotional/complimentary one.
     * Confirm with product if digital-only should instead follow a
     * different tier's pause rule. Both, along with the manager-approval
     * gates, can be overridden per site.
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