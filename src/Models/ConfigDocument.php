<?php

namespace App\Models;

class ConfigDocument extends Model
{
    protected $table = 'config_documents';

    protected $fillable = [
        'type',
        'fingerprint',
        'published_at',
        'payload',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'payload' => 'array',
    ];
}