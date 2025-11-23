<?php

namespace App\Models;
class MemberPoint extends Model
{
    protected $table = 'member_points';

    protected $fillable = [
        'member_id', 'points', 'reason', 'reference_type',
        'reference_id', 'awarded_at'
    ];

    protected $casts = [
        'points' => 'integer',
        'awarded_at' => 'datetime'
    ];

    public function member($relation = false)
    {
        return $this->belongsTo(Member::class, 'member_id', 'id', $relation);
    }
}