<?php

namespace App\Services\Subscriptions\Calculators;

use App\Services\ValueObjects\Money;

class SubscriptionPricingCalculator
{
    public function calculateFinalPrice(Money $basePrice, Money $discountAmount): Money
    {
        return $basePrice->subtract($discountAmount)->ensureNonNegative();
    }

    public function validateDiscount(Money $basePrice, Money $discountAmount): void
    {
        if ($discountAmount->isNegative()) {
            throw new \InvalidArgumentException('Discount amount cannot be negative');
        }

        if ($discountAmount->toCents() > $basePrice->toCents()) {
            throw new \InvalidArgumentException('Discount amount cannot exceed base price');
        }
    }
}