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
        'site_id'
    ];

    protected $casts = [
        'confirmed' => 'boolean',
        'subscribed_at' => 'datetime'
    ];

    public function isConfirmed(): bool
    {
        return $this->confirmed;
    }

    public static function findByEmail(string $email, int $siteId): ?self
    {
        return static::where('email', $email)->where('site_id', $siteId)->first();
    }

    public static function findByConfirmationToken(string $token): ?self
    {
        $result = static::where('confirmation_token', $token)->first();
        return $result ? new self($result) : null;
    }

    public static function findByUnsubscribeToken(string $token): ?self
    {
        $result = static::where('unsubscribe_token', $token)->first();
        return $result ? new self($result) : null;
    }

    public static function getConfirmedEmails(int $siteId): array
    {
        return static::where('confirmed', true)
            ->where('site_id', $siteId)
            ->pluck('email')
            ->toArray();
    }
}