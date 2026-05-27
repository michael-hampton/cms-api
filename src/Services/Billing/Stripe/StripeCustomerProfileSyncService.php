<?php

namespace App\Services\Billing\Stripe;

use App\Models\Member;
use Stripe\StripeClient;

class StripeCustomerProfileSyncService
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly StripeCustomerGateway $customerGateway,
    ) {}

    public function sync(Member $member): void
    {
        $customerId = $this->customerGateway->getOrCreate($member);

        $this->stripe->customers->update($customerId, [
            'email' => $member->email,
            'name' => $member->full_name,
            'metadata' => [
                'member_id' => $member->id,
                'site_id' => $member->site_id,
                'is_active' => (int) (bool) $member->is_active,
            ],
        ]);
    }

    public function markInactive(Member $member): void
    {
        if (empty($member->stripe_customer_id)) {
            return;
        }

        $this->stripe->customers->update($member->stripe_customer_id, [
            'metadata' => [
                'member_id' => $member->id,
                'site_id' => $member->site_id,
                'is_active' => 0,
            ],
        ]);
    }
}
