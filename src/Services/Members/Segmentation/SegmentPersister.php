<?php

namespace App\Services\Members\Segmentation;

use App\Models\MemberSegment;
use App\Models\Segment;

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
final class SegmentPersister
{
    /**
     * @param string[] $segmentKeys e.g. ['churning', 'lurker']
     */
    public function persist(int $memberId, int $siteId, array $segmentKeys): void
    {
        if (empty($segmentKeys)) {
            return;
        }

        $now = now_datetime();

        $segmentIds = Segment::whereIn('key', $segmentKeys)
            ->where('is_active', true)
            ->pluck('id', 'key');

        foreach ($segmentKeys as $key) {
            $segmentId = $segmentIds->get($key);

            if ($segmentId === null) {
                // Segment key came from resolver but was deactivated between the two
                // queries — safe to skip, the segment is no longer active.
                continue;
            }

            $existing = MemberSegment::where('member_id', $memberId)
                ->where('site_id', $siteId)
                ->where('segment_id', $segmentId)
                ->first();

            if ($existing !== null) {
                $existing->last_seen_at = $now;
                $existing->save();
            } else {
                MemberSegment::create([
                    'member_id' => $memberId,
                    'site_id' => $siteId,
                    'segment_id' => $segmentId,
                    'assigned_at' => $now,
                    'last_seen_at' => $now,
                ]);
            }
        }
    }
}