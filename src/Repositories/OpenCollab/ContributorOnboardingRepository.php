<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\ContributorOnboardingStatus;
use App\Models\ContributorOnboarding;
use App\Models\Model;
use App\Models\Site;
use App\Repositories\Repository;
use DateTimeInterface;

class ContributorOnboardingRepository extends Repository
{
    // ── Reads ─────────────────────────────────────────────────────────────────

    public function hasStarted(int $siteId, int $userId): bool
    {
        return ContributorOnboarding::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->exists();
    }

    public function findForUser(int $userId, int $siteId): ?ContributorOnboarding
    {
        return ContributorOnboarding::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->first();
    }

    /**
     * Returns all incomplete onboarding records whose expiry deadline has passed.
     * Only pending and in_progress records are eligible — completed records are never expired.
     */
    public function findExpiredIncomplete(DateTimeInterface $now): iterable
    {
        return ContributorOnboarding::query()
            ->whereIn('status', [
                ContributorOnboardingStatus::Pending->value,
                ContributorOnboardingStatus::InProgress->value,
            ])
            ->whereNull('completed_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now->format('Y-m-d H:i:s'))
            ->get();
    }

    // ── Writes ────────────────────────────────────────────────────────────────

    public function createOnboarding(int $userId, int $siteId, ?string $expiresAt = null): Model
    {
        return ContributorOnboarding::create([
            'user_id'          => $userId,
            'site_id'          => $siteId,
            'status'           => ContributorOnboardingStatus::InProgress->value,
            'started_at'       => date('Y-m-d H:i:s'),
            'last_activity_at' => date('Y-m-d H:i:s'),
            'expires_at'       => $expiresAt,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
    }

    public function start(int $userId, int $siteId, ?string $expiresAt = null): void
    {
        if ($this->hasStarted($siteId, $userId)) {
            return;
        }

        $this->createOnboarding($userId, $siteId, $expiresAt);
    }

    /**
     * Record step activity: bumps last_activity_at and, optionally, extends expires_at.
     */
    public function touchActivity(int $userId, int $siteId, ?string $newExpiresAt = null): void
    {
        $record = $this->findForUser($userId, $siteId);

        if (!$record) {
            return;
        }

        $data = [
            'last_activity_at' => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ];

        if ($newExpiresAt !== null) {
            $data['expires_at'] = $newExpiresAt;
        }

        $record->update($data);
    }

    /**
     * Marks a single record as expired.
     */
    public function markExpired(ContributorOnboarding $record, string $reason = 'onboarding_timeout'): void
    {
        $record->update([
            'status'        => ContributorOnboardingStatus::Expired->value,
            'expired_at'    => date('Y-m-d H:i:s'),
            'expiry_reason' => $reason,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Transitions an expired record back to in_progress, resetting expiry fields
     * and setting a new deadline.
     */
    public function restartOnboarding(ContributorOnboarding $record, string $expiresAt): void
    {
        $record->update([
            'status'           => ContributorOnboardingStatus::InProgress->value,
            'started_at'       => date('Y-m-d H:i:s'),
            'last_activity_at' => date('Y-m-d H:i:s'),
            'expires_at'       => $expiresAt,
            'expired_at'       => null,
            'expiry_reason'    => null,
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
    }

    public function syncStatus(int $userId, Site $site, bool $isComplete): void
    {
        $record = $this->findForUser($userId, (int) $site->id);

        if (!$record) {
            $this->start($userId, (int) $site->id);
            $record = $this->findForUser($userId, (int) $site->id);

            if (!$record) {
                return;
            }
        }

        $newStatus   = $isComplete ? ContributorOnboardingStatus::Completed->value
            : ContributorOnboardingStatus::InProgress->value;
        $completedAt = $isComplete ? date('Y-m-d H:i:s') : null;

        $record->update([
            'status'       => $newStatus,
            'completed_at' => $completedAt,
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    protected function getModelClass(): string
    {
        return ContributorOnboarding::class;
    }
}