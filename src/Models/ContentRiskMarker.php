<?php

namespace App\Models;

use App\Enums\OpenCollab\RiskSeverity;
use App\Enums\OpenCollab\RiskSource;
use App\Enums\OpenCollab\RiskStatus;
use App\Enums\OpenCollab\RiskType;

class ContentRiskMarker extends Model
{
    protected $table = 'oc_content_risk_markers';

    protected $fillable = [
        'site_id', 'page_id', 'page_version_id', 'cms_image_id',
        'risk_type', 'source', 'severity', 'confidence', 'status',
        'details', 'created_by_user_id', 'reviewed_by_user_id',
        'reviewed_at', 'resolved_by_user_id', 'resolved_at', 'resolution_notes',
    ];

    protected $casts = [
        'risk_type' => RiskType::class,
        'source' => RiskSource::class,
        'severity' => RiskSeverity::class,
        'status' => RiskStatus::class,
        'details' => 'array',
        'reviewed_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function isOutstanding(): bool
    {
        return $this->status->isOutstanding();
    }
}