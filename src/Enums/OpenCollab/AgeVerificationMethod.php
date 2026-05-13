<?php

namespace App\Enums\OpenCollab;

/**
 * How the contributor's age was verified.
 *
 * The enum values intentionally form a trust hierarchy:
 *   self_declared → kyc_verified → manual_review
 *
 * Future KYC integration upgrades the method without schema changes.
 */
enum AgeVerificationMethod: string
{
    case SelfDeclared = 'self_declared';
    case KycVerified = 'kyc_verified';
    case ManualReview = 'manual_review';

    public function label(): string
    {
        return match ($this) {
            self::SelfDeclared => 'Self-declared',
            self::KycVerified => 'KYC verified',
            self::ManualReview => 'Manual review',
        };
    }

    /**
     * Returns true if this method is considered a strong verification.
     * Self-declared alone is not considered strong.
     */
    public function isStrong(): bool
    {
        return match ($this) {
            self::SelfDeclared => false,
            self::KycVerified => true,
            self::ManualReview => true,
        };
    }
}