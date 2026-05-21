<?php

namespace App\Services\Billing\Stripe\Contracts;

use App\DTO\Stripe\BillingAddressData;
use App\Models\Address;
use App\Models\Member;

/**
 * Resolves the billing address for a given member and optional checkout address.
 *
 * Responsibility:
 *   - Prefer the explicitly supplied checkout address
 *   - Fall back to the member's default billing address
 *   - Map to BillingAddressData DTO
 *
 * Must NOT:
 *   - Call Stripe
 *   - Apply tax rules
 *   - Enforce country presence (validation belongs in checkout/billing validation)
 */
interface BillingAddressResolverInterface
{
    public function resolve(Member $member, ?Address $address = null): BillingAddressData;
}