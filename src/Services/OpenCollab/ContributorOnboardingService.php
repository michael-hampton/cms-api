<?php

namespace App\Services\OpenCollab;

use App\DTO\OpenCollab\OnboardingRequirements;
use App\Enums\OpenCollab\OnboardingStepStatus;
use App\Exceptions\OpenCollab\OnboardingIncompleteException;
use App\Models\ContributorOnboarding;
use App\Models\Site;
use App\Repositories\OpenCollab\ContractRepository;
use App\Repositories\OpenCollab\ContributorOnboardingRepository;
use App\Repositories\OpenCollab\ContributorOnboardingStepRepository;
use App\Repositories\OpenCollab\ContributorProfileRepository;
use App\Repositories\OpenCollab\GuidelinesRepository;

/**
 * Single authority for contributor onboarding state.
 *
 * Completion rule:
 *   A step is only treated as currently complete when ALL THREE hold:
 *     1. the site requires/allows that step
 *     2. contributor_onboarding_steps row status === 'completed'
 *     3. domain validation still passes at runtime
 *
 *   The step table records explicit workflow completion (user action taken).
 *   Domain validation confirms current compliance (nothing changed since).
 *   Both must pass; either alone is insufficient.
 *
 *   When domain validation fails for a 'completed' row, pendingSteps() treats
 *   the step as pending and invalidates the row so the UI reflects reality.
 *
 * syncStatus() persists a convenience snapshot but MUST NOT be used for
 * permission decisions. Always derive from pendingSteps() / isComplete().
 */
class ContributorOnboardingService
{
    public function __construct(
        private readonly ContributorProfileRepository        $profileRepository,
        private readonly ContributorOnboardingStepRepository $onboardingStepRepository,
        private readonly ContractRepository                  $contractRepository,
        private readonly GuidelinesRepository                $guidelinesRepository,
        private readonly ContributorAgeValidationService     $ageValidationService,
        private readonly ContributorOnboardingRepository $contributorOnboardingRepository
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Called when a contributor accepts their invitation.
     * Creates an 'incomplete' onboarding record for the user+site pair.
     * Idempotent — safe to call multiple times.
     */
    public function start(int $userId, int $siteId): void
    {
        $this->contributorOnboardingRepository->start($userId, $siteId);
    }

    /**
     * Explicitly complete a step for a contributor.
     *
     * Validates that:
     *   - the step is a known key
     *   - the site requires this step
     *   - domain validation passes (i.e. the underlying fact is actually true)
     *
     * Idempotent: calling twice has no harmful side effect.
     *
     * @param array<string, mixed>|null $meta  Optional step-specific metadata
     *                                         (e.g. contract_id, version).
     *
     * @throws \InvalidArgumentException if the step key is unknown or not
     *                                   applicable to this site.
     * @throws \RuntimeException         if domain validation fails — the
     *                                   prerequisite fact has not been recorded
     *                                   before calling completeStep().
     */
    public function completeStep(int $userId, Site $site, string $step, ?array $meta = null): void
    {
        $this->assertStepKnown($step);

        $req = $this->mapSiteToRequirements($site);

        $applicable = $this->applicableStepsFromRequirements($req);

        if (!in_array($step, $applicable, true)) {
            throw new \InvalidArgumentException(
                "Step [{$step}] is not applicable for site [{$site->id}]."
            );
        }

        // Validate the underlying domain fact before marking complete.
        // This ensures completeStep() is called AFTER the domain write, not before.
        $this->assertDomainValidForStep($userId, $site, $step, $req);

        $this->onboardingStepRepository->markCompleted(
            $userId,
            (int) $site->id,
            $step,
            $meta,
        );

        $this->contributorOnboardingRepository->syncStatus(
            $userId,
            $site,
            $this->isComplete($userId, $site),
        );
    }

    /**
     * Mark a step as in-progress for a contributor.
     *
     * Only valid for steps applicable to the site.
     * No-op if the step is already completed.
     *
     * @throws \InvalidArgumentException if the step is not applicable.
     */
    public function markStepInProgress(int $userId, Site $site, string $step): void
    {
        $this->assertStepKnown($step);

        $req = $this->mapSiteToRequirements($site);
        $applicable = $this->applicableStepsFromRequirements($req);

        if (!in_array($step, $applicable, true)) {
            throw new \InvalidArgumentException(
                "Step [{$step}] is not applicable for site [{$site->id}]."
            );
        }

        $this->onboardingStepRepository->markInProgress($userId, (int) $site->id, $step);
    }

    /**
     * Invalidate a single step for a contributor.
     *
     * Used when an upstream change (new contract published, guidelines bumped,
     * payment revoked) means a previously-completed step is no longer valid.
     *
     * Only transitions rows that are currently 'completed' to 'invalidated'.
     * Rows already pending/in_progress/invalidated are left unchanged.
     */
    public function invalidateStep(int $userId, int $siteId, string $step): void
    {
        $this->assertStepKnown($step);
        $this->onboardingStepRepository->markInvalidated($userId, $siteId, $step);
    }

    /**
     * Invalidate a step for every contributor on a site who has it completed.
     *
     * Called when a new contract or guidelines version is published so that
     * all affected contributors must re-complete the step.
     *
     * Returns the number of rows invalidated.
     */
    public function invalidateStepForAllContributors(int $siteId, string $step): int
    {
        $this->assertStepKnown($step);
        return $this->onboardingStepRepository->bulkInvalidateCompletedStep($siteId, $step);
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
     * Each entry: ['step' => string, 'status' => string, 'reason' => string, 'meta' => array]
     *
     * A step appears in this list when ANY of the following hold:
     *   - no step row exists (never started)
     *   - step row status is pending, in_progress, or invalidated
     *   - step row status is completed BUT domain validation now fails
     *
     * When a 'completed' row fails domain validation, the row is lazily
     * invalidated so subsequent calls and the dashboard reflect reality.
     *
     * @return array<int, array{step: string, status: string, reason: string, meta: array<string, mixed>}>
     */
    public function pendingSteps(int $userId, Site $site): array
    {
        return $this->pendingStepsFromRequirements(
            $userId,
            $this->mapSiteToRequirements($site),
            $site,
        );
    }

    /**
     * Returns the names of steps that are required by this site AND have been
     * satisfied (step row = completed AND domain validation passes).
     * Steps not required by the site are excluded entirely.
     *
     * @return array<int, string>
     */
    public function completedSteps(int $userId, Site $site): array
    {
        $req      = $this->mapSiteToRequirements($site);
        $allSteps = $this->applicableStepsFromRequirements($req);

        $pendingStepNames = array_column(
            $this->pendingStepsFromRequirements($userId, $req, $site),
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
        $this->contributorOnboardingRepository->syncStatus(
            $userId,
            $site,
            $this->isComplete($userId, $site),
        );
    }

    public function isComplete(int $userId, Site $site): bool
    {
        return empty($this->pendingSteps($userId, $site));
    }

    // ── Profile convenience (kept for backwards compat) ───────────────────────

    /**
     * @deprecated Use markStepInProgress($userId, $site, 'profile') instead.
     *             Kept to avoid breaking callers that pass siteId only.
     */
    public function markProfileInProgress(int $userId, int $siteId): void
    {
        $this->onboardingStepRepository->markInProgress($userId, $siteId, 'profile');
    }

    /**
     * Validates the profile data and completes the profile step if valid.
     *
     * Returns ['ok' => true, 'status' => [...]] on success.
     * Returns ['ok' => false, 'errors' => [...]] on validation failure.
     */
    public function completeProfileStep(int $userId, Site $site): array
    {
        $profile = $this->profileRepository->findByUserId($userId);

        if (!$profile || !trim((string) $profile->bio)) {
            return [
                'ok'     => false,
                'errors' => ['bio' => ['A short bio is required before you can complete your profile.']],
            ];
        }

        if (mb_strlen(trim((string) $profile->bio)) < 20) {
            return [
                'ok'     => false,
                'errors' => ['bio' => ['Your bio must be at least 20 characters.']],
            ];
        }

        // Delegate to the generic completeStep — validates + persists + syncs.
        try {
            $this->completeStep($userId, $site, 'profile');
        } catch (\RuntimeException $e) {
            return [
                'ok'     => false,
                'errors' => ['profile' => [$e->getMessage()]],
            ];
        }

        return [
            'ok'     => true,
            'status' => $this->statusPayload($userId, $site),
        ];
    }

    /**
     * Validates that payment details have been recorded and completes the
     * payment step if so.
     *
     * Returns ['ok' => true, 'status' => [...]] on success.
     * Returns ['ok' => false, 'errors' => [...]] on validation failure.
     */
    public function completePaymentStep(int $userId, Site $site): array
    {
        if (!$this->profileRepository->isPaymentSetup($userId)) {
            return [
                'ok'     => false,
                'errors' => ['payment' => ['Payment details must be saved before you can continue.']],
            ];
        }

        try {
            $this->completeStep($userId, $site, 'payment');
        } catch (\RuntimeException $e) {
            return [
                'ok'     => false,
                'errors' => ['payment' => [$e->getMessage()]],
            ];
        }

        return [
            'ok'     => true,
            'status' => $this->statusPayload($userId, $site),
        ];
    }

    public function statusPayload(int $userId, Site $site): array
    {
        $pending   = $this->pendingSteps($userId, $site);
        $completed = $this->completedSteps($userId, $site);

        return [
            'isComplete'     => empty($pending),
            'completedCount' => count($completed),
            'totalSteps'     => count($this->applicableStepsFromRequirements($this->mapSiteToRequirements($site))),
            'completedSteps' => $completed,
            'pendingSteps'   => $pending,
        ];
    }

    // ── Private: requirements mapping ────────────────────────────────────────

    private function mapSiteToRequirements(Site $site): OnboardingRequirements
    {
        return new OnboardingRequirements(
            siteId: (int) $site->id,
            requirePaymentSetup: (bool) ($site->require_payment_setup ?? true),
            requireContracts: (bool) ($site->require_contracts ?? true),
            requireGuidelines: (bool) ($site->require_guidelines_ack ?? true),
            guidelinesVersion: (int) ($site->guidelines_version ?? 1),
            requireAgeVerification: (bool) ($site->require_age_verification ?? true),
            minimumContributorAge: (int) ($site->minimum_contributor_age ?? 18),
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

    // ── Private: pending-step derivation ─────────────────────────────────────

    /**
     * Core derivation logic.
     *
     * For each applicable step:
     *   1. Check the step row status.
     *   2. If the row says 'completed', run domain validation.
     *   3. If domain validation fails, lazily invalidate the row and treat
     *      the step as pending.
     *   4. If domain validation passes and row is 'completed', step is done.
     *   5. Any other row status (pending/in_progress/invalidated/missing)
     *      → step is pending.
     *
     * @return array<int, array{step: string, status: string, reason: string, meta: array<string, mixed>}>
     */
    private function pendingStepsFromRequirements(
        int                  $userId,
        OnboardingRequirements $req,
        ?Site                $site = null,
    ): array {
        $pending = [];

        foreach ($this->applicableStepsFromRequirements($req) as $step) {
            $result = $this->evaluateStep($userId, $req, $step);

            if ($result !== null) {
                // Step is pending — lazily invalidate stale 'completed' rows.
                if ($result['stale'] && $site !== null) {
                    $this->onboardingStepRepository->markInvalidated($userId, $req->siteId, $step);
                }

                $pending[] = [
                    'step'   => $result['step'],
                    'status' => $result['status'],
                    'reason' => $result['reason'],
                    'meta'   => $result['meta'],
                ];
            }
        }

        return $pending;
    }

    /**
     * Evaluates a single step.
     *
     * Returns null when the step is complete.
     * Returns an array describing the pending state otherwise.
     * The 'stale' key signals that the step row says 'completed' but
     * domain validation failed — the caller should invalidate the row.
     *
     * @return array{step: string, status: string, reason: string, meta: array, stale: bool}|null
     */
    private function evaluateStep(int $userId, OnboardingRequirements $req, string $step): ?array
    {
        $rowStatus = $this->onboardingStepRepository->getStatus($userId, $req->siteId, $step)
            ?? OnboardingStepStatus::Pending->value;

        $rowIsCompleted = $rowStatus === OnboardingStepStatus::Completed->value;

        // Check domain validation regardless — the row could be stale.
        [$domainPasses, $reason, $meta] = $this->checkDomainForStep($userId, $req, $step);

        if ($rowIsCompleted && $domainPasses) {
            // Both the workflow record and the domain say this step is done.
            return null;
        }

        if ($rowIsCompleted && !$domainPasses) {
            // Row is stale — domain has changed since the step was completed.
            return [
                'step'   => $step,
                'status' => OnboardingStepStatus::Invalidated->value,
                'reason' => $reason,
                'meta'   => $meta,
                'stale'  => true,
            ];
        }

        // Row is not completed (pending / in_progress / invalidated / missing).
        return [
            'step'   => $step,
            'status' => $rowStatus,
            'reason' => $reason,
            'meta'   => $meta,
            'stale'  => false,
        ];
    }

    /**
     * Runs the domain check for a step.
     *
     * Returns [bool $passes, string $reason, array $meta].
     *
     * @return array{0: bool, 1: string, 2: array<string, mixed>}
     */
    private function checkDomainForStep(int $userId, OnboardingRequirements $req, string $step): array
    {
        return match ($step) {
            'profile'          => $this->checkProfileDomain($userId, $req),
            'payment'          => $this->checkPaymentDomain($userId),
            'contract'         => $this->checkContractDomain($userId, $req),
            'guidelines'       => $this->checkGuidelinesDomain($userId, $req),
            'age_verification' => $this->checkAgeDomain($userId, $req),
            default            => [false, "Unknown step [{$step}].", []],
        };
    }

    // ── Domain checks ─────────────────────────────────────────────────────────

    /** @return array{0: bool, 1: string, 2: array} */
    private function checkProfileDomain(int $userId, OnboardingRequirements $req): array
    {
        $profile    = $this->profileRepository->findByUserId($userId);
        $hasBio     = (bool) trim((string) ($profile?->bio ?? ''));

        if (!$profile || !$hasBio) {
            return [
                false,
                'Complete your contributor profile before contributing.',
                ['has_bio' => $hasBio],
            ];
        }

        return [true, '', ['has_bio' => true]];
    }

    /** @return array{0: bool, 1: string, 2: array} */
    private function checkPaymentDomain(int $userId): array
    {
        if (!$this->profileRepository->isPaymentSetup($userId)) {
            return [
                false,
                'Payment details must be set up to receive earnings.',
                [],
            ];
        }

        return [true, '', []];
    }

    /** @return array{0: bool, 1: string, 2: array} */
    private function checkContractDomain(int $userId, OnboardingRequirements $req): array
    {
        $contract = $this->contractRepository->latestPublishedForSite($req->siteId);

        if (!$contract) {
            // No published contract exists — nothing to sign.
            return [true, '', []];
        }

        if (!$this->contractRepository->hasSigned($userId, $contract->id)) {
            return [
                false,
                'A new contributor agreement requires your signature.',
                [
                    'contract_id'      => $contract->id,
                    'contract_version' => $contract->version,
                ],
            ];
        }

        return [
            true,
            '',
            [
                'contract_id'      => $contract->id,
                'contract_version' => $contract->version,
            ],
        ];
    }

    /** @return array{0: bool, 1: string, 2: array} */
    private function checkGuidelinesDomain(int $userId, OnboardingRequirements $req): array
    {
        $ack = $this->guidelinesRepository->latestAcknowledgedVersion($userId, $req->siteId);

        if ($ack < $req->guidelinesVersion) {
            return [
                false,
                'The brand guidelines have been updated and require acknowledgement.',
                [
                    'required_version'     => $req->guidelinesVersion,
                    'acknowledged_version' => $ack,
                ],
            ];
        }

        return [
            true,
            '',
            [
                'required_version'     => $req->guidelinesVersion,
                'acknowledged_version' => $ack,
            ],
        ];
    }

    /** @return array{0: bool, 1: string, 2: array} */
    private function checkAgeDomain(int $userId, OnboardingRequirements $req): array
    {
        $profile   = $this->profileRepository->findByUserId($userId);
        $dobString = $profile?->date_of_birth;
        $dob       = $this->ageValidationService->parseDob($dobString);

        if ($dob === null) {
            return [
                false,
                'You must meet the minimum contributor age requirement before contributing.',
                ['minimum_age' => $req->minimumContributorAge],
            ];
        }

        if (!$this->ageValidationService->meetsMinimumAge($dob, $req->minimumContributorAge)) {
            return [
                false,
                'You must meet the minimum contributor age requirement before contributing.',
                ['minimum_age' => $req->minimumContributorAge],
            ];
        }

        return [true, '', ['minimum_age' => $req->minimumContributorAge]];
    }

    // ── Domain assertion (for completeStep validation) ────────────────────────

    /**
     * Asserts that the domain state for a step is currently valid.
     * Throws \RuntimeException if it is not — meaning completeStep() was called
     * before the prerequisite domain write was performed.
     *
     * @throws \RuntimeException
     */
    private function assertDomainValidForStep(int $userId, Site $site, string $step, OnboardingRequirements $req): void
    {
        [$passes, $reason] = $this->checkDomainForStep($userId, $req, $step);

        if (!$passes) {
            throw new \RuntimeException(
                "Cannot complete step [{$step}]: domain validation failed. {$reason}"
            );
        }
    }

    // ── Guards ────────────────────────────────────────────────────────────────

    private function assertStepKnown(string $step): void
    {
        if (!in_array($step, OnboardingStepStatus::STEPS, true)) {
            throw new \InvalidArgumentException(
                "Unknown onboarding step [{$step}]. Valid steps: "
                . implode(', ', OnboardingStepStatus::STEPS) . '.'
            );
        }
    }
}