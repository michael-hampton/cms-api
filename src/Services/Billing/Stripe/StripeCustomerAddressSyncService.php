<?php

namespace App\Services\Billing\Stripe;

use App\Models\Address;
use App\Models\Member;

class StripeCustomerAddressSyncService
{
    public function __construct(
        private readonly StripeCustomerGateway $customerGateway,
    ) {}

    public function sync(Member $member, ?Address $address = null): void
    {
        $this->customerGateway->getOrCreate($member, $address);
    }
}
