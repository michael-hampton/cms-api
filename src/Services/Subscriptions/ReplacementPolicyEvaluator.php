<?php

declare(strict_types=1);

namespace App\Services\Subscriptions;

use App\DTO\Subscriptions\ReplacementEligibilityResult;
use App\DTO\Subscriptions\ReplacementUsageStatistics;
use App\Enums\Subscriptions\ReplacementResolution;
use App\Models\ReplacementPolicy;

/**
 * Answers a single question: does this policy entitle this decision
 * (replace/extend), given how much of it has already been used?
 *
 * Pure calculation — no repositories, no persistence, no side effects.
 * Deliberately does not know about stock, dispatch state, or duplicate
 * requests; those are operational constraints owned by
 * FulfilmentReplacementEligibilityService, not entitlement.
 *
 * Named broadly (not "...EligibilityEvaluator") because it's expected to
 * grow to cover other policy-driven decisions (fees, courier upgrades,
 * partial/digital replacement) without becoming misnamed.
 */
class ReplacementPolicyEvaluator
{
    public function evaluate(
        ReplacementPolicy $policy,
        ReplacementResolution $resolution,
        ReplacementUsageStatistics $usage,
    ): ReplacementEligibilityResult {
        return match ($resolution) {
            ReplacementResolution::REPLACE => $this->evaluateReplacement($policy, $usage),
            ReplacementResolution::EXTEND => $this->evaluateExtension($policy, $usage),
        };
    }

    private function evaluateReplacement(
        ReplacementPolicy $policy,
        ReplacementUsageStatistics $usage,
    ): ReplacementEligibilityResult {
        if (!$policy->allows_replacements) {
            return ReplacementEligibilityResult::denied(
                'This plan does not allow issue replacements.'
            );
        }

        if ($policy->max_replacements !== null && $usage->replacementsUsed >= $policy->max_replacements) {
            return ReplacementEligibilityResult::denied(
                'The replacement limit for this plan has been reached.'
            );
        }

        if ($policy->requires_manager_approval) {
            return ReplacementEligibilityResult::denied(
                'This plan requires manager approval before a replacement can be issued.'
            );
        }

        return ReplacementEligibilityResult::allowed();
    }

    private function evaluateExtension(
        ReplacementPolicy $policy,
        ReplacementUsageStatistics $usage,
    ): ReplacementEligibilityResult {
        if (!$policy->allows_extensions) {
            return ReplacementEligibilityResult::denied(
                'This plan does not allow subscription extensions.'
            );
        }

        if ($policy->max_extensions !== null && $usage->extensionsUsed >= $policy->max_extensions) {
            return ReplacementEligibilityResult::denied(
                'The extension limit for this plan has been reached.'
            );
        }

        if ($policy->requires_manager_approval) {
            return ReplacementEligibilityResult::denied(
                'This plan requires manager approval before an extension can be issued.'
            );
        }

        return ReplacementEligibilityResult::allowed();
    }
}