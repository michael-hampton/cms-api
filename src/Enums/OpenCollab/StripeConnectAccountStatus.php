<?php

declare(strict_types=1);

namespace App\Enums\OpenCollab;

enum StripeConnectAccountStatus: string
{
    case Disconnected        = 'disconnected';
    case Incomplete          = 'incomplete';
    case VerificationPending = 'verification_pending';
    case Restricted          = 'restricted';
    case Enabled             = 'enabled';

    /**
     * Derive the status from raw Stripe account fields.
     *
     * Rules (evaluated in order):
     *   1. No account record at all               → Disconnected
     *   2. details_submitted = false              → Incomplete
     *   3. payouts_enabled = false                → Restricted
     *   4. requirements_due is non-empty          → VerificationPending
     *   5. payouts_enabled AND details_submitted
     *      AND no requirements due                → Enabled
     */
    public static function fromAccountFields(
        bool  $connected,
        bool  $detailsSubmitted,
        bool  $payoutsEnabled,
        array $requirementsDue,
    ): self {
        if (!$connected) {
            return self::Disconnected;
        }

        if (!$detailsSubmitted) {
            return self::Incomplete;
        }

        if (!$payoutsEnabled) {
            return self::Restricted;
        }

        if (!empty($requirementsDue)) {
            return self::VerificationPending;
        }

        return self::Enabled;
    }

    /**
     * Whether this status satisfies the KYC-ready check:
     * payouts enabled, details submitted, no requirements due.
     */
    public function isKycReady(): bool
    {
        return $this === self::Enabled;
    }

    /**
     * Whether this status should invalidate (or block) the kyc_verification
     * onboarding step. Any status other than Enabled blocks KYC.
     */
    public function blocksKyc(): bool
    {
        return $this !== self::Enabled;
    }
}