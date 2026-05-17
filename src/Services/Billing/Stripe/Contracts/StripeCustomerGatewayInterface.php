<?php

namespace App\Services\Billing\Stripe\Contracts;

use App\Models\Address;
use App\Models\Member;

interface StripeCustomerGatewayInterface
{
    public function getOrCreate(Member $member, ?Address $address = null): string;
    public function attachPaymentMethod(
        string $customerId,
        string $paymentMethodId,
    ): void;
}