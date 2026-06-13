<?php

namespace App\Models\OpenCollab;

use App\Models\Model;

class ImageSubmissionEvidence extends Model
{
    protected $table = 'oc_image_submission_evidence';

    /**
     * No mass-assignment — all writes go through the repository
     * to enforce the immutability contract.
     */
    protected $fillable = [
        'site_id',
        'cms_image_id',
        'contributor_user_id',
        'contributor_profile_id',
        'cms_image_rights_value',
        'name_submitted',
        'alt_text_submitted',
        'credit_submitted',
        'terms_version_id',
        'attestation_version_id',
        'rights_confirmation',
        'ai_generated',
        'sponsored_content',
        'affiliate_content',
        'request_correlation_id',
        'ip_address',
        'user_agent',
        'submitted_at',
    ];

    protected $casts = [
        'rights_confirmation' => 'boolean',
        'ai_generated'        => 'boolean',
        'sponsored_content'   => 'boolean',
        'affiliate_content'   => 'boolean',
        'submitted_at'        => 'date',
    ];
}