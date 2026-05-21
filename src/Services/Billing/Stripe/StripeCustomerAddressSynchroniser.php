<?php

namespace App\Services\Billing\Stripe;

use App\DTO\Stripe\BillingAddressData;
use App\Services\Billing\Stripe\Contracts\StripeCustomerAddressSynchroniserInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Synchronises a local BillingAddressData to a Stripe customer record.
 *
 * Algorithm:
 *   1. Retrieve the current Stripe customer
 *   2. Compare tracked address fields against the local address
 *   3. Call customers->update() only when at least one field differs
 *
 * Comparison uses BillingAddressData::differsWith() which treats empty
 * strings and nulls as equivalent to avoid spurious API calls.
 *
 * Stripe API failures are allowed to propagate — the caller (StripeCustomerGateway)
 * decides whether to treat sync failure as critical or non-critical.
 */
class StripeCustomerAddressSynchroniser implements StripeCustomerAddressSynchroniserInterface
{
    public function __construct(
        private readonly StripeClient $stripe,
    ) {}

    public function sync(string $customerId, BillingAddressData $address): void
    {
        $customer = $this->stripe->customers->retrieve($customerId);

        $existingAddress = $this->extractAddressArray($customer->address);

        if (!$address->differsWith($existingAddress)) {
            return;
        }

        $this->stripe->customers->update($customerId, [
            'address' => $address->toStripe()
        ]);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * Extract a plain PHP array from the Stripe customer address field.
     *
     * Stripe SDK wraps nested objects as StripeObject instances.
     * (array) cast on a StripeObject produces mangled internal property keys,
     * not a flat key-value array. StripeObject::toArray() returns the correct
     * plain representation with the original field names intact.
     */
    private function extractAddressArray(mixed $address): array
    {
        if ($address === null) {
            return [];
        }

        // StripeObject (and subclasses) expose toArray()
        if (is_object($address) && method_exists($address, 'toArray')) {
            return $address->toArray();
        }

        // Plain array (e.g. from a mock returning an array directly)
        if (is_array($address)) {
            return $address;
        }

        return [];
    }
}