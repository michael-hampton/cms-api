<?php

namespace App\Repositories\Members;

use App\Models\MemberNote;
use App\Repositories\Repository;

class MemberNoteRepository extends Repository
{
    /**
     * Return all top-level notes for a member (with their replies eager-loaded),
     * newest first.
     *
     * @return array{data: MemberNote[], total: int, per_page: int, current_page: int, last_page: int}
     */
    public function getPaginatedForMember(
        int $memberId,
        int $siteId,
        int $page = 1,
        int $perPage = 20,
    ): array
    {
        $offset = ($page - 1) * $perPage;

        $total = MemberNote::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->whereNull('parent_id')
            ->count();

        $notes = MemberNote::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->whereNull('parent_id')
            ->with('replies')
            ->orderBy('id', 'desc')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return [
            'data' => $notes,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    public function findForMember(int $id, int $memberId, int $siteId): ?MemberNote
    {
        return MemberNote::where('id', $id)
            ->where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->first();
    }

    public function deleteParentAndChildren(MemberNote $note): void
    {
        MemberNote::where('parent_id', $note->id)
            ->get()
            ->each(fn(MemberNote $reply) => $reply->delete());

        $note->delete();
    }

    protected function getModelClass(): string
    {
        return MemberNote::class;
    }
}