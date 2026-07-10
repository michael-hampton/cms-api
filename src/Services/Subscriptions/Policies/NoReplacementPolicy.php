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
 * No replacements or extensions permitted under any circumstance.
 *
 * Used for promotional, trial, and complimentary plans, and as the
 * system-wide default (site default policy), so a missing/misconfigured
 * plan assignment fails safe rather than granting free replacements.
 */
class NoReplacementPolicy extends AbstractReplacementPolicy
{
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
     * Cancellation is always allowed — a customer on a free/promotional/
     * trial plan being unable to cancel would be a dark pattern, and
     * nothing in the ticket suggests restricting it.
     */
    public function evaluateCancellation(CancellationPolicyContext $context): PolicyEvaluationResult
    {
        return PolicyEvaluationResult::allowed();
    }

    /**
     * Promotional pause: not permitted, per the ticket's example.
     */
    public function evaluatePause(PausePolicyContext $context): PolicyEvaluationResult
    {
        return PolicyEvaluationResult::denied(
            'Pausing is not available on this plan.'
        );
    }
}
