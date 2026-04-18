<?php

namespace App\Models;

class MemberSegment extends Model
{
    protected $table = 'member_segments';
    public $timestamps = false;
    protected $fillable = [
        'member_id',
        'site_id',
        'segment_id',
        'assigned_at',
        'last_seen_at',
    ];
    protected $casts = [
        'assigned_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function segment()
    {
        return $this->belongsTo(Segment::class);
    }
}