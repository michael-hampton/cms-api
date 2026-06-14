<?php

namespace App\Models;

class UserTermsAcceptance extends Model
{
    protected $table = 'oc_user_terms_acceptances';

    protected $fillable = [
        'site_id',
        'user_id',
        'terms_version_id',
        'rendered_hash',
        'accepted_at',
        'ip_address',
        'user_agent',
        'accepted_via',
    ];

    protected $casts = [
        'site_id' => 'integer',
        'user_id' => 'integer',
        'terms_version_id' => 'integer',
        'accepted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function termsVersion($relation = false)
    {
        return $this->belongsTo(TermsVersion::class, 'terms_version_id', 'id', $relation);
    }
}
