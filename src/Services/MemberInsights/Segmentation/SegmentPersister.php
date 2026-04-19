<?php

namespace App\Services\MemberInsights\Segmentation;

use App\Repositories\MemberInsights\MemberSegmentRepository;
use App\Repositories\MemberInsights\SegmentRepository;

/**
 * Persists resolved segment assignments for a member.
 *
 * Behaviour:
 *   - New segment assignment  → insert row with assigned_at = last_seen_at = now
 *   - Existing assignment     → update last_seen_at only (no duplicate rows)
 *
 * This is idempotent: running it twice with the same segments produces
 * the same state.
 */
class SegmentPersister
{
    public function __construct(
        private readonly SegmentRepository       $segmentRepository,
        private readonly MemberSegmentRepository $memberSegmentRepository,
    )
    {
    }

    /**
     * @param string[] $segmentKeys e.g. ['churning', 'lurker']
     */
    public function persist(int $memberId, int $siteId, array $segmentKeys): void
    {
        if (empty($segmentKeys)) {
            return;
        }

        $now = now_datetime();

        $segmentIds = $this->segmentRepository->getActiveIdsByKeys($segmentKeys);

        foreach ($segmentKeys as $key) {
            $segmentId = $segmentIds->get($key);

            if (!is_int($segmentId) && !(is_string($segmentId) && ctype_digit($segmentId))) {
                // Segment key came from resolver but was deactivated between the two
                // queries — safe to skip, the segment is no longer active.
                continue;
            }

            $segmentId = (int)$segmentId;

            $existing = $this->memberSegmentRepository->findForMemberSiteSegment($memberId, $siteId, $segmentId);

            if ($existing !== null) {
                $this->memberSegmentRepository->touchLastSeen($existing, $now);
            } else {
                $this->memberSegmentRepository->createAssignment($memberId, $siteId, $segmentId, $now);
            }
        }
    }
}
