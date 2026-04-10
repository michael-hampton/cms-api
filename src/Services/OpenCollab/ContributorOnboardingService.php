<?php

namespace App\Services\OpenCollab;

use App\Exceptions\OpenCollab\OnboardingIncompleteException;
use App\Models\Site;
use App\Repositories\OpenCollab\ContractRepository;
use App\Repositories\OpenCollab\ContributorProfileRepository;
use App\Repositories\OpenCollab\GuidelinesRepository;

/**
 * Single authority for contributor onboarding state.
 *
 * Onboarding steps (strict order):
 *   1. Profile setup        — bio present
 *   2. Payment setup        — stripe token stored
 *   3. Contract signed      — latest site contract signature exists
 *   4. Guidelines accepted  — latest site guidelines acknowledged
 *
 * Whether each step is required is driven by site config flags:
 *   - require_contracts
 *   - require_payment_setup
 *   - require_guidelines_ack
 *
 * Profile is always required.
 */
class ContributorOnboardingService
{
    public function __construct(
        private readonly ContributorProfileRepository $profileRepository,
        private readonly ContractRepository           $contractRepository,
        private readonly GuidelinesRepository         $guidelinesRepository,
    )
    {
    }

    /**
     * Throws if onboarding is not complete.
     * Call this before any publish operation.
     *
     * @throws OnboardingIncompleteException
     */
    public function requireComplete(int $userId, Site $site): void
    {
        $pending = $this->pendingSteps($userId, $site);

        if (!empty($pending)) {
            throw new OnboardingIncompleteException($pending);
        }
    }

    /**
     * Returns the list of pending step names for this contributor on this site.
     * Empty array means onboarding is complete.
     */
    public function pendingSteps(int $userId, Site $site): array
    {
        $pending = [];

        $profile = $this->profileRepository->findByUserId($userId);

        if (!$profile || !$profile->bio) {
            $pending[] = 'profile';
        }

        if (($site->require_payment_setup ?? true) && !$this->profileRepository->isPaymentSetup($userId)) {
            $pending[] = 'payment';
        }

        if (($site->require_contracts ?? true)) {
            $contract = $this->contractRepository->latestForSite($site->id);
            if ($contract && !$this->contractRepository->hasSigned($userId, $contract->id)) {
                $pending[] = 'contract';
            }
        }

        if (($site->require_guidelines_ack ?? true)) {
            $latestVersion = (int)($site->guidelines_version ?? 1);
            $acknowledgedVer = $this->guidelinesRepository->latestAcknowledgedVersion($userId, $site->id);
            if ($acknowledgedVer < $latestVersion) {
                $pending[] = 'guidelines';
            }
        }

        return $pending;
    }

    public function isComplete(int $userId, Site $site): bool
    {
        return empty($this->pendingSteps($userId, $site));
    }
}