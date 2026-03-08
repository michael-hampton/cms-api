<?php

namespace App\Models;

use App\Models\Concerns\HasRegionSetVisibility;
use DateTime;

class Newsletter extends Model
{
    use HasRegionSetVisibility;
    protected $table = 'newsletters';
    protected $fillable = [
        'title',
        'content',
        'interval',
        'last_sent',
        'active',
        'site_id',
        'content_type',
        'page_filters',
        'max_pages',
        'sort_by',
        'sort_order',
        'template',
        'created_at',
        'is_default',
        'slug',
        'is_premium',
        'allows_single_purchase',
        'is_preview',
        'allowed_regions',
        'blocked_regions',
        'has_geographic_restrictions',
        'access_window_start',
        'access_window_end',
        'has_time_window',
        'bundle_id',
        'requires_bundle',
        'layout_id',
        'content_blocks',
        'legacy_content',
        'paused',
        'design_config',
    ];

    protected $casts = [
        'active' => 'boolean',
        'last_sent' => 'datetime',
        'page_filters' => 'array',
        'max_pages' => 'integer',
        'created_at' => 'datetime',
        'is_default' => 'boolean',
        'is_preview' => 'boolean',
        'allowed_regions' => 'array',
        'blocked_regions' => 'array',
        'has_geographic_restrictions' => 'boolean',
        'access_window_start' => 'datetime',
        'access_window_end' => 'datetime',
        'has_time_window' => 'boolean',
        'requires_bundle' => 'boolean',
        'content_blocks' => 'array',
        'paused' => 'boolean',
        'design_config' => 'array',
    ];

    const INTERVAL_DAILY = 'daily';
    const INTERVAL_WEEKLY = 'weekly';
    const INTERVAL_MONTHLY = 'monthly';

    const CONTENT_TYPE_MANUAL = 'manual';
    const CONTENT_TYPE_AUTO_PAGES = 'auto_pages';
    const CONTENT_TYPE_CUSTOM_BLOCKS = 'custom_blocks';

    // ── Relationships ─────────────────────────────────────────────────────

    public function site($relation = false)
    {
        return $this->belongsTo(Site::class, 'site_id', 'id', $relation);
    }

    public function regionSets(bool $relation = false)
    {
        return $this->belongsToMany(
            RegionSet::class,
            'newsletter_region_sets',
            'newsletter_id',
            'region_set_id',
            $relation
        );
    }

    public function sends()
    {
        return $this->hasMany(NewsletterSend::class);
    }

    public function shouldSend(): bool
    {
        if (!$this->active) {
            return false;
        }

        if (!$this->last_sent) {
            return true;
        }

        $now = new DateTime();
        $diff = $this->last_sent->diff($now);

        switch ($this->interval) {
            case self::INTERVAL_DAILY:
                return $diff->days >= 1;
            case self::INTERVAL_WEEKLY:
                return $diff->days >= 7;
            case self::INTERVAL_MONTHLY:
                return $diff->days >= 30;
            default:
                return false;
        }
    }

    public static function getDueNewsletters(int $siteId): array
    {
        $newsletters = static::where('active', true)->where('site_id', $siteId)->get();
        $due = [];

        foreach ($newsletters as $newsletter) {
            $model = new self($newsletter);
            if ($model->shouldSend()) {
                $due[] = $model;
            }
        }

        return $due;
    }

    public function isAutomated()
    {
        return true;
    }

    /**
     * Get the default newsletter for a site
     */
    public static function getDefault(int $siteId): ?self
    {
        return static::where('site_id', $siteId)
            ->where('is_default', true)
            ->where('active', true)
            ->first();
    }

    public function setAsDefault(): bool
    {
        // Unset any other default newsletters for this site
        static::where('site_id', $this->site_id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        // Set this one as default
        return $this->update(['is_default' => true]);
    }

    public function isPremium(): bool
    {
        return $this->is_premium ?? false;
    }

    public function campaigns($relation = false)
    {
        return $this->hasMany(Campaign::class, 'newsletter_id', 'id', $relation);
    }

    /**
     * Check if newsletter has geographic restrictions
     */
    public function hasGeographicRestrictions(): bool
    {
        return $this->has_geographic_restrictions ?? false;
    }

    /**
     * Check if a region is allowed to access this newsletter
     */
    public function isRegionAllowed(?string $region): bool
    {
        if (!$this->hasGeographicRestrictions()) {
            return true; // No restrictions = everyone allowed
        }

        if (!$region) {
            return false; // Region required but not provided
        }

        // Check blocked regions first (blocklist takes precedence)
        if ($this->blocked_regions && in_array($region, $this->blocked_regions)) {
            return false;
        }

        // If allowed_regions is set, region must be in it
        if ($this->allowed_regions) {
            return in_array($region, $this->allowed_regions);
        }

        // No specific allowed regions but has restrictions = use blocklist only
        return true;
    }

    /**
     * Check if newsletter has a time-based access window
     */
    public function hasTimeWindow(): bool
    {
        return $this->has_time_window ?? false;
    }

    /**
     * Check if current time is within access window
     */
    public function isWithinAccessWindow(\DateTime $currentTime, ?Subscription $subscription = null): bool
    {
        if (!$this->hasTimeWindow()) {
            return true; // No time window = always accessible
        }

        // Check start window
        if ($this->access_window_start && $currentTime < $this->access_window_start) {
            return false; // Too early
        }

        // Check end window
        if ($this->access_window_end && $currentTime > $this->access_window_end) {
            return false; // Too late
        }

        return true;
    }

    /**
     * Check if newsletter requires a bundle
     */
    public function requiresBundle(): bool
    {
        return $this->requires_bundle ?? false;
    }

    /**
     * Get the required bundle
     */
    public function bundle()
    {
        if (!$this->bundle_id) {
            return null;
        }

        return SubscriptionBundle::find($this->bundle_id);
    }

    public function hasBlockContent(): bool
    {
        return !empty($this->content_blocks);
    }

    public function isLegacyContent(): bool
    {
        return $this->content_type === self::CONTENT_TYPE_MANUAL
            && empty($this->content_blocks);
    }

    public function getBlocks(): array
    {
        return $this->content_blocks ?? [];
    }
}