<?php

namespace App\Enums\Member;

enum DuplicateMemberMatchType: string
{
    case Email           = 'email';
    case Phone           = 'phone';
    case StripeCustomer  = 'stripe_customer_id';
    case NamePostcode    = 'name_postcode';

    public function label(): string
    {
        return match ($this) {
            self::Email          => 'Normalised email match',
            self::Phone          => 'Phone number match',
            self::StripeCustomer => 'Stripe customer ID match',
            self::NamePostcode   => 'Name and postcode match',
        };
    }

    /**
     * Fixed confidence scores.
     *
     * Email and Stripe customer matches are high-confidence (exact identifiers).
     * Phone is medium-high (can be shared by households).
     * Name + postcode is lower-confidence (common names, shared addresses).
     */
    public function confidenceScore(): int
    {
        return match ($this) {
            self::Email          => 95,
            self::StripeCustomer => 95,
            self::Phone          => 85,
            self::NamePostcode   => 60,
        };
    }
}