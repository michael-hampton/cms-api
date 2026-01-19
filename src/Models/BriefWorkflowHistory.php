<?php

namespace App\Models;

class BriefWorkflowHistory extends Model
{
    protected $table = 'brief_workflow_history';

    protected $fillable = [
        'brief_id',
        'status',
        'changed_by',
        'changed_at',
        'notes'
    ];

    protected $casts = [
        'changed_at' => 'datetime'
    ];

    public function brief()
    {
        return $this->belongsTo(Brief::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}