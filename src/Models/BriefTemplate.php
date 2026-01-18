<?php

namespace App\Models;

class BriefTemplate extends Model
{
    protected $table = 'brief_templates';

    protected $fillable = [
        'site_id',
        'name',
        'description',
        'type',
        'structure',
        'default_fields',
        'is_system',
        'created_by'
    ];

    protected $casts = [
        'default_fields' => 'array',
        'is_system' => 'boolean'
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}