<?php

namespace App\Enums\OpenCollab;

/**
 * Canonical registry for onboarding step keys and their workflow statuses.
 *
     * STEP KEYS — the only place step names are defined.
     *   Use OnboardingStepStatus::STEPS to get the ordered list.
     *   Never hard-code the strings 'profile', 'contract', etc. elsewhere.
 *
 * STATUSES — stored as plain strings in contributor_onboarding_steps.status.
 *   pending     — not yet started, or reset after invalidation
 *   in_progress — user has started but not completed
 *   completed   — explicitly completed AND domain validation passed at time of completion
 *   invalidated — was completed but an upstream change (new contract, new guidelines
 *                 version, payment revoked) means the user must re-complete it
 *
 * Completion rule:
 *   A step is only treated as currently complete when:
 *     1. the site requires that step
 *     2. the contributor_onboarding_steps row status is 'completed'
 *     3. domain validation still passes at runtime
 *
 *   If (3) fails even though the row says 'completed', the step is treated as
 *   pending and the row should be invalidated on next sync.
 */
enum OnboardingStepStatus: string
{
    case Pending     = 'pending';
    case InProgress  = 'in_progress';
    case Completed   = 'completed';
    case Invalidated = 'invalidated';

    // ── Step keys ─────────────────────────────────────────────────────────────

    /** All valid step keys, in canonical display order. */
    public const STEPS = [
        'profile',
        'payment_setup',
        'kyc_verification',
        'contract',
        'guidelines',
        'age_verification',
    ];

    /**
     * Steps that are invalidated when a new contract is published for a site.
     *
     * @return string[]
     */
    public static function stepsInvalidatedByNewContract(): array
    {
        return ['contract'];
    }

    /**
     * Steps that are invalidated when new guidelines are published for a site.
     *
     * @return string[]
     */
    public static function stepsInvalidatedByNewGuidelines(): array
    {
        return ['guidelines'];
    }

    /**
     * Steps that are invalidated when payment details are revoked or disabled.
     *
     * @return string[]
     */
    public static function stepsInvalidatedByPaymentRevoked(): array
    {
        return ['payment_setup', 'kyc_verification'];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isComplete(): bool
    {
        return $this === self::Completed;
    }

    public function isBlockingCompletion(): bool
    {
        return $this === self::Pending
            || $this === self::InProgress
            || $this === self::Invalidated;
    }
}
