<?php

declare(strict_types=1);

namespace App\Repositories\Members;

use App\Framework\Support\Collection;
use App\Models\MemberMerge;
use App\Models\Model;
use App\Repositories\Repository;

/**
 * MemberMergeRepository
 *
 * Persistence for the member_merges audit log.
 * Records every completed merge for compliance and history.
 */
class MemberMergeRepository extends Repository
{
    protected function getModelClass(): string
    {
        return MemberMerge::class;
    }

    /**
     * Record a completed merge in the audit log.
     *
     * @param int        $primaryMemberId  The member whose account survives.
     * @param int        $mergedMemberId   The member whose account was absorbed.
     * @param int        $mergedBy         Admin/agent user ID performing the merge.
     * @param string     $mergedAt         ISO datetime string of merge completion.
     * @param string|null $reason          Optional free-text reason captured at merge time.
     * @param array|null  $metadata        Optional structured context (e.g. match signals used).
     */
    public function recordMerge(
        int     $primaryMemberId,
        int     $mergedMemberId,
        int     $mergedBy,
        string  $mergedAt,
        ?string $reason   = null,
        ?array  $metadata = null,
    ): Model {
        return $this->create([
            'primary_member_id' => $primaryMemberId,
            'merged_member_id'  => $mergedMemberId,
            'merged_by'         => $mergedBy,
            'merged_at'         => $mergedAt,
            'reason'            => $reason,
            'metadata'          => $metadata ? json_encode($metadata) : null,
        ]);
    }

    /**
     * Fetch all merge records where the given member was the primary.
     *
     * @return Collection<MemberMerge>
     */
    public function findByPrimaryMember(int $memberId): Collection
    {
        return $this->model::where('primary_member_id', $memberId)
            ->orderByDesc('merged_at')
            ->get();
    }

    /**
     * Fetch all merge records where the given member was merged (absorbed).
     *
     * @return Collection<MemberMerge>
     */
    public function findByMergedMember(int $memberId): Collection
    {
        return $this->model::where('merged_member_id', $memberId)
            ->orderByDesc('merged_at')
            ->get();
    }

    /**
     * Check whether a merge record already exists for the given pair
     * in either direction.
     */
    public function mergeExistsForPair(int $memberIdA, int $memberIdB): bool
    {
        return $this->model::where(function ($q) use ($memberIdA, $memberIdB) {
            $q->where('primary_member_id', $memberIdA)
                ->where('merged_member_id', $memberIdB);
        })->orWhere(function ($q) use ($memberIdA, $memberIdB) {
            $q->where('primary_member_id', $memberIdB)
                ->where('merged_member_id', $memberIdA);
        })->exists();
    }
}