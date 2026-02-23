<?php

namespace App\Models;

class CompetitionNotification extends Model
{
    protected $table = 'competition_notifications';

    protected $fillable = [
        'competition_id', 'member_id', 'notified_at',
    ];

    protected $casts = [
        'notified_at' => 'datetime',
    ];

    public function competition($relation = false)
    {
        return $this->belongsTo(Competition::class, 'competition_id', 'id', $relation);
    }

    public function member($relation = false)
    {
        return $this->belongsTo(Member::class, 'member_id', 'id', $relation);
    }
}