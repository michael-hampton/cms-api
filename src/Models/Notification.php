<?php

namespace App\Models;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'member_id',
        'title',
        'body',
        'is_read',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}