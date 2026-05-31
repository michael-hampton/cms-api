<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\OnboardingStepStatus;
use App\Models\ContributorOnboardingStep;
use App\Repositories\Repository;

class ContributorOnboardingStepRepository extends Repository
{
    // ── Single-step writes ────────────────────────────────────────────────────

    public function markInProgress(int $userId, int $siteId, string $step): void
    {
        $existing = $this->findStep($userId, $siteId, $step);

        if ($existing && $existing->status === OnboardingStepStatus::Completed->value) {
            return;
        }

        $this->upsertStep($userId, $siteId, $step, OnboardingStepStatus::InProgress->value, null, null);
    }

    public function markCompleted(int $userId, int $siteId, string $step, ?array $meta = null): void
    {
        $this->upsertStep(
            $userId,
            $siteId,
            $step,
            OnboardingStepStatus::Completed->value,
            date('Y-m-d H:i:s'),
            $meta,
        );
    }

    public function markPending(int $userId, int $siteId, string $step): void
    {
        $this->upsertStep($userId, $siteId, $step, OnboardingStepStatus::Pending->value, null, null);
    }

    /**
     * Mark a step as invalidated — it was previously completed but an upstream
     * change (new contract, new guidelines version, payment revoked) means the
     * contributor must re-complete it.
     *
     * Only rows that are currently 'completed' are transitioned to 'invalidated'.
     * Rows already pending/in_progress/invalidated are left unchanged.
     */
    public function markInvalidated(int $userId, int $siteId, string $step): void
    {
        $existing = $this->findStep($userId, $siteId, $step);

        if (!$existing || $existing->status !== OnboardingStepStatus::Completed->value) {
            return;
        }

        $this->upsertStep($userId, $siteId, $step, OnboardingStepStatus::Invalidated->value, null, null);
    }

    // ── Bulk writes ───────────────────────────────────────────────────────────

    /**
     * Invalidate a specific step for all contributors on a site who have
     * that step marked as completed. Used when a new contract/guidelines
     * version is published.
     *
     * Returns the number of rows affected.
     */
    public function bulkInvalidateCompletedStep(int $siteId, string $step): int
    {
        return (int) ContributorOnboardingStep::where('site_id', $siteId)
            ->where('step', $step)
            ->where('status', OnboardingStepStatus::Completed->value)
            ->update([
                'status'       => OnboardingStepStatus::Invalidated->value,
                'completed_at' => null,
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * Invalidate a specific step for a single contributor.
     * No-op if the row does not exist or is not completed.
     */
    public function invalidateStepForUser(int $userId, int $siteId, string $step): void
    {
        $this->markInvalidated($userId, $siteId, $step);
    }

    // ── Reads ─────────────────────────────────────────────────────────────────

    public function isCompleted(int $userId, int $siteId, string $step): bool
    {
        $record = $this->findStep($userId, $siteId, $step);

        return $record !== null && $record->status === OnboardingStepStatus::Completed->value;
    }

    /**
     * Returns the raw status string for a step, or null if no row exists.
     */
    public function getStatus(int $userId, int $siteId, string $step): ?string
    {
        return $this->findStep($userId, $siteId, $step)?->status;
    }

    /**
     * Returns the full step row, or null if it does not exist.
     */
    public function getStep(int $userId, int $siteId, string $step): ?ContributorOnboardingStep
    {
        return $this->findStep($userId, $siteId, $step);
    }

    /**
     * Returns all step rows for a contributor on a site, keyed by step name.
     *
     * @return array<string, ContributorOnboardingStep>
     */
    public function getAllForUserAndSite(int $userId, int $siteId): array
    {
        return ContributorOnboardingStep::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->get()
            ->keyBy('step')
            ->toArray();
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function findStep(int $userId, int $siteId, string $step): ?ContributorOnboardingStep
    {
        /** @var ContributorOnboardingStep|null */
        return ContributorOnboardingStep::where([
            'user_id' => $userId,
            'site_id' => $siteId,
            'step'    => $step,
        ])->first();
    }

    private function upsertStep(
        int     $userId,
        int     $siteId,
        string  $step,
        string  $status,
        ?string $completedAt,
        ?array  $meta,
    ): void {
        $updateData = [
            'status'       => $status,
            'completed_at' => $completedAt,
            'updated_at'   => date('Y-m-d H:i:s'),
        ];

        if ($meta !== null) {
            $updateData['completed_meta'] = json_encode($meta);
        }

        ContributorOnboardingStep::updateOrCreate(
            [
                'user_id' => $userId,
                'site_id' => $siteId,
                'step'    => $step,
            ],
            $updateData,
        );
    }

    protected function getModelClass(): string
    {
        return ContributorOnboardingStep::class;
    }
}