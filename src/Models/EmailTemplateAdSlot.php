<?php

namespace App\Models;

use App\Framework\Database\QueryBuilder;

class EmailTemplateAdSlot extends Model
{
    protected $table = 'email_template_ad_slots';

    protected $fillable = [
        'site_id',
        'placement',
        'content_html',
        'is_active',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return $this->expires_at < new \DateTimeImmutable();
    }

    public function scopeBySite(QueryBuilder $query, int $siteId): QueryBuilder
    {
        return $query->where('site_id', $siteId);
    }

    public function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('is_active', true);
    }
}