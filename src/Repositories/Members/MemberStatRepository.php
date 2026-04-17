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
        if (!in_array($column, self::INCREMENTABLE_COLUMNS, true)) {
            throw new \InvalidArgumentException(
                "Column [{$column}] is not incrementable on member_stats."
            );
        }

        MemberStat::upsert(
            [
                [
                    'member_id' => $memberId,
                    'site_id' => $siteId,
                    $column => 1
                ]
            ],
            ['member_id', 'site_id'],
            [
                $column => Database::raw("{$column} + VALUES({$column})"),
                'updated_at' => Database::raw('NOW()')
            ]
        );
    }

    public function upsertComputed(int $memberId, int $siteId, array $counts): void
    {
        MemberStat::updateOrCreate(
            ['member_id' => $memberId, 'site_id' => $siteId],
            array_merge($counts, ['last_computed_at' => now_datetime()->toDateTimeString()])
        );
    }

    protected function getModelClass(): string
    {
        return MemberStat::class;
    }
}