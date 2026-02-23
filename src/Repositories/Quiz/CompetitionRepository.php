<?php

namespace App\Repositories\Quiz;

use App\Framework\Support\Collection;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionNotification;
use App\Models\Model;
use App\Repositories\Repository;

class CompetitionRepository extends Repository
{
    // -------------------------------------------------------------------------
    // Listing / lookup
    // -------------------------------------------------------------------------

    public function getActiveForSite(int $siteId): Collection
    {
        return Competition::where('site_id', $siteId)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('starts_at')
            ->get();
    }

    public function getFeaturedForSite(int $siteId): ?Competition
    {
        return Competition::where('site_id', $siteId)
            ->where('status', 'active')
            ->where('is_featured', true)
            ->orderBy('sort_order')
            ->first();
    }

    // -------------------------------------------------------------------------
    // Entries
    // -------------------------------------------------------------------------

    public function findEntry(int $competitionId, int $memberId): ?CompetitionEntry
    {
        return CompetitionEntry::where('competition_id', $competitionId)
            ->where('member_id', $memberId)
            ->first();
    }

    public function createEntry(array $data): Model
    {
        return CompetitionEntry::create($data);
    }

    public function getEntryCount(int $competitionId): int
    {
        return CompetitionEntry::where('competition_id', $competitionId)->count();
    }

    // -------------------------------------------------------------------------
    // Notifications
    // -------------------------------------------------------------------------

    public function findNotification(int $competitionId, int $memberId): ?CompetitionNotification
    {
        return CompetitionNotification::where('competition_id', $competitionId)
            ->where('member_id', $memberId)
            ->first();
    }

    public function createNotification(array $data): Model
    {
        return CompetitionNotification::create($data);
    }

    public function getMembersToNotify(int $competitionId): Collection
    {
        return CompetitionNotification::where('competition_id', $competitionId)
            ->with(['member'])
            ->get();
    }

    // -------------------------------------------------------------------------
    // Winner
    // -------------------------------------------------------------------------

    public function setWinner(int $competitionId, int $memberId): Model
    {
        $competition = Competition::findOrFail($competitionId);
        $competition->update([
            'winner_member_id' => $memberId,
            'status' => 'ended',
        ]);

        return $competition->fresh();
    }

    protected function getModelClass(): string
    {
        return Competition::class;
    }
}