<?php

namespace App\Models;

use App\Enums\Campaigns\CampaignScheduleStatus;
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
        'signup_count',
        'gates_premium_content',
        'status',
        'campaign_type',
        'campaign_id',
        'start_date',
        'end_date',
        'scheduled_at',
        'schedule_status',
        'tracking_params',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
        'segment_id',
        'channel',
        'fallback_channels',
        'template',
        'cooldown_hours',
        'priority',
        'force_channel',
        'push_body',
        'push_icon',
        'push_url',
        'purpose',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'gates_premium_content' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'scheduled_at' => 'datetime',
        'tracking_params' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'channel' => 'string',
        'cooldown_hours' => 'integer',
        'priority' => 'integer',
        'fallback_channels' => 'array',
    ];

    // ── Scheduling ────────────────────────────────────────────────────────────

    /**
     * Returns true when the campaign is waiting for its scheduled_at time.
     */
    public function isScheduled(): bool
    {
        return $this->schedule_status === CampaignScheduleStatus::Scheduled->value
            && $this->scheduled_at !== null;
    }

    /**
     * Returns true when the schedule has been paused before firing.
     */
    public function isSchedulePaused(): bool
    {
        return $this->schedule_status === CampaignScheduleStatus::Paused->value;
    }

    /**
     * Returns true when the campaign is due to be dispatched right now.
     * Used by DispatchScheduledCampaignsCommand.
     */
    public function isDueForDispatch(): bool
    {
        return $this->isScheduled()
            && $this->scheduled_at <= new DateTime();
    }

    // ── Existing lifecycle ────────────────────────────────────────────────────

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

        if ($this->start_date && $this->start_date > $now) {
            return false;
        }

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
        $this->updated_at = new DateTime();
    }

    public function resume(): void
    {
        $this->status = 'active';
        $this->is_active = true;
        $this->updated_at = new DateTime();
    }

    public function isValidForSignup(): bool
    {
        return $this->isActive() && !$this->hasEnded();
    }

    public function segment()
    {
        return $this->belongsTo(Segment::class);
    }

    public function executions()
    {
        return $this->hasMany(CampaignExecution::class);
    }

    public function variants()
    {
        return $this->hasMany(CampaignVariant::class);
    }

    public function deliveries()
    {
        return $this->hasMany(CampaignDelivery::class);
    }

    public function events()
    {
        return $this->hasMany(CampaignEvent::class);
    }
}