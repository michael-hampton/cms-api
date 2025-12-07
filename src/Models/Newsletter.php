<?php

namespace App\Models;

use DateTime;

class Newsletter extends Model
{
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
        'template'
    ];

    protected $casts = [
        'active' => 'boolean',
        'last_sent' => 'datetime',
        'page_filters' => 'array',
        'max_pages' => 'integer'
    ];

    const INTERVAL_DAILY = 'daily';
    const INTERVAL_WEEKLY = 'weekly';
    const INTERVAL_MONTHLY = 'monthly';

    const CONTENT_TYPE_MANUAL = 'manual';
    const CONTENT_TYPE_AUTO_PAGES = 'auto_pages';

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
}