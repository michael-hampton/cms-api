<?php

namespace App\Services\OpenCollab;

use App\DTO\OpenCollab\OnboardingRequirements;
use App\Exceptions\OpenCollab\OnboardingIncompleteException;
use App\Models\ContributorOnboarding;
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
 * start() creates an initial onboarding record when a contributor accepts
 * their invitation. This is idempotent — calling it twice for the same
 * user+site is safe.
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
     * Called when a contributor accepts their invitation.
     * Creates an 'incomplete' onboarding record for the user+site pair.
     * Idempotent — safe to call multiple times.
     */
    public function start(int $userId, int $siteId): void
    {
        $alreadyStarted = ContributorOnboarding::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->exists();

        if ($alreadyStarted) {
            return;
        }

        ContributorOnboarding::create([
            'user_id' => $userId,
            'site_id' => $siteId,
            'status' => 'incomplete',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
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
        return $this->pendingStepsFromRequirements(
            $userId,
            $this->mapSiteToRequirements($site)
        );
    }

    private function mapSiteToRequirements(Site $site): OnboardingRequirements
    {
        return new OnboardingRequirements(
            siteId: (int)$site->id,
            requirePaymentSetup: (bool)($site->require_payment_setup ?? true),
            requireContracts: (bool)($site->require_contracts ?? true),
            requireGuidelines: (bool)($site->require_guidelines_ack ?? true),
            guidelinesVersion: (int)($site->guidelines_version ?? 1),
        );
    }

    private function pendingStepsFromRequirements(int $userId, OnboardingRequirements $req): array
    {
        $pending = [];

        $profile = $this->profileRepository->findByUserId($userId);

        if (!$profile || !$profile->bio) {
            $pending[] = 'profile';
        }

        if ($req->requirePaymentSetup && !$this->profileRepository->isPaymentSetup($userId)) {
            $pending[] = 'payment';
        }

        if ($req->requireContracts) {
            $contract = $this->contractRepository->latestForSite($req->siteId);

            if ($contract && !$this->contractRepository->hasSigned($userId, $contract->id)) {
                $pending[] = 'contract';
            }
        }

        if ($req->requireGuidelines) {
            $ack = $this->guidelinesRepository
                ->latestAcknowledgedVersion($userId, $req->siteId);

            if ($ack < $req->guidelinesVersion) {
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