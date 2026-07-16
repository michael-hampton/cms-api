<?php

namespace App\Enums\Billing;

/**
 * Lifecycle status of a member's saved (Stripe) payment method.
 *
 * This is the single source of truth for expiry-based status. It replaces
 * the magic strings ('expired' / 'expiring') that used to live inline in
 * StripePaymentMethodWarningService, and is the value the API contract
 * returns to both the PressStack account area and the site-scoped member
 * area so neither frontend has to re-derive the business rule itself.
 */
enum PaymentMethodStatus: string
{
    case Active = 'active';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::ExpiringSoon => 'Expiring soon',
            self::Expired => 'Expired',
        };
    }

    public function requiresUpdateAction(): bool
    {
        return $this === self::ExpiringSoon || $this === self::Expired;
    }
}
