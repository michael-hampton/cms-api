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
 * No replacements or extensions permitted under any circumstance.
 *
 * Used for promotional, trial, and complimentary plans, and as the
 * system-wide default (site default policy), so a missing/misconfigured
 * plan assignment fails safe rather than granting free replacements.
 */
class NoReplacementPolicy extends AbstractReplacementPolicy
{
    private const PAUSE_ALLOWED = false;
    private const PAUSE_LIMIT_PER_TERM = 0;
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
        return PolicyEvaluationResult::denied(match ($context->requestedResolution) {
            ReplacementResolution::REPLACE => 'This plan does not allow issue replacements.',
            ReplacementResolution::EXTEND => 'This plan does not allow subscription extensions.',
        });
    }

    // Scope is never consulted (evaluate() always denies before usage
    // stats would matter), so any value is inert. PER_ISSUE kept as a
    // harmless default.
    public function replacementLimitScope(): ReplacementLimitScope
    {
        return ReplacementLimitScope::PER_ISSUE;
    }

    public function extensionLimitScope(): ReplacementLimitScope
    {
        return ReplacementLimitScope::PER_ISSUE;
    }

    /**
     * NAMING NOTE: the ticket's testing strategy names this tier
     * "PromotionalConsumerPolicy". This class (NoReplacementPolicy) is
     * already used in this codebase for promotional/trial/complimentary
     * plans (see its class docblock) and as the system-wide default, so
     * cancellation/pause behaviour for "Promotional" is implemented here
     * rather than introducing a duplicate class.
     *
     * Cancellation is always allowed by default — a customer on a free/
     * promotional/trial plan being unable to cancel would be a dark
     * pattern, and nothing in the ticket suggests restricting it. Still
     * overridable per site if a specific promotional programme needs a
     * different rule.
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
     * Promotional pause: not permitted by default, per the ticket's
     * example — overridable per site (e.g. a goodwill exception for one
     * promotional account) the same as every other tier.
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