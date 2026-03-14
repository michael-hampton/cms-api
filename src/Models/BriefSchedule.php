<?php

namespace App\Models;

class BriefSchedule extends Model
{
    public const VALID_FREQUENCIES = ['daily', 'weekly', 'monthly', 'custom'];
    public const VALID_END_TYPES = ['never', 'after_occurrences', 'on_date'];
    protected $table = 'brief_schedules';
    protected $fillable = [
        'source_brief_id',
        'frequency',
        'week_days',
        'custom_interval',
        'end_type',
        'end_after_occurrences',
        'end_date',
        'next_run_at',
        'occurrences_count',
        'site_id',
        'active',
        'processing',
        'created_at',
        'updated_at',
    ];
    protected $casts = [
        'week_days' => 'array',
        'active' => 'boolean',
        'processing' => 'boolean',
        'end_date' => 'datetime',
        'next_run_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    protected array $defaults = [
        'occurrences_count' => 0,
        'active' => true,
        'processing' => false,
        'end_type' => 'never',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $schedule) {
            if (!$schedule->source_brief_id) {
                throw new \InvalidArgumentException('BriefSchedule requires source_brief_id.');
            }
            if (!$schedule->frequency) {
                throw new \InvalidArgumentException('BriefSchedule requires frequency.');
            }
            if (!in_array($schedule->frequency, self::VALID_FREQUENCIES, true)) {
                throw new \InvalidArgumentException(
                    'Invalid frequency. Allowed: ' . implode(', ', self::VALID_FREQUENCIES)
                );
            }
        });
    }

    public function sourceBrief(bool $relation = false)
    {
        return $this->belongsTo(Brief::class, 'source_brief_id', 'id', $relation);
    }

    public function isDue(?\DateTime $now = null): bool
    {
        $now = $now ?? new \DateTime();
        return $this->active && !$this->processing && $this->next_run_at <= $now;
    }

    public function hasReachedEndCondition(?\DateTime $now = null): bool
    {
        $now = $now ?? new \DateTime();

        return match ($this->end_type) {
            'after_occurrences' => $this->occurrences_count >= $this->end_after_occurrences,
            'on_date' => $this->end_date && $now >= $this->end_date,
            default => false,
        };
    }
}