<?php

namespace App\Services\Billing\Stripe;

use App\DTO\Stripe\BillingAddressData;
use App\Models\Address;
use App\Models\Member;
use App\Services\Billing\Stripe\Contracts\BillingAddressResolverInterface;

/**
 * Resolves the billing address from checkout or member data.
 *
 * Priority:
 *   1. Explicitly supplied checkout address (e.g. new address entered at checkout)
 *   2. Member's resolved billing address (default billing → any billing → shipping → any)
 *   3. Member's country field as a last resort when no address record exists
 *
 * Country enforcement (e.g. requiring a non-null country) is the responsibility
 * of checkout or billing validation — not this class.
 */
class BillingAddressResolver implements BillingAddressResolverInterface
{
    public function resolve(Member $member, ?Address $address = null): BillingAddressData
    {
        if ($address !== null) {
            return $this->fromAddress($address);
        }

        $memberAddress = $member->resolveBillingAddress();

        if ($memberAddress !== null) {
            return $this->fromAddress($memberAddress);
        }

        // No address record available — use the member's country field only.
        // This is the thinnest valid entry for Stripe customer creation.
        return new BillingAddressData(
            line1:   null,
            line2:   null,
            city:    null,
            state:   null,
            postcode: null,
            country:  $member->country ?? null,
        );
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function fromAddress(Address $address): BillingAddressData
    {
        return new BillingAddressData(
            line1:   $address->address_line_1 ?: null,
            line2:   $address->address_line_2 ?: null,
            city:    $address->city           ?: null,
            state:   $address->state          ?: null,
            postcode: $address->postcode      ?: null,
            country:  $address->country       ?: null,
        );
    }
}