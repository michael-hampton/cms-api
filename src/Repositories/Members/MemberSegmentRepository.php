<?php

namespace App\Repositories\Members;

use App\Models\MemberSegment;
use App\Repositories\Repository;

class MemberSegmentRepository extends Repository
{
    public function findForMemberSiteSegment(int $memberId, int $siteId, int $segmentId): ?MemberSegment
    {
        return MemberSegment::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->where('segment_id', $segmentId)
            ->first();
    }

    public function createAssignment(int $memberId, int $siteId, int $segmentId, \DateTimeInterface $timestamp): MemberSegment
    {
        return MemberSegment::create([
            'member_id' => $memberId,
            'site_id' => $siteId,
            'segment_id' => $segmentId,
            'assigned_at' => $timestamp,
            'last_seen_at' => $timestamp,
        ]);
    }

    public function touchLastSeen(MemberSegment $memberSegment, \DateTimeInterface $timestamp): void
    {
        $memberSegment->last_seen_at = $timestamp;
        $memberSegment->save();
    }

    protected function getModelClass(): string
    {
        return MemberSegment::class;
    }
}
