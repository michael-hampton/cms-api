<?php

namespace App\Repositories\OpenCollab;

use App\Models\ContributorProfile;
use App\Repositories\Repository;

class ContributorProfileRepository extends Repository
{
    public function __construct(
        ?ContributorPayoutAccountRepository $payoutAccountRepository = null,
    )
    {
        $this->payoutAccountRepository = $payoutAccountRepository ?? new ContributorPayoutAccountRepository();
        parent::__construct();
    }

    private readonly ContributorPayoutAccountRepository $payoutAccountRepository;

    public function markPaymentSetup(int $userId, string $stripeToken): void
    {
        $this->createOrUpdate($userId, [
            'payment_method_type' => 'stripe',
            'payment_details' => $stripeToken, // encrypted at model layer
        ]);
    }

    public function createOrUpdate(int $userId, array $data): ContributorProfile
    {
        $profile = $this->findByUserId($userId);

        if ($profile) {
            return $this->update($profile->id, $data);
        }

        return $this->create(array_merge($data, ['user_id' => $userId]));
    }

    public function findByUserId(int $userId): ?ContributorProfile
    {
        /** @var ContributorProfile|null */
        return ContributorProfile::where('user_id', $userId)->first();
    }

    public function isPaymentSetup(int $userId): bool
    {
        $account = $this->payoutAccountRepository->findByUserId($userId, 'stripe');
        return (bool)($account?->payouts_enabled);
    }

    protected function getModelClass(): string
    {
        return ContributorProfile::class;
    }
}