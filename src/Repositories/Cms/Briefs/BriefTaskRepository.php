<?php

namespace App\Repositories\Cms\Briefs;

use App\Framework\Support\Collection;
use App\Models\BriefTask;
use App\Repositories\Repository;

class BriefTaskRepository extends Repository
{
    public function getForBrief(int $briefId, bool $includeCompleted = true): Collection
    {
        $query = BriefTask::where('brief_id', $briefId)
            ->with(['assignee', 'creator']);

        if (!$includeCompleted) {
            $query->where('status', '!=', 'completed');
        }

        return $query->orderBy('status')
            ->orderBy('due_date')
            ->get();
    }

    public function getPending(int $userId): array
    {
        return BriefTask::where('assigned_to', $userId)
            ->where('status', '!=', 'completed')
            ->with(['brief', 'creator'])
            ->orderBy('due_date')
            ->get()
            ->toArray();
    }

    /**
     * Return subtasks for a user in either the owner or reviewer role,
     * optionally scoped to a deadline date range.
     *
     * @param int|null $ownerId Filter by owner_id
     * @param int|null $reviewerId Filter by reviewer_id
     * @param string|null $startDate Deadline >= this date (Y-m-d)
     * @param string|null $endDate Deadline <= this date (Y-m-d)
     */
    public function getForUser(
        ?int    $ownerId,
        ?int    $reviewerId,
        ?string $startDate,
        ?string $endDate,
    ): Collection
    {
        $query = BriefTask::query()
            ->with(['brief', 'creator', 'assignee'])
            ->when($ownerId, fn($q) => $q->where('created_by', $ownerId))
            ->when($reviewerId, fn($q) => $q->where('assigned_to', $reviewerId))
            ->when($startDate, fn($q) => $q->whereDate('due_date', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('due_date', '<=', $endDate))
            ->orderBy('due_date');

        return $query->get();
    }

    protected function getModelClass(): string
    {
        return BriefTask::class;
    }
}