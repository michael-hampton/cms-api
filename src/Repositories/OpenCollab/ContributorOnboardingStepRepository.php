<?php

namespace App\Repositories\OpenCollab;

use App\Models\ContributorOnboardingStep;
use App\Repositories\Repository;

class ContributorOnboardingStepRepository extends Repository
{
    public function markInProgress(int $userId, int $siteId, string $step): void
    {
        $existing = $this->findStep($userId, $siteId, $step);

        if ($existing && $existing->status === 'complete') {
            return;
        }

        $this->upsertStep($userId, $siteId, $step, 'in_progress', null);
    }

    public function markCompleted(int $userId, int $siteId, string $step): void
    {
        $this->upsertStep($userId, $siteId, $step, 'complete', date('Y-m-d H:i:s'));
    }

    public function markPending(int $userId, int $siteId, string $step): void
    {
        $this->upsertStep($userId, $siteId, $step, 'pending', null);
    }

    public function isCompleted(int $userId, int $siteId, string $step): bool
    {
        $record = $this->findStep($userId, $siteId, $step);

        return $record !== null && $record->status === 'complete';
    }

    private function findStep(int $userId, int $siteId, string $step): ?ContributorOnboardingStep
    {
        /** @var ContributorOnboardingStep|null */
        return ContributorOnboardingStep::where([
            'user_id' => $userId,
            'site_id' => $siteId,
            'step' => $step,
        ])->first();
    }

    private function upsertStep(int $userId, int $siteId, string $step, string $status, ?string $completedAt): void
    {
        ContributorOnboardingStep::updateOrCreate(
            [
                'user_id' => $userId,
                'site_id' => $siteId,
                'step' => $step,
            ],
            [
                'status' => $status,
                'completed_at' => $completedAt,
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        );
    }

    protected function getModelClass(): string
    {
        return ContributorOnboardingStep::class;
    }
}
