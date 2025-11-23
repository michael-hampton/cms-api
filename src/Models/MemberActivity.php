<?php

namespace App\Models;

class MemberActivity extends Model
{
    protected $table = 'member_activities';

    protected $fillable = [
        'member_id', 'site_id', 'activity_type', 'entity_type',
        'entity_id', 'metadata', 'points', 'activity_date'
    ];

    protected $casts = [
        'metadata' => 'array',
        'points' => 'integer',
        'activity_date' => 'datetime'
    ];

    public function member($relation = false)
    {
        return $this->belongsTo(Member::class, 'member_id', 'id', $relation);
    }
}