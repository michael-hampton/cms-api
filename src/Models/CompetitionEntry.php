<?php

namespace App\Models;

class CompetitionEntry extends Model
{
    protected $table = 'competition_entries';

    protected $fillable = [
        'competition_id', 'member_id',
        'entered_at', 'entry_method',
        'referred_by_member_id', 'metadata',
    ];

    protected $casts = [
        'entered_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function competition($relation = false)
    {
        return $this->belongsTo(Competition::class, 'competition_id', 'id', $relation);
    }

    public function member($relation = false)
    {
        return $this->belongsTo(Member::class, 'member_id', 'id', $relation);
    }

    public function referrer($relation = false)
    {
        return $this->belongsTo(Member::class, 'referred_by_member_id', 'id', $relation);
    }
}