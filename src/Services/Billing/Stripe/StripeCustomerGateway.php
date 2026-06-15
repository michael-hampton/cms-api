<?php

namespace App\Services\Billing\Stripe;

use App\DTO\Stripe\BillingAddressData;
use App\Models\Address;
use App\Models\Member;
use App\Services\Billing\Stripe\Contracts\BillingAddressResolverInterface;
use App\Services\Billing\Stripe\Contracts\StripeCustomerAddressSynchroniserInterface;
use App\Services\Billing\Stripe\Contracts\StripeCustomerGatewayInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Manages Stripe Customer lifecycle.
 *
 * Orchestrates:
 *   - Resolve billing address via BillingAddressResolver
 *   - Retrieve or create the Stripe customer
 *   - Sync the address to Stripe when the customer already exists
 *   - Persist stripe_customer_id back to the member record
 *   - Attach payment methods and set them as default
 *
 * Does NOT:
 *   - Contain address selection logic (delegated to BillingAddressResolver)
 *   - Contain address comparison logic (delegated to StripeCustomerAddressSynchroniser)
 *   - Apply tax rules
 *   - Build queries or access sessions
 */
class StripeCustomerGateway implements StripeCustomerGatewayInterface
{
    public function __construct(
        private readonly StripeClient                              $stripe,
        private readonly BillingAddressResolverInterface           $addressResolver,
        private readonly StripeCustomerAddressSynchroniserInterface $addressSynchroniser,
    ) {}

    /**
     * Retrieve an existing Stripe customer or create a new one.
     * When the customer already exists the address is synchronised if it has changed.
     * Persists the stripe_customer_id back to the member record on creation.
     */
    public function getOrCreate(Member $member, ?Address $address = null): string
    {
        $billingAddress = $this->addressResolver->resolve($member, $address);

        if (!empty($member->stripe_customer_id)) {
            $existingId = $this->getExistingCustomer($member);

            if ($existingId !== null) {
                $this->addressSynchroniser->sync($existingId, $billingAddress);
                return $existingId;
            }
        }

        return $this->createCustomer($member, $billingAddress);
    }

    /**
     * Attach a payment method to a customer and set it as the default.
     */
    public function attachPaymentMethod(
        string $customerId,
        string $paymentMethodId,
    ): void {
        $this->stripe->paymentMethods->attach(
            $paymentMethodId,
            ['customer' => $customerId]
        );

        $this->stripe->customers->update($customerId, [
            'invoice_settings' => [
                'default_payment_method' => $paymentMethodId,
            ],
        ]);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * Attempt to retrieve an existing Stripe customer.
     * Returns null when the customer no longer exists in Stripe.
     */
    private function getExistingCustomer(Member $member): ?string
    {
        try {
            $customer = $this->stripe->customers->retrieve(
                $member->stripe_customer_id
            );

            return $customer->id;
        } catch (ApiErrorException) {
            // Customer no longer exists in Stripe — fall through to create
            return null;
        }
    }

    /**
     * Create a new Stripe customer and persist the ID back to the member.
     */
    private function createCustomer(Member $member, BillingAddressData $billingAddress): string
    {
        $customer = $this->stripe->customers->create([
            'email'    => $member->email,
            'name'     => $member->full_name,
            'address'  => $billingAddress->toStripe(),
            'metadata' => [
                'member_id' => $member->id,
                'site_id'   => $member->site_id,
            ],
        ]);

        $member->update(['stripe_customer_id' => $customer->id]);

        return $customer->id;
    }
}