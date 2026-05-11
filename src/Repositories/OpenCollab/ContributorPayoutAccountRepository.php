<?php

namespace App\Repositories\OpenCollab;

use App\Models\ContributorPayoutAccount;
use App\Repositories\Repository;

class ContributorPayoutAccountRepository extends Repository
{
    public function findByUserId(int $userId, string $provider = 'stripe'): ?ContributorPayoutAccount
    {
        /** @var ContributorPayoutAccount|null */
        return ContributorPayoutAccount::where('user_id', $userId)
            ->where('provider', $provider)
            ->first();
    }

    public function findByStripeAccountId(string $stripeAccountId): ?ContributorPayoutAccount
    {
        /** @var ContributorPayoutAccount|null */
        return ContributorPayoutAccount::where('stripe_account_id', $stripeAccountId)->first();
    }

    /**
     * Update provider capability-like fields only (no identifier changes).
     */
    public function updateCapabilities(int $id, array $data): ?ContributorPayoutAccount
    {
        $allowed = [
            'charges_enabled',
            'payouts_enabled',
            'details_submitted',
            'onboarding_completed_at',
            'requirements_due_json',
        ];

        $update = array_intersect_key($data, array_flip($allowed));

        /** @var ContributorPayoutAccount|null */
        return $this->update($id, $update);
    }

    protected function getModelClass(): string
    {
        return ContributorPayoutAccount::class;
    }
}

