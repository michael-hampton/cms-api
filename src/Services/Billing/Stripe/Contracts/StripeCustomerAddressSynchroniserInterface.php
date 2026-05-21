<?php

namespace App\Services\Billing\Stripe\Contracts;

use App\DTO\Stripe\BillingAddressData;

/**
 * Synchronises a local billing address to the corresponding Stripe customer.
 *
 * Responsibility:
 *   - Retrieve the current Stripe customer address
 *   - Compare against the local address
 *   - Update Stripe only when a difference is detected
 *
 * Must NOT:
 *   - Create customers
 *   - Decide which address is correct (caller resolves this before calling sync)
 *   - Apply tax rules
 *   - Handle checkout logic
 */
interface StripeCustomerAddressSynchroniserInterface
{
    /**
     * Sync the address to the Stripe customer if any field differs.
     * Silently no-ops when the address is already up to date.
     */
    public function sync(string $customerId, BillingAddressData $address): void;
}