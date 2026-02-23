<?php

namespace App\Models;

use App\Enums\Newsletters\NewsletterScheduleStatus;

class NewsletterCreationSchedule extends Model
{
    protected $table = 'newsletter_creation_schedules';

    protected $fillable = [
        'newsletter_id',
        'site_id',
        'frequency',
        'day_of_week',
        'day_of_month',
        'time',
        'status',
        'next_run_at',
        'last_run_at',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'day_of_month' => 'integer',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function newsletter()
    {
        return $this->belongsTo(Newsletter::class, 'newsletter_id');
    }

    public function sendSchedule()
    {
        return $this->hasOne(NewsletterSendSchedule::class, 'creation_schedule_id');
    }

    public function isActive(): bool
    {
        return $this->status === NewsletterScheduleStatus::ACTIVE->value;
    }

    public function isPaused(): bool
    {
        return $this->status === NewsletterScheduleStatus::PAUSED->value;
    }

    public function isCancelled(): bool
    {
        return $this->status === NewsletterScheduleStatus::CANCELLED->value;
    }

    public function scopeActive($query)
    {
        return $query->where('status', NewsletterScheduleStatus::ACTIVE->value);
    }

    public function scopeRunnable($query)
    {
        return $query->where('status', NewsletterScheduleStatus::ACTIVE->value)
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            });
    }
}