<?php

namespace App\Repositories\OpenCollab;

use App\Enums\OpenCollab\EscalationStatus;
use App\Models\ModerationEscalation;
use App\Framework\Support\Collection;

class ModerationEscalationRepository
{
    public function find(int $id): ?ModerationEscalation
    {
        return ModerationEscalation::find($id);
    }

    public function create(array $attributes): ModerationEscalation
    {
        $attributes['created_at'] = $attributes['created_at'] ?? date('Y-m-d H:i:s');

        return ModerationEscalation::create($attributes);
    }

    public function update(int $id, array $attributes): ModerationEscalation
    {
        $escalation = ModerationEscalation::find($id);
        $escalation->update($attributes);
        return $escalation->refresh();
    }

    /**
     * @return Collection<ModerationEscalation>
     */
    public function openForPage(int $siteId, int $pageId): Collection
    {
        $rows = ModerationEscalation::query()
            ->where('site_id', $siteId)
            ->where('page_id', $pageId)
            ->whereNotIn('status', [
                EscalationStatus::Resolved->value,
                EscalationStatus::Closed->value,
                EscalationStatus::Cancelled->value,
            ])
            ->get();

        return Collection::make($rows->all());
    }

    /**
     * @return Collection<ModerationEscalation>
     */
    public function forSite(int $siteId, array $filters = []): Collection
    {
        $query = ModerationEscalation::query()->where('site_id', $siteId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $rows = $query->orderBy('due_at')->orderBy('created_at')->get();

        return Collection::make($rows->all());
    }

    /**
     * Marks open/acknowledged/in_progress escalations past due_at as overdue.
     * Returns the count updated — used by MarkOverdueEscalationsCommand.
     */
    public function markOverdue(): int
    {
        return ModerationEscalation::query()
            ->whereIn('status', [
                EscalationStatus::Open->value,
                EscalationStatus::Acknowledged->value,
                EscalationStatus::InProgress->value,
            ])
            ->where('due_at', '<', date('Y-m-d H:i:s'))
            ->update(['status' => EscalationStatus::Overdue->value]);
    }
}