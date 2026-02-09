<?php

namespace App\Models;

class Collaborator extends Model
{
    protected $table = 'collaborators';

    protected $fillable = [
        'collaboratable_type',
        'collaboratable_id',
        'user_id',
        'role',
        'assigned_at',
        'assigned_by',
        'site_id'
    ];

    protected $casts = [
        'assigned_at' => 'datetime'
    ];

    public function collaboratable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}