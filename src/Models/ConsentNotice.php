<?php

namespace App\Models;

class ConsentNotice extends Model
{
    protected $table = 'consent_notices';

    protected $fillable = [
        'site_id',
        'code',
        'name',
        'content',
        'consent_types',
        'display_type',
        'display_rules',
        'is_active'
    ];

    protected $casts = [
        'consent_types' => 'array',
        'display_rules' => 'array',
        'is_active' => 'boolean'
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function memberNotices()
    {
        return $this->hasMany(MemberConsentNotice::class, 'consent_notice_id');
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBySite($query, int $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    public function scopeByDisplayType($query, string $type)
    {
        return $query->where('display_type', $type);
    }
}