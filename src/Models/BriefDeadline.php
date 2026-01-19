<?php

namespace App\Models;

class BriefDeadline extends Model
{
    protected $table = 'brief_deadlines';

    protected $fillable = [
        'brief_id', 'due_date', 'reminder_days', 'notify_collaborators', 'created_by'
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'reminder_days' => 'array',
        'notify_collaborators' => 'boolean'
    ];

    public function brief()
    {
        return $this->belongsTo(Brief::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}