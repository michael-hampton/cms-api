<?php

namespace App\Models;

use App\Models\Concerns\TracksCreator;

class BriefTask extends Model
{
    use TracksCreator;

    protected $table = 'brief_tasks';

    protected $fillable = [
        'brief_id', 'comment_id', 'title', 'description',
        'assigned_to', 'created_by', 'status', 'due_date', 'completed_at'
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime'
    ];

    public function brief()
    {
        return $this->belongsTo(Brief::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}