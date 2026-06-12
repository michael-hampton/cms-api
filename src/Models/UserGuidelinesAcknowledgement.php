<?php

namespace App\Models;

class UserGuidelinesAcknowledgement extends Model
{
    protected $table = 'oc_user_guidelines_acknowledgements';

    protected $fillable = [
        'user_id',
        'site_id',
        'guideline_id',
        'guideline_version',
        'version',
        'acknowledged_at',
        'accepted_at',
        'accepted_ip',
        'accepted_user_agent',
    ];

    protected $casts = [
        'guideline_id' => 'integer',
        'guideline_version' => 'integer',
        'version' => 'integer',
        'acknowledged_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];
}
