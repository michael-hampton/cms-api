<?php

namespace App\Models;

class LeaderboardEntry extends Model
{
    protected $table = 'leaderboard_entries';

    protected $fillable = [
        'site_id', 'member_id', 'type', 'period',
        'score', 'rank', 'week_start',
    ];

    public static function currentWeekStart(): string
    {
        // Returns Monday of the current week
        return date('Y-m-d', strtotime('monday this week'));
    }

    public function member()
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}