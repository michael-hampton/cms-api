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

    /**
     * Standard consumer cancellation entitlement: allowed by default,
     * with no manager-approval step — either can be overridden per site
     * via SubscriptionPolicySettingOverrideService.
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
     * Standard consumer pause entitlement: one pause per subscription
     * term by default (per ticket example). See
     * SubscriptionTermCalculator::pausesUsedThisTerm() for how
     * $context->pausesUsedThisTerm is derived and its limitations. All
     * three gates (allowed, per-term limit, manager approval) can be
     * overridden per site.
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