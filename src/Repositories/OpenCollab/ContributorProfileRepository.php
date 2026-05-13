<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\AgeVerificationMethod;
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

    // ── Age verification ──────────────────────────────────────────────────────

    /**
     * Persists the contributor's date of birth.
     *
     * NOTE: DOB is personal data. The caller is responsible for ensuring
     * this is only stored when the contributor has explicitly provided it.
     * Never pass raw request input here without validation.
     *
     * @param string $dob Format: Y-m-d (validated upstream)
     */
    public function updateDob(int $userId, string $dob): ContributorProfile
    {
        return $this->createOrUpdate($userId, ['date_of_birth' => $dob]);
    }

    /**
     * Records a successful age verification event.
     *
     * Sets age_verified_at, age_verification_method, and minimum_age_confirmed = true.
     * Called by the age verification flow after the contributor has passed checks.
     */
    public function markAgeVerified(int $userId, AgeVerificationMethod $method): ContributorProfile
    {
        return $this->createOrUpdate($userId, [
            'age_verified_at' => date('Y-m-d H:i:s'),
            'age_verification_method' => $method->value,
            'minimum_age_confirmed' => true,
        ]);
    }

    protected function getModelClass(): string
    {
        return ContributorProfile::class;
    }
}