<?php

namespace App\Services\Members\Segmentation;

use App\Framework\Support\Collection;
use App\Models\Campaign;
use App\Repositories\Members\CampaignRepository;

/**
 * Matches active campaigns to the segments a member belongs to.
 *
 * Returns campaigns ordered by priority descending so callers can
 * apply caps or take-first strategies without sorting themselves.
 *
 * No writes, no side effects.
 */
class CampaignMatcher
{
    public function __construct(
        private readonly CampaignRepository $campaignRepository,
    )
    {
    }

    /**
     * @param string[] $segmentKeys e.g. ['churning', 'lurker']
     * @return Collection<Campaign>
     */
    public function match(array $segmentKeys): Collection
    {
        return $this->campaignRepository->matchActiveBySegmentKeys($segmentKeys);
    }
}
