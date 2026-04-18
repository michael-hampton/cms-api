<?php

namespace App\Repositories\Members;

use App\Framework\Database\Database;
use App\Models\MemberStat;
use App\Repositories\Repository;

class MemberStatRepository extends Repository
{
    private const INCREMENTABLE_COLUMNS = [
        'view_count',
        'like_count',
        'comment_count',
        'order_count',
        'reward_claimed_count',
        'articles_gifted_count',
        'articles_received_count',
    ];

    public function getForMember(int $memberId, int $siteId): ?MemberStat
    {
        return MemberStat::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->first();
    }

    public function increment(int $memberId, int $siteId, string $column): void
    {
        $this->assertIncrementable($column);

        MemberStat::upsert(
            [['member_id' => $memberId, 'site_id' => $siteId, $column => 1]],
            ['member_id', 'site_id'],
            [
                $column => Database::raw("{$column} + VALUES({$column})"),
                'updated_at' => Database::raw('NOW()'),
            ],
        );
    }

    /**
     * Decrement a stat column, clamping at zero so counts never go negative.
     * Uses an upsert so a missing row is treated as zero rather than throwing.
     */
    public function decrement(int $memberId, int $siteId, string $column): void
    {
        $this->assertIncrementable($column);

        // GREATEST prevents the column going below 0.
        MemberStat::upsert(
            [['member_id' => $memberId, 'site_id' => $siteId, $column => 0]],
            ['member_id', 'site_id'],
            [
                $column => Database::raw("GREATEST(0, {$column} - 1)"),
                'updated_at' => Database::raw('NOW()'),
            ],
        );
    }

    public function upsertComputed(int $memberId, int $siteId, array $counts): void
    {
        MemberStat::updateOrCreate(
            ['member_id' => $memberId, 'site_id' => $siteId],
            array_merge($counts, ['last_computed_at' => now_datetime()->toDateTimeString()]),
        );
    }

    protected function getModelClass(): string
    {
        return MemberStat::class;
    }

    private function assertIncrementable(string $column): void
    {
        if (!in_array($column, self::INCREMENTABLE_COLUMNS, true)) {
            throw new \InvalidArgumentException(
                "Column [{$column}] is not incrementable on member_stats.",
            );
        }
    }
}