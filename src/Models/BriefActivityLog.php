<?php

namespace App\Models;

class BriefActivityLog extends Model
{
    protected $table = 'brief_activity_log';

    protected $fillable = [
        'brief_id', 'user_id', 'action', 'description', 'metadata'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    public function brief()
    {
        return $this->belongsTo(Brief::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}