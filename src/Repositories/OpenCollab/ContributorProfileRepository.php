<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\AgeVerificationMethod;
use App\Models\ContributorProfile;
use App\Models\Model;
use App\Repositories\Repository;

/**
 * Data-access layer for oc_contributor_profiles.
 *
 * Thin wrapper so controllers and services never build queries directly,
 * and unit tests can swap in a mock.
 */
class ContributorProfileRepository extends Repository
{
    public function __construct(
        private readonly ContributorPayoutAccountRepository $payoutAccountRepository,
    )
    {
        parent::__construct();
    }

    /**
     * Find a contributor's profile for a specific site.
     */
    public function findByUserAndSite(int $userId, int $siteId): ?ContributorProfile
    {
        return ContributorProfile::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->first();
    }

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
        $profile = $this->findByUserId($userId);

        if ($profile && !empty($profile->payment_method_type) && !empty($profile->payment_details)) {
            return true;
        }

        $account = $this->payoutAccountRepository->findByUserId($userId, 'stripe');

        if (!$account) {
            return false;
        }

        return (bool)($account->payouts_enabled || $account->details_submitted);
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

    /**
     * Create a new profile row for the given user + site combination.
     *
     * @param array<string, mixed> $extra Additional columns to set on creation.
     */
    public function createForUser(int $userId, array $extra = []): Model
    {
        return ContributorProfile::create(array_merge([
            'user_id' => $userId,
            'site_id' => $siteId,
        ], $extra));
    }

    protected function getModelClass(): string
    {
        return ContributorProfile::class;
    }
}
