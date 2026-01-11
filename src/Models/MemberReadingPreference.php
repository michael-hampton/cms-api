<?php

namespace App\Models;

class MemberReadingPreference extends Model
{
    protected $table = 'member_reading_preferences';

    protected $fillable = [
        'member_id',
        'site_id',
        'preferred_categories',
        'preferred_tags',
        'preferred_authors',
        'engagement_score'
    ];

    protected $casts = [
        'preferred_categories' => 'array',
        'preferred_tags' => 'array',
        'preferred_authors' => 'array',
        'engagement_score' => 'integer'
    ];

    public function member($relation = false)
    {
        return $this->belongsTo(Member::class, 'member_id', 'id', $relation);
    }
}