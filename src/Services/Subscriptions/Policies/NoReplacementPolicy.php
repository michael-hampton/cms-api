<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Policies;

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
}
