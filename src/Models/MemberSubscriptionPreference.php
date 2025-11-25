<?php

namespace App\Models;

class MemberSubscriptionPreference extends Model
{
    protected $table = 'member_subscription_preferences';

    protected $fillable = [
        'member_id',
        'site_id',
        'email_notifications',
        'newsletter_frequency',
        'content_types',
        'category_preferences',
        'unsubscribe_token',
        'is_active'
    ];

    protected $casts = [
        'email_notifications' => 'boolean',
        'content_types' => 'array',
        'category_preferences' => 'array',
        'is_active' => 'boolean',
    ];

    public static function findByToken(string $token): ?self
    {
        return self::where('unsubscribe_token', $token)->first();
    }

    public function member($relation = false)
    {
        return $this->belongsTo(Member::class, 'member_id', 'id', $relation);
    }

    public function site($relation = false)
    {
        return $this->belongsTo(Site::class, 'site_id', 'id', $relation);
    }

    public function unsubscribe(): bool
    {
        $this->is_active = false;
        $this->email_notifications = false;
        return $this->save();
    }

    public function resubscribe(): bool
    {
        $this->is_active = true;
        $this->email_notifications = true;
        return $this->save();
    }

    public function hasPreferenceFor(string $contentType): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->content_types === null || count($this->content_types) === 0) {
            return true; // No preferences means all content
        }

        return in_array($contentType, $this->content_types);
    }

    public function wantsCategory(int $categoryId): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->category_preferences === null || count($this->category_preferences) === 0) {
            return true; // No preferences means all categories
        }

        return in_array($categoryId, $this->category_preferences);
    }
}