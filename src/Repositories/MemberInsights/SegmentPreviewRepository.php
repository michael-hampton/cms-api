<?php

namespace App\Repositories\MemberInsights;

use App\Framework\Support\Collection;
use App\Models\Segment;
use App\Models\Subscription;
use App\Repositories\Repository;

/**
 * Provides chunked access to subscriptions that are candidates for a given
 * segment's evaluation. The plan filter narrows iteration to only plans that
 * have the segment assigned, keeping preview scans efficient.
 */
class SegmentPreviewRepository extends Repository
{
    private const CHUNK_SIZE = 200;

    /**
     * Chunk through all active subscriptions whose plan has this segment assigned.
     *
     * @param callable(Collection<Subscription>): void $callback
     */
    public function chunkActiveSubscriptionsForSegment(Segment $segment, callable $callback): void
    {
        // Retrieve plan IDs that have this segment assigned and active.
        $planIds = \App\Models\PlanSegment::where('segment_id', $segment->id)
            ->where('is_active', true)
            ->get()
            ->pluck('plan_id')
            ->all();

        if (empty($planIds)) {
            return;
        }

        Subscription::whereIn('plan_id', $planIds)
            ->where('status', 'active')
            ->chunk(self::CHUNK_SIZE, function ($subscriptions) use ($callback) {
                $callback($subscriptions);
            });
    }

    protected function getModelClass(): string
    {
        return Subscription::class;
    }
}