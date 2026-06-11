<?php

namespace App\Models;

class ContributorAuthorSyncAudit extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'oc_contributor_author_sync_audits';
    protected $timestamps = false;

    protected $fillable = [
        'contributor_profile_id',
        'author_id',
        'site_id',
        'actor_type',
        'actor_id',
        'event',
        'fields',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'fields' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
}
