<?php

namespace App\Services\Vouchers\Contracts;

use App\Services\Vouchers\DiscountApplicationResult;
use App\Services\Vouchers\DiscountContext\DiscountContext;

interface DiscountProvider
{
    /**
     * Priority determines application order (lower = earlier).
     * Typical order:
     * 10 = Product offers (base discounts)
     * 20 = Tiered promotions
     * 30 = Vouchers
     * 40 = Reward discounts
     * 50 = Store credit (last)
     */
    public function priority(): int;

    /**
     * Check if this provider applies to the given context
     */
    public function supports(DiscountContext $context): bool;

    /**
     * Apply the discount and return result
     * Returns null if discount cannot be applied
     */
    public function apply(DiscountContext $context): ?DiscountApplicationResult;
}