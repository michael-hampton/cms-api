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
        ?string $createdFrom = null,
        ?string $createdTo = null,
        ?string $updatedFrom = null,
        ?string $updatedTo = null,
    ): array
    {
        $offset = ($page - 1) * $perPage;

        $applyFilters = function ($query) use ($createdFrom, $createdTo, $updatedFrom, $updatedTo) {
            if (!empty($createdFrom)) {
                $query->where('created_at', '>=', $createdFrom . ' 00:00:00');
            }
            if (!empty($createdTo)) {
                $query->where('created_at', '<=', $createdTo . ' 23:59:59');
            }
            if (!empty($updatedFrom)) {
                $query->where('updated_at', '>=', $updatedFrom . ' 00:00:00');
            }
            if (!empty($updatedTo)) {
                $query->where('updated_at', '<=', $updatedTo . ' 23:59:59');
            }
            return $query;
        };

        $total = $applyFilters(
            MemberNote::where('member_id', $memberId)
                ->where('site_id', $siteId)
                ->whereNull('parent_id')
        )->count();

        $notes = $applyFilters(
            MemberNote::where('member_id', $memberId)
                ->where('site_id', $siteId)
                ->whereNull('parent_id')
        )
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