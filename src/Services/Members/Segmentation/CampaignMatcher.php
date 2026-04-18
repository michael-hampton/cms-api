<?php

namespace App\Services\Members\Segmentation;

use App\Framework\Support\Collection;
use App\Models\Campaign;

/**
 * Matches active campaigns to the segments a member belongs to.
 *
 * Returns campaigns ordered by priority descending so callers can
 * apply caps or take-first strategies without sorting themselves.
 *
 * No writes, no side effects.
 */
final class CampaignMatcher
{
    /**
     * @param string[] $segmentKeys e.g. ['churning', 'lurker']
     * @return Collection<Campaign>
     */
    public function match(array $segmentKeys): Collection
    {
        if (empty($segmentKeys)) {
            return new Collection();
        }

        return Campaign::whereHas('segment', fn($q) => $q->whereIn('key', $segmentKeys))
            ->where('is_active', true)
            ->with('segment')
            ->orderByDesc('priority')
            ->get();
    }
}