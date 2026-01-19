<?php

namespace App\Models;

class BriefCollaborator extends Model
{
    protected $table = 'brief_collaborators';

    protected $fillable = [
        'brief_id',
        'user_id',
        'role',
        'assigned_at',
        'assigned_by'
    ];

    protected $casts = [
        'assigned_at' => 'datetime'
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