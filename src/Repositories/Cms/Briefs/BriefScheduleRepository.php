<?php

namespace App\Repositories\Cms\Briefs;

use App\Models\BriefSchedule;
use App\Repositories\Repository;

class BriefScheduleRepository extends Repository
{
    public function findForBrief(int $briefId): ?BriefSchedule
    {
        return BriefSchedule::where('source_brief_id', $briefId)->first();
    }

    /**
     * Find all active, non-processing schedules whose next_run_at is <= now.
     * The processing = false guard is the idempotency lock against cron overlap.
     *
     * @return BriefSchedule[]
     */
    public function findDue(?\DateTime $now = null): array
    {
        $now = $now ?? new \DateTime();

        return BriefSchedule::where('active', true)
            ->where('processing', false)
            ->where('next_run_at', '<=', $now->format('Y-m-d H:i:s'))
            ->get()
            ->all();
    }

    /**
     * Atomically mark a schedule as processing to prevent double-execution.
     */
    public function markProcessing(int $scheduleId): bool
    {
        return BriefSchedule::where('id', $scheduleId)
                ->where('processing', false)   // only flip if still false
                ->update(['processing' => true]) > 0;
    }

    public function deactivate(int $scheduleId): void
    {
        $this->update($scheduleId, ['active' => false, 'processing' => false]);
    }

    protected function getModelClass(): string
    {
        return BriefSchedule::class;
    }
}