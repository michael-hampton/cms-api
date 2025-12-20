<?php

namespace App\Models;

class Subscriber extends Model
{
    protected $table = 'subscribers';
    protected $fillable = [
        'email',
        'confirmed',
        'confirmation_token',
        'unsubscribe_token',
        'subscribed_at',
        'unsubscribed_at',
        'site_id',
        'newsletter_id',
        'campaign_id'
    ];

    protected $casts = [
        'confirmed' => 'boolean',
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime'
    ];

    public function isConfirmed(): bool
    {
        return $this->confirmed;
    }

    public static function findByEmail(string $email, int $siteId): ?self
    {
        return static::active()
            ->where('email', $email)
            ->where('site_id', $siteId)
            ->first();
    }


    public static function findByConfirmationToken(string $token): ?self
    {
        return static::active()
            ->where('confirmation_token', $token)
            ->first();
    }

    public static function findByUnsubscribeToken(string $token): ?self
    {
        return static::where('unsubscribe_token', $token)->first();
    }

    public static function getConfirmedEmails(int $siteId): array
    {
        return static::where('confirmed', true)
            ->where('site_id', $siteId)
            ->pluck('email');
    }

    public function campaign($relation = false)
    {
        return $this->belongsTo(Campaign::class, 'campaign_id', 'id', $relation);
    }

    /**
     * Scope: Get only active (not unsubscribed) subscribers
     */
    public function scopeActive($query)
    {
        return $query->whereNull('unsubscribed_at');
    }

    /**
     * Scope: Get only unsubscribed subscribers
     */
    public function scopeUnsubscribed($query)
    {
        return $query->whereNotNull('unsubscribed_at');
    }

    /**
     * Scope: Get confirmed and active subscribers
     */
    public function scopeConfirmedAndActive($query)
    {
        return $query->where('confirmed', true)
            ->whereNull('unsubscribed_at');
    }

    /**
     * Check if subscriber is currently active (subscribed)
     */
    public function isActive(): bool
    {
        return $this->unsubscribed_at === null;
    }

    /**
     * Unsubscribe this subscriber
     */
    public function unsubscribe(): bool
    {
        return $this->update([
            'unsubscribed_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Resubscribe this subscriber
     *
     * @param int|null $campaignId The campaign responsible for resubscription
     */
    public function resubscribe(?int $campaignId = null): bool
    {
        $data = ['unsubscribed_at' => null];

        if ($campaignId !== null) {
            $data['campaign_id'] = $campaignId;
        }

        return $this->update($data);
    }

    /**
     * Find existing subscription record (active or unsubscribed)
     * Used to check for duplicates and enable resubscription
     */
    public static function findExisting(string $email, int $newsletterId, int $siteId): ?self
    {
        return static::where('email', $email)
            ->where('newsletter_id', $newsletterId)
            ->where('site_id', $siteId)
            ->first();
    }
}