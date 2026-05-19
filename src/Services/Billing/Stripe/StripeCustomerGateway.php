<?php

namespace App\Services\Billing\Stripe;

use App\Models\Member;
use App\Models\Address;
use App\Enums\Address\AddressType;
use App\Services\Billing\Stripe\Contracts\StripeCustomerGatewayInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * Manages Stripe Customer lifecycle.
 * Extracted from StripePaymentProcessor.
 */
class StripeCustomerGateway implements StripeCustomerGatewayInterface
{
    public function __construct(
        private readonly StripeClient                      $stripe
    )
    {
    }

    /**
     * Retrieve an existing Stripe customer or create a new one.
     * Persists the stripe_customer_id back to the member record.
     */
    public function getOrCreate(Member $member, ?Address $address = null): string
    {
        if (!empty($member->stripe_customer_id)) {
            try {
                $customer = $this->stripe->customers->retrieve(
                    $member->stripe_customer_id
                );
                return $customer->id;
            } catch (ApiErrorException) {
                // Customer no longer exists in Stripe — fall through to create
            }
        }

        $customer = $this->stripe->customers->create([
            'email'    => $member->email,
            'name'     => $member->full_name,
            'address'  => $this->buildAddress($member, $address),
            'metadata' => [
                'member_id' => $member->id,
                'site_id'   => $member->site_id,
            ],
        ]);

        $member->update(['stripe_customer_id' => $customer->id]);

        return $customer->id;
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

    private function buildAddress(Member $member, ?Address $address): array
    {
        if ($address) {
            return [
                'line1'       => $address->address_line_1,
                'line2'       => $address->address_line_2,
                'city'        => $address->city,
                'state'       => $address->state,
                'postal_code' => $address->postcode,
                'country'     => $address->country,
            ];
        }

        return ['country' => $member->country ?? 'GB'];
    }
}