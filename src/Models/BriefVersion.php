<?php

namespace App\Models;

class BriefVersion extends Model
{
    protected $table = 'brief_versions';

    protected $fillable = [
        'brief_id',
        'version_number',
        'title',
        'description',
        'data',
        'created_by',
        'change_summary',
        'created_at'
    ];

    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime'
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