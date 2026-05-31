<?php

namespace App\Repositories\OpenCollab;

use App\Models\ContributorOnboarding;
use App\Models\Model;
use App\Models\Site;
use App\Repositories\Repository;

class ContributorOnboardingRepository extends Repository
{
    public function hasStarted(int $siteId, int $userId): bool
    {
        return ContributorOnboarding::where('user_id', $userId)
            ->where('site_id', $siteId)
            ->exists();
    }

    public function createOnboarding(int $userId, int $siteId): Model
    {
        return ContributorOnboarding::create([
            'user_id' => $userId,
            'site_id' => $siteId,
            'status' => 'incomplete',
            'started_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

    }

    public function syncStatus(int $userId, Site $site, bool $isComplete): void
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

        $newStatus   = $isComplete ? 'complete' : 'incomplete';
        $completedAt = $isComplete ? date('Y-m-d H:i:s') : null;

        $record->update([
            'status'       => $newStatus,
            'completed_at' => $completedAt,
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    public function start(int $userId, int $siteId): void
    {
        $alreadyStarted = $this->hasStarted($siteId, $userId);

        if ($alreadyStarted) {
            return;
        }

        $this->createOnboarding($userId, $siteId);
    }

    protected function getModelClass(): string
    {
        return ContributorOnboarding::class;
    }
}