<?php

namespace App\Models;

use DateTime;

class SingleContentAccess extends Model
{
    const CONTENT_TYPE_PAGE = 'page';
    const CONTENT_TYPE_NEWSLETTER = 'newsletter';
    const CONTENT_TYPE_REPORT = 'report';
    protected $table = 'single_content_access';
    protected $fillable = [
        'member_id',
        'site_id',
        'content_type',
        'content_id',
        'access_token',
        'price',
        'currency',
        'payment_id',
        'purchased_at',
        'expires_at',
        'is_active',
        'metadata'
    ];
    protected $casts = [
        'price' => 'float',
        'purchased_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Generate a unique access token
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Find access by token
     */
    public static function findByToken(string $token): ?self
    {
        return static::where('access_token', $token)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Check if access is valid (active and not expired)
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        return true;
    }

    /**
     * Check if access has expired
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false; // No expiration
        }

        return $this->expires_at < new DateTime();
    }

    /**
     * Get the content this access grants access to
     */
    public function getContent()
    {
        switch ($this->content_type) {
            case self::CONTENT_TYPE_PAGE:
                return \App\Models\Page::find($this->content_id);
            case self::CONTENT_TYPE_NEWSLETTER:
                return Newsletter::find($this->content_id);
            case self::CONTENT_TYPE_REPORT:
                // Assuming you have a Report model
                //return \App\Models\Report::find($this->content_id);
                return null;
            default:
                return null;
        }
    }

    /**
     * Revoke access
     */
    public function revoke(): bool
    {
        return $this->update(['is_active' => false]);
    }

    /**
     * Get member relationship
     */
    public function member($relation = false)
    {
        return $this->belongsTo(Member::class, 'member_id', 'id', $relation);
    }

    /**
     * Get payment relationship
     */
    public function payment($relation = false)
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'id', $relation);
    }

    /**
     * Get days until expiration
     */
    public function getDaysUntilExpiration(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        if ($this->isExpired()) {
            return 0;
        }

        $now = new DateTime();
        $interval = $now->diff($this->expires_at);

        return $interval->days;
    }

    /**
     * Extend access by days
     */
    public function extend(int $days): bool
    {
        if (!$this->expires_at) {
            // If no expiration, set from now
            $newExpiration = (new DateTime())->modify("+{$days} days");
        } else {
            // Extend from current expiration
            $newExpiration = (clone $this->expires_at)->modify("+{$days} days");
        }

        return $this->update([
            'expires_at' => $newExpiration->format('Y-m-d H:i:s')
        ]);
    }
}