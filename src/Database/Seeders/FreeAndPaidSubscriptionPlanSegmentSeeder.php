<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\PlanSegment;
use App\Models\Segment;
use App\Models\SubscriptionPlan;

/**
 * Links the free_subscription / paid_subscription segments (seeded by
 * SubscriptionSegmentSeeder) to every active subscription plan.
 *
 * These are baseline, offer-derived classifications, so they are linked at
 * a low priority (high number — plan_segment.priority is evaluated
 * ascending, lowest number first) so any more specific segment already
 * assigned to a plan is evaluated before falling back to these.
 *
 * Idempotent: skips a plan/segment pair that's already linked, so it is
 * safe to re-run (e.g. after new plans are created).
 *
 * Run after SubscriptionSegmentSeeder.
 */
class FreeAndPaidSubscriptionPlanSegmentSeeder extends Seeder
{
    private const PRIORITY = [
        'free_subscription' => 900,
        'paid_subscription' => 910,
    ];

    public function run(): void
    {
        $segments = Segment::whereIn('key', array_keys(self::PRIORITY))->get()->keyBy('key');

        if ($segments->isEmpty()) {
            return;
        }

        /** @var int[] $planIds */
        $planIds = SubscriptionPlan::where('is_active', true)->pluck('id');

        foreach ($segments as $key => $segment) {
            foreach ($planIds as $planId) {
                $exists = PlanSegment::where('plan_id', $planId)
                    ->where('segment_id', $segment->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                PlanSegment::create([
                    'plan_id'    => $planId,
                    'segment_id' => $segment->id,
                    'priority'   => self::PRIORITY[$key],
                    'is_active'  => true,
                ]);
            }
        }
    }
}