<?php

namespace App\Services\Gdpr\Exporters;

use App\Models\Member;
use App\Models\MemberActivity;
use App\Models\MemberPoint;

final class ActivityExporter implements MemberDataExporter
{
    public function key(): string
    {
        return 'activity';
    }

    public function export(Member $member): array
    {
        $activities = MemberActivity::where('member_id', $member->id)
            ->orderBy('activity_date', 'desc')
            ->get()
            ->map(fn(MemberActivity $a) => [
                'activity_type' => $a->activity_type,
                'entity_type'   => $a->entity_type,
                'entity_id'     => $a->entity_id,
                'points'        => $a->points,
                'activity_date' => $a->activity_date?->format('Y-m-d H:i:s'),
            ])
            ->toArray();

        $points = MemberPoint::where('member_id', $member->id)
            ->orderBy('awarded_at', 'desc')
            ->get()
            ->map(fn(MemberPoint $p) => [
                'points'         => $p->points,
                'reason'         => $p->reason,
                'reference_type' => $p->reference_type,
                'reference_id'   => $p->reference_id,
                'awarded_at'     => $p->awarded_at?->format('Y-m-d H:i:s'),
            ])
            ->toArray();

        return [
            'activities'   => $activities,
            'points_log'   => $points,
            'total_points' => array_sum(array_column($points, 'points')),
        ];
    }
}