<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\ModerationQueueStatus;
use App\Models\ModerationQueueEntry;
use App\Framework\Support\Collection;

class ModerationQueueRepository
{
    public function find(int $id): ?ModerationQueueEntry
    {
        return ModerationQueueEntry::find($id);
    }

    /**
     * The single open queue entry for a page, if any.
     * Used to decide "refresh existing entry" vs "create new review cycle".
     */
    public function openEntryForPage(int $siteId, int $pageId): ?ModerationQueueEntry
    {
        return ModerationQueueEntry::query()
            ->where('site_id', $siteId)
            ->where('page_id', $pageId)
            ->whereNotIn('status', $this->closedStatuses())
            ->first();
    }

    public function create(array $attributes): ModerationQueueEntry
    {
        return ModerationQueueEntry::create($attributes);
    }

    public function update(int $id, array $attributes): ModerationQueueEntry
    {
        $entry = ModerationQueueEntry::find($id);
        $entry->update($attributes);
        return $entry->refresh();
    }

    /**
     * Deterministic ordering: priority desc, then submitted_at asc (older first on ties).
     */
    public function forSite(int $siteId, array $filters = []): Collection
    {
        $query = ModerationQueueEntry::query()->where('site_id', $siteId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (array_key_exists('assigned_to', $filters)) {
            $query->where('assigned_to_user_id', $filters['assigned_to']);
        }
        if (!empty($filters['unassigned'])) {
            $query->whereNull('assigned_to_user_id');
        }
        if (!empty($filters['submitted_before'])) {
            $query->where('submitted_at', '<', $filters['submitted_before']);
        }
        if (!empty($filters['scheduled_before'])) {
            $query->where('scheduled_publish_at', '<', $filters['scheduled_before']);
        }

        $rows = $query
            ->orderByDesc('priority_score')
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();

        return Collection::make($rows->all());
    }

    /**
     * Atomic claim — only succeeds if currently unassigned and open.
     * Returns the claimed entry, or null if someone else got there first.
     */
    public function claimIfUnassigned(int $id, int $userId): ?ModerationQueueEntry
    {
        $updated = ModerationQueueEntry::query()
            ->where('id', $id)
            ->whereNull('assigned_to_user_id')
            ->whereNotIn('status', $this->closedStatuses())
            ->update([
                'assigned_to_user_id' => $userId,
                'claimed_at' => date('Y-m-d H:i:s'),
                'status' => \App\Enums\OpenCollab\ModerationQueueStatus::Claimed->value,
            ]);

        return $updated > 0 ? ModerationQueueEntry::find($id) : null;
    }

    private function closedStatuses(): array
    {
        return array_map(
            fn(ModerationQueueStatus $s) => $s->value,
            array_filter(ModerationQueueStatus::cases(), fn($s) => $s->isClosed())
        );
    }
}