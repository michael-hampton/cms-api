<?php

namespace App\Models;

class UserGuidelinesAcknowledgement extends Model
{
    protected $table = 'oc_user_guidelines_acknowledgements';

    protected $fillable = [
        'user_id',
        'site_id',
        'version',
        'acknowledged_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'acknowledged_at' => 'datetime',
    ];
}