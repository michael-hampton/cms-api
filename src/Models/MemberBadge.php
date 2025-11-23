<?php

namespace App\Models;

class MemberBadge extends Model
{
    protected $table = 'member_badges';

    protected $fillable = [
        'member_id', 'badge_id', 'earned_at',
        'criteria_met', 'is_visible'
    ];

    protected $casts = [
        'earned_at' => 'datetime',
        'criteria_met' => 'array',
        'is_visible' => 'boolean'
    ];

    public function member($relation = false)
    {
        return $this->belongsTo(Member::class, 'member_id', 'id', $relation);
    }

    public function badge($relation = false)
    {
        return $this->belongsTo(Badge::class, 'badge_id', 'id', $relation);
    }
}