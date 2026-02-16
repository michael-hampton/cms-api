<?php
// src/Models/Campaign.php

namespace App\Models;

use App\Models\Concerns\TracksCreator;
use DateTime;

class Campaign extends Model
{
    use TracksCreator;

    protected $table = 'campaigns';

    protected $fillable = [
        'site_id',
        'name',
        'slug',
        'description',
        'newsletter_id',
        'is_active',
        'gates_premium_content',
        'status',
        'campaign_type',
        'campaign_id',
        'start_date',
        'end_date',
        'tracking_params',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'gates_premium_content' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'tracking_params' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public static function findBySlug(string $slug, int $siteId): ?self
    {
        return static::where('slug', $slug)
            ->where('site_id', $siteId)
            ->first();
    }

    public static function getActiveCampaigns(int $siteId): array
    {
        $campaigns = static::where('site_id', $siteId)
            ->where('is_active', true)
            ->get();

        $active = [];
        foreach ($campaigns as $campaign) {
            $model = new self($campaign);
            if ($model->isActive()) {
                $active[] = $model;
            }
        }

        return $active;
    }

    public function isActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = new DateTime();

        // Check start date
        if ($this->start_date && $this->start_date > $now) {
            return false;
        }

        // Check end date
        if ($this->end_date && $this->end_date < $now) {
            return false;
        }

        return true;
    }

    public function newsletter($relation = false)
    {
        return $this->belongsTo(Newsletter::class, 'newsletter_id', 'id', $relation);
    }

    public function site($relation = false)
    {
        return $this->belongsTo(Site::class, 'site_id', 'id', $relation);
    }

    public function subscribers($relation = false)
    {
        return $this->hasMany(Subscriber::class, 'campaign_id', 'id', $relation);
    }

    public function hasEnded(): bool
    {
        if (!$this->end_date) {
            return false;
        }

        return $this->end_date < new DateTime();
    }

    public function gatesPremiumContent(): bool
    {
        return $this->gates_premium_content;
    }

    public function getTrackingParam(string $key, $default = null)
    {
        return $this->tracking_params[$key] ?? $default;
    }

    public function setTrackingParam(string $key, $value): void
    {
        $params = $this->tracking_params ?? [];
        $params[$key] = $value;
        $this->tracking_params = $params;
    }

    public function pause(): void
    {
        $this->status = 'paused';
        $this->is_active = false;
        $this->updated_at = new \DateTime();
    }

    public function resume(): void
    {
        $this->status = 'active';
        $this->is_active = true;
        $this->updated_at = new \DateTime();
    }

    public function isValidForSignup(): bool
    {
        return $this->isActive() && !$this->hasEnded();
    }
}