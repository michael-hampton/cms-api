<?php

namespace App\Services\OpenCollab;

use App\DTO\OpenCollab\OnboardingRequirements;
use App\Enums\OpenCollab\OnboardingStepStatus;
use App\Enums\OpenCollab\StripeConnectAccountStatus;
use App\Exceptions\OpenCollab\OnboardingIncompleteException;
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
 * KYC verification step:
 *   Enabled via the site flag `require_kyc_verification`.
 *   Completion requires BOTH:
 *     - explicit completeStep() call (contributor returns from Stripe onboarding)
 *     - Stripe account status === Enabled (payouts_enabled, details_submitted, no requirements)
 *
 *   The step may be invalidated by the webhook handler when Stripe becomes restricted.
 */
class ContributorOnboardingService
{
    public function __construct(
        private readonly ContributorProfileRepository        $profileRepository,
        private readonly ContributorOnboardingStepRepository $onboardingStepRepository,
        private readonly ContractRepository                  $contractRepository,
        private readonly GuidelinesRepository                $guidelinesRepository,
        private readonly ContributorAgeValidationService     $ageValidationService,
        private readonly ContributorOnboardingRepository     $contributorOnboardingRepository,
        private readonly ?StripeConnectAccountService        $stripeConnectAccountService = null,
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    public function start(int $userId, int $siteId): void
    {
        $this->contributorOnboardingRepository->start($userId, $siteId);
    }

    /**
     * Explicitly complete a step for a contributor.
     *
     * @param array<string, mixed>|null $meta Optional step-specific metadata.
     *
     * @throws \InvalidArgumentException if the step key is unknown or not applicable.
     * @throws \RuntimeException         if domain validation fails.
     */
    public function completeStep(int $userId, Site $site, string $step, ?array $meta = null): void
    {
        $step = $this->normalizeStep($step);
        $this->assertStepKnown($step);

        $req        = $this->mapSiteToRequirements($site);
        $applicable = $this->applicableStepsFromRequirements($req);

        if (!in_array($step, $applicable, true)) {
            throw new \InvalidArgumentException(
                "Step [{$step}] is not applicable for site [{$site->id}]."
            );
        }

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

    public function markStepInProgress(int $userId, Site $site, string $step): void
    {
        $step = $this->normalizeStep($step);
        $this->assertStepKnown($step);

        $req        = $this->mapSiteToRequirements($site);
        $applicable = $this->applicableStepsFromRequirements($req);

        if (!in_array($step, $applicable, true)) {
            throw new \InvalidArgumentException(
                "Step [{$step}] is not applicable for site [{$site->id}]."
            );
        }

        $this->onboardingStepRepository->markInProgress($userId, (int) $site->id, $step);
    }

    public function invalidateStep(int $userId, int $siteId, string $step): void
    {
        $step = $this->normalizeStep($step);
        $this->assertStepKnown($step);
        $this->onboardingStepRepository->markInvalidated($userId, $siteId, $step);
    }

    public function invalidateStepForAllContributors(int $siteId, string $step): int
    {
        $step = $this->normalizeStep($step);
        $this->assertStepKnown($step);
        return $this->onboardingStepRepository->bulkInvalidateCompletedStep($siteId, $step);
    }

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

    // ── Profile convenience ───────────────────────────────────────────────────

    /**
     * @deprecated Use markStepInProgress($userId, $site, 'profile') instead.
     */
    public function markProfileInProgress(int $userId, int $siteId): void
    {
        $this->onboardingStepRepository->markInProgress($userId, $siteId, 'profile');
    }

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

    public function completePaymentStep(int $userId, Site $site): array
    {
        if (!$this->profileRepository->isPaymentSetup($userId)) {
            return [
                'ok'     => false,
                'errors' => ['payment' => ['Payment details must be saved before you can continue.']],
            ];
        }

        try {
            $this->completeStep($userId, $site, 'payment_setup');
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

    public function completeKycVerificationStep(int $userId, Site $site): array
    {
        try {
            $this->completeStep($userId, $site, 'kyc_verification');
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return [
                'ok'     => false,
                'errors' => ['kyc_verification' => [$e->getMessage()]],
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
            siteId:                  (int) $site->id,
            requirePaymentSetup:    (bool) ($site->require_payment_setup ?? true),
            requireContracts:       (bool) ($site->require_contracts ?? true),
            requireGuidelines:      (bool) ($site->require_guidelines_ack ?? true),
            guidelinesVersion:      (int) ($site->guidelines_version ?? 1),
            requireAgeVerification: (bool) ($site->require_age_verification ?? true),
            minimumContributorAge:  (int) ($site->minimum_contributor_age ?? 18),
            requireKycVerification: (bool) ($site->require_kyc_verification ?? false),
        );
    }

    /**
     * Returns only the step names applicable to this site configuration.
     *
     * @return array<int, string>
     */
    private function applicableStepsFromRequirements(OnboardingRequirements $req): array
    {
        $steps = ['profile'];

        if ($req->requirePaymentSetup) {
            $steps[] = 'payment_setup';
        }

        if ($req->requireKycVerification) {
            $steps[] = 'kyc_verification';
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
     * @return array<int, array{step: string, status: string, reason: string, meta: array<string, mixed>}>
     */
    private function pendingStepsFromRequirements(
        int                    $userId,
        OnboardingRequirements $req,
        ?Site                  $site = null,
    ): array {
        $pending = [];

        foreach ($this->applicableStepsFromRequirements($req) as $step) {
            $result = $this->evaluateStep($userId, $req, $step);

            if ($result !== null) {
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
     * @return array{step: string, status: string, reason: string, meta: array, stale: bool}|null
     */
    private function evaluateStep(int $userId, OnboardingRequirements $req, string $step): ?array
    {
        $rowStatus = $this->onboardingStepRepository->getStatus($userId, $req->siteId, $step)
            ?? OnboardingStepStatus::Pending->value;

        $rowIsCompleted = $rowStatus === OnboardingStepStatus::Completed->value;

        [$domainPasses, $reason, $meta] = $this->checkDomainForStep($userId, $req, $step);

        if ($rowIsCompleted && $domainPasses) {
            return null;
        }

        if ($rowIsCompleted && !$domainPasses) {
            return [
                'step'   => $step,
                'status' => OnboardingStepStatus::Invalidated->value,
                'reason' => $reason,
                'meta'   => $meta,
                'stale'  => true,
            ];
        }

        return [
            'step'   => $step,
            'status' => $rowStatus,
            'reason' => $reason,
            'meta'   => $meta,
            'stale'  => false,
        ];
    }

    /**
     * @return array{0: bool, 1: string, 2: array<string, mixed>}
     */
    private function checkDomainForStep(int $userId, OnboardingRequirements $req, string $step): array
    {
        return match ($step) {
            'profile'          => $this->checkProfileDomain($userId, $req),
            'payment_setup'    => $this->checkPaymentDomain($userId),
            'kyc_verification' => $this->checkKycDomain($userId, $req),
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
        $profile = $this->profileRepository->findByUserId($userId);
        $hasBio  = (bool) trim((string) ($profile?->bio ?? ''));

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

    /**
     * KYC domain check.
     *
     * Passes only when the contributor's Stripe Connect account is in the
     * Enabled state (payouts_enabled = true, details_submitted = true, no
     * requirements due). A missing Stripe account always fails.
     *
     * If no StripeConnectAccountService is injected (e.g. in tests that do
     * not care about KYC), the check defaults to pending.
     *
     * @return array{0: bool, 1: string, 2: array}
     */
    private function checkKycDomain(int $userId, OnboardingRequirements $req): array
    {
        if ($this->stripeConnectAccountService === null) {
            return [false, 'KYC verification is required before contributing.', []];
        }

        $status = $this->stripeConnectAccountService->getAccountStatus($userId);

        return match ($status) {
            StripeConnectAccountStatus::Enabled => [
                true,
                '',
                ['stripe_status' => $status->value],
            ],
            StripeConnectAccountStatus::Disconnected => [
                false,
                'A Stripe Connect account is required for KYC verification.',
                ['stripe_status' => $status->value],
            ],
            StripeConnectAccountStatus::Incomplete => [
                false,
                'Please complete your Stripe onboarding to verify your identity.',
                ['stripe_status' => $status->value],
            ],
            StripeConnectAccountStatus::VerificationPending => [
                false,
                'Your Stripe verification is pending. Please check your Stripe dashboard.',
                ['stripe_status' => $status->value],
            ],
            StripeConnectAccountStatus::Restricted => [
                false,
                'Your Stripe account is restricted. Please resolve any outstanding issues.',
                ['stripe_status' => $status->value],
            ],
        };
    }

    /** @return array{0: bool, 1: string, 2: array} */
    private function checkContractDomain(int $userId, OnboardingRequirements $req): array
    {
        $contract = $this->contractRepository->latestPublishedForSite($req->siteId);

        if (!$contract) {
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

    // ── Domain assertion ──────────────────────────────────────────────────────

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

    private function normalizeStep(string $step): string
    {
        return match ($step) {
            'payment' => 'payment_setup',
            default => $step,
        };
    }
}
