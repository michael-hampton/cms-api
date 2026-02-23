<?php

namespace App\Models;

class Competition extends Model
{
    protected $table = 'competitions';

    protected $fillable = [
        'site_id', 'title', 'description', 'slug',
        'status', 'starts_at', 'ends_at',
        'winner_member_id', 'prize_description',
        'entry_type', // 'open', 'badge', 'activity', 'referral', 'raffle', 'sponsored'
        'settings',   // JSON — criteria, external_url, raffle config, etc.
        'is_featured', 'sort_order',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'settings' => 'array',
        'is_featured' => 'boolean',
    ];

    public function site($relation = false)
    {
        return $this->belongsTo(Site::class, 'site_id', 'id', $relation);
    }

    public function winner($relation = false)
    {
        return $this->belongsTo(Member::class, 'winner_member_id', 'id', $relation);
    }

    public function entries($relation = false)
    {
        return $this->hasMany(CompetitionEntry::class, 'competition_id', 'id', $relation);
    }

    public function notifications($relation = false)
    {
        return $this->hasMany(CompetitionNotification::class, 'competition_id', 'id', $relation);
    }

    public function isActive(): bool
    {
        $now = now_datetime();

        if ($this->status !== 'active') {
            return false;
        }

        if ($this->starts_at && $this->starts_at > $now) {
            return false;
        }

        if ($this->ends_at && $this->ends_at < $now) {
            return false;
        }

        return true;
    }

    public function isComingSoon(): bool
    {
        return $this->status === 'active'
            && $this->starts_at
            && $this->starts_at > now_datetime();
    }

    public function hasEnded(): bool
    {
        return $this->status === 'ended'
            || ($this->ends_at && $this->ends_at < now_datetime());
    }

    public function getExternalUrl(): ?string
    {
        return $this->settings['external_url'] ?? null;
    }

    public function requiresActivityTracking(): bool
    {
        foreach ($this->getEntryCriteria() as $criterion) {
            if (in_array($criterion['type'], ['activity', 'return_visits'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the entry_criteria array from settings, normalised to a list.
     * e.g. [['type' => 'badge', 'badge_ids' => [1,2], 'required_count' => 2], ...]
     */
    public function getEntryCriteria(): array
    {
        return $this->settings['entry_criteria'] ?? [];
    }
}