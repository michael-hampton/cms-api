<?php

namespace App\Services\OpenCollab;

use App\Enums\OpenCollab\ContributorOnboardingStatus;
use App\Events\OpenCollab\ContributorOnboardingExpired;
use App\Events\OpenCollab\ContributorOnboardingRestarted;
use App\Models\ContributorOnboarding;
use App\Models\Site;
use App\Repositories\OpenCollab\ContributorOnboardingRepository;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Handles the soft-expiry lifecycle for contributor onboarding.
 *
 * Responsibilities:
 *   - Calculate expiry timestamps from config.
 *   - Find and mark stale incomplete onboarding records as expired.
 *   - Restart expired onboarding flows.
 *
 * Separation note: expiry and restart are distinct from the step-completion
 * and pending-step logic that lives in ContributorOnboardingService. This
 * service operates on the header record (contributor_onboardings) only.
 */
class ContributorOnboardingExpiryService
{
    /** Default expiry window when no config key is present. */
    private const DEFAULT_EXPIRY_DAYS = 60;

    public function __construct(
        private readonly ContributorOnboardingRepository $onboardingRepository,
    ) {}

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Calculate and return the expiry timestamp string for a new or restarted onboarding.
     */
    public function calculateExpiresAt(): string
    {
        $days = (int) (config('open_collab.onboarding.expires_after_days') ?? self::DEFAULT_EXPIRY_DAYS);
        return (new DateTimeImmutable("now +{$days} days", new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    }

    /**
     * Find all incomplete onboarding records past their deadline and mark them expired.
     * Fires ContributorOnboardingExpired for each affected record.
     *
     * Returns the number of records expired.
     */
    public function expireStaleOnboardings(): int
    {
        $now     = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $records = $this->onboardingRepository->findExpiredIncomplete($now);
        $count   = 0;

        foreach ($records as $record) {
            $this->onboardingRepository->markExpired($record, 'onboarding_timeout');
            event(new ContributorOnboardingExpired($record, 'onboarding_timeout'));
            $count++;
        }

        return $count;
    }

    /**
     * Restart an expired (or otherwise incomplete) onboarding record.
     *
     * - Only expired records are eligible. Calling restart on a completed record
     *   is a no-op (the caller should guard against this upstream).
     * - Existing valid completed step rows are NOT touched; they remain in place
     *   and will be re-evaluated by ContributorOnboardingService::pendingSteps().
     * - Fires ContributorOnboardingRestarted.
     *
     * @throws \DomainException if the onboarding record is not found.
     * @throws \LogicException  if the record is already completed.
     */
    public function restart(int $userId, Site $site): ContributorOnboarding
    {
        $record = $this->onboardingRepository->findForUser($userId, (int) $site->id);

        if (!$record) {
            throw new \DomainException(
                "No onboarding record found for user [{$userId}] on site [{$site->id}]."
            );
        }

        if ($record->isComplete()) {
            throw new \LogicException(
                "Cannot restart a completed onboarding for user [{$userId}] on site [{$site->id}]."
            );
        }

        $expiresAt = $this->calculateExpiresAt();
        $this->onboardingRepository->restartOnboarding($record, $expiresAt);

        event(new ContributorOnboardingRestarted($record, $userId));

        return $record->fresh();
    }

    /**
     * Whether the onboarding record for a user/site is in an expired state.
     * Returns false if no record exists (contributor has not yet started).
     */
    public function isExpired(int $userId, int $siteId): bool
    {
        $record = $this->onboardingRepository->findForUser($userId, $siteId);
        return $record !== null && $record->isExpired();
    }
}