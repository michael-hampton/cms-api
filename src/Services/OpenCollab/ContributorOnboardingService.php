<?php

namespace App\Services\OpenCollab;

use App\DTO\OpenCollab\OnboardingRequirements;
use App\Exceptions\OpenCollab\OnboardingIncompleteException;
use App\Models\ContributorOnboarding;
use App\Models\Site;
use App\Repositories\OpenCollab\ContractRepository;
use App\Repositories\OpenCollab\ContributorOnboardingStepRepository;
use App\Repositories\OpenCollab\ContributorProfileRepository;
use App\Repositories\OpenCollab\GuidelinesRepository;

/**
 * Single authority for contributor onboarding state.
 *
 * Onboarding completion is DERIVED at runtime — never stored as source of truth.
 * The DB status column is a convenience snapshot only (used for admin queries).
 *
 * pendingSteps() returns structured data:
 * [
 *   ['step' => 'contract', 'reason' => '...', 'meta' => ['contract_id' => 5, ...]]
 * ]
 *
 * completedSteps() returns only the steps that are *applicable* to this site
 * configuration and have been satisfied — it never reports a step as done if
 * the site doesn't require it.
 *
 * syncStatus() writes a convenience snapshot but MUST NOT be used for
 * permission decisions. Always call pendingSteps() / isComplete() for that.
 */
class ContributorOnboardingService
{
    public function __construct(
        private readonly ContributorProfileRepository    $profileRepository,
        private readonly ContributorOnboardingStepRepository $onboardingStepRepository,
        private readonly ContractRepository              $contractRepository,
        private readonly GuidelinesRepository            $guidelinesRepository,
        private readonly ContributorAgeValidationService $ageValidationService,
    )
    {
    }

    // ── Public API ────────────────────────────────────────────────────────────

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
            'started_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Throws if the contributor has any blocking pending steps.
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
     * Returns structured pending steps for this contributor on this site.
     * Empty array means the contributor is fully compliant.
     *
     * Each entry: ['step' => string, 'reason' => string, 'meta' => array]
     *
     * @return array<int, array{step: string, reason: string, meta: array<string, mixed>}>
     */
    public function pendingSteps(int $userId, Site $site): array
    {
        return $this->pendingStepsFromRequirements(
            $userId,
            $this->mapSiteToRequirements($site),
        );
    }

    /**
     * Returns the names of steps that are required by this site AND have been satisfied.
     * Steps not required by the site are excluded entirely.
     *
     * @return array<int, string>
     */
    public function completedSteps(int $userId, Site $site): array
    {
        $req = $this->mapSiteToRequirements($site);
        $allSteps = $this->applicableStepsFromRequirements($req);

        $pendingStepNames = array_column(
            $this->pendingStepsFromRequirements($userId, $req),
            'step',
        );

        return array_values(array_diff($allSteps, $pendingStepNames));
    }

    /**
     * Persists a convenience status snapshot on the onboarding record.
     * Auto-starts the record if it does not exist (lazy init).
     *
     * This snapshot MUST NOT be used for permission checks — always derive
     * from pendingSteps() at runtime. The snapshot is for admin queries only.
     */
    public function syncStatus(int $userId, Site $site): void
    {
        $record = ContributorOnboarding::where('user_id', $userId)
            ->where('site_id', $site->id)
            ->first();

        if (!$record) {
            $this->start($userId, $site->id);

            $record = ContributorOnboarding::where('user_id', $userId)
                ->where('site_id', $site->id)
                ->first();

            if (!$record) {
                return;
            }
        }

        $isComplete = $this->isComplete($userId, $site);
        $newStatus = $isComplete ? 'complete' : 'incomplete';
        $completedAt = $isComplete ? date('Y-m-d H:i:s') : null;

        // Do NOT use array_filter here — completed_at must be explicitly cleared
        // to null when a contributor becomes incomplete again (e.g. new contract).
        $record->update([
            'status' => $newStatus,
            'completed_at' => $completedAt,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function isComplete(int $userId, Site $site): bool
    {
        return empty($this->pendingSteps($userId, $site));
    }

    public function markProfileInProgress(int $userId, int $siteId): void
    {
        $this->onboardingStepRepository->markInProgress($userId, $siteId, 'profile');
    }

    public function completeProfileStep(int $userId, Site $site): array
    {
        $profile = $this->profileRepository->findByUserId($userId);

        if (!$profile || !trim((string)$profile->bio)) {
            return [
                'ok' => false,
                'errors' => ['bio' => ['A short bio is required before you can complete your profile.']],
            ];
        }

        if (mb_strlen(trim((string)$profile->bio)) < 20) {
            return [
                'ok' => false,
                'errors' => ['bio' => ['Your bio must be at least 20 characters.']],
            ];
        }

        $this->onboardingStepRepository->markCompleted($userId, (int)$site->id, 'profile');
        $this->syncStatus($userId, $site);

        return [
            'ok' => true,
            'status' => $this->statusPayload($userId, $site),
        ];
    }

    public function statusPayload(int $userId, Site $site): array
    {
        $pending = $this->pendingSteps($userId, $site);
        $completed = $this->completedSteps($userId, $site);

        return [
            'isComplete' => empty($pending),
            'completedCount' => count($completed),
            'totalSteps' => count($this->applicableStepsFromRequirements($this->mapSiteToRequirements($site))),
            'completedSteps' => $completed,
            'pendingSteps' => $pending,
        ];
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function mapSiteToRequirements(Site $site): OnboardingRequirements
    {
        return new OnboardingRequirements(
            siteId: (int)$site->id,
            requirePaymentSetup: (bool)($site->require_payment_setup ?? true),
            requireContracts: (bool)($site->require_contracts ?? true),
            requireGuidelines: (bool)($site->require_guidelines_ack ?? true),
            guidelinesVersion: (int)($site->guidelines_version ?? 1),
            requireAgeVerification: (bool)($site->require_age_verification ?? true),
            minimumContributorAge: (int)($site->minimum_contributor_age ?? 18),
        );
    }

    /**
     * Returns only the step names that are applicable to this site configuration.
     * Profile is always required; all others depend on site flags.
     *
     * @return array<int, string>
     */
    private function applicableStepsFromRequirements(OnboardingRequirements $req): array
    {
        $steps = ['profile'];

        if ($req->requirePaymentSetup) {
            $steps[] = 'payment';
        }

        if ($req->requireContracts) {
            $steps[] = 'contract';
        }

        if ($req->requireGuidelines) {
            $steps[] = 'guidelines';
        }

        if ($req->requireAgeVerification) {
            $steps[] = 'age_verification';
        }

        return $steps;
    }

    /**
     * @return array<int, array{step: string, reason: string, meta: array<string, mixed>}>
     */
    private function pendingStepsFromRequirements(int $userId, OnboardingRequirements $req): array
    {
        $pending = [];

        // ── Profile ───────────────────────────────────────────────────────────
        $profile = $this->profileRepository->findByUserId($userId);
        $hasBio = (bool)trim((string)($profile?->bio ?? ''));
        $profileComplete = $this->onboardingStepRepository->isCompleted($userId, $req->siteId, 'profile');

        if (!$profile || !$hasBio || !$profileComplete) {
            $pending[] = [
                'step' => 'profile',
                'reason' => 'Complete your contributor profile before contributing.',
                'meta' => [
                    'has_bio' => $hasBio,
                    'step_completed' => $profileComplete,
                ],
            ];
        }

        // ── Payment ───────────────────────────────────────────────────────────
        if ($req->requirePaymentSetup && !$this->profileRepository->isPaymentSetup($userId)) {
            $pending[] = [
                'step' => 'payment',
                'reason' => 'Payment details must be set up to receive earnings.',
                'meta' => [],
            ];
        }

        // ── Contract ──────────────────────────────────────────────────────────
        if ($req->requireContracts) {
            $contract = $this->contractRepository->latestForSite($req->siteId);

            if ($contract && !$this->contractRepository->hasSigned($userId, $contract->id)) {
                $pending[] = [
                    'step' => 'contract',
                    'reason' => 'A new contributor agreement requires your signature.',
                    'meta' => [
                        'contract_id' => $contract->id,
                        'contract_version' => $contract->version,
                    ],
                ];
            }
        }

        // ── Guidelines ────────────────────────────────────────────────────────
        if ($req->requireGuidelines) {
            $ack = $this->guidelinesRepository->latestAcknowledgedVersion($userId, $req->siteId);

            if ($ack < $req->guidelinesVersion) {
                $pending[] = [
                    'step' => 'guidelines',
                    'reason' => 'The brand guidelines have been updated and require acknowledgement.',
                    'meta' => [
                        'required_version' => $req->guidelinesVersion,
                        'acknowledged_version' => $ack,
                    ],
                ];
            }
        }

        // ── Age verification ──────────────────────────────────────────────────
        if ($req->requireAgeVerification) {
            $profile = $profile ?? $this->profileRepository->findByUserId($userId);

            $dobString = $profile?->date_of_birth;

            $dob = $this->ageValidationService->parseDob($dobString);

            if ($dob === null) {
                $pending[] = [
                    'step' => 'age_verification',
                    'reason' => 'You must meet the minimum contributor age requirement before contributing.',
                    'meta' => ['minimum_age' => $req->minimumContributorAge],
                ];
            } elseif (!$this->ageValidationService->meetsMinimumAge($dob, $req->minimumContributorAge)) {
                $pending[] = [
                    'step' => 'age_verification',
                    'reason' => 'You must meet the minimum contributor age requirement before contributing.',
                    'meta' => ['minimum_age' => $req->minimumContributorAge],
                ];
            }
        }

        return $pending;
    }
}
