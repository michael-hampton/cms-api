<?php

namespace App\Models;


class Segment extends Model
{
    protected $table = 'segments';

    protected $fillable = [
        'key',
        'name',
        'description',
        'category',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function rules()
    {
        return $this->hasMany(SegmentRule::class);
    }

    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }

    public function memberSegments()
    {
        return $this->hasMany(MemberSegment::class);
    }
}