<?php

namespace App\Models;

class Member extends Model
{
    protected $fillable = [
        'site_id',
        'email',
        'password',
        'first_name',
        'last_name',
        'display_name',
        'is_active',
        'email_verified_at',
        'last_login_at',
        'email_verification_token',
        'email_verification_expires_at',
        'password_reset_token',
        'password_reset_expires_at'
    ];

    protected $hidden = [
        'password',
        'email_verification_token',
        'password_reset_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public $table = 'members';

    public function roles($relation = false)
    {
        return $this->belongsToMany(
            MemberRole::class,
            'member_role_assignments',
            'member_id',
            'role_id',
            true
        )->withPivot('expires_at');
    }

    public function hasRole(string $roleSlug): bool
    {
        if (!$this->relationLoaded('roles')) {
            $this->load(['roles']);
        }

        return $this->roles->contains(function ($role) use ($roleSlug) {
            // Check if role is not expired
            $expiresAt = $role->pivot['expires_at'] ?? null;
            if ($expiresAt && strtotime($expiresAt) < time()) {
                return false;
            }
            return $role->slug === $roleSlug;
        });
    }

    public function hasAnyRole(array $roleSlugs): bool
    {
        foreach ($roleSlugs as $slug) {
            if ($this->hasRole($slug)) {
                return true;
            }
        }
        return false;
    }

    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->attributes['display_name'] ?? $this->getFullNameAttribute();
    }

    public static function findByEmail(string $email, int $siteId): ?self
    {
        return self::where('email', $email)
            ->where('site_id', $siteId)
            ->first();
    }

    public static function findByVerificationToken(string $token, int $siteId): ?self
    {
       return self::where('email_verification_token', $token)
            ->where('site_id', $siteId)
            ->where('email_verification_expires_at', '>', date('Y-m-d H:i:s'))
            ->first();
    }

    public static function findByPasswordResetToken(string $token, int $siteId): ?self
    {
        return self::where('password_reset_token', $token)
            ->where('site_id', $siteId)
            ->where('password_reset_expires_at', '>', date('Y-m-d H:i:s'))
            ->first();
    }

    // Add to Member model:

    public function addresses($relation = false)
    {
        return $this->hasMany(Address::class, 'member_id', 'id', $relation);
    }

    public function defaultShippingAddress($relation = false)
    {
        return $this->hasOne(Address::class, 'member_id', 'id', $relation)
            ->where('is_default', true)
            ->whereIn('type', ['shipping', 'both']);
    }

    public function defaultBillingAddress($relation = false)
    {
        return $this->hasOne(Address::class, 'member_id', 'id', $relation)
            ->where('is_default', true)
            ->whereIn('type', ['billing', 'both']);
    }

    public function subscriptions($relation = false)
    {
        return $this->hasMany(Subscription::class, 'member_id', 'id', $relation);
    }

    public function activeSubscription($relation = false)
    {
        return $this->hasOne(Subscription::class, 'member_id', 'id', $relation)
            ->where('status', 'active');
    }

    public function comments($relation = false)
    {
        return $this->hasMany(Comment::class, 'email', 'email', $relation);
    }

    // Add these methods to App\Models\Member.php

    public function pageViews($relation = false)
    {
        return $this->hasMany(PageView::class, 'member_id', 'id', $relation);
    }

    public function pageLikes($relation = false)
    {
        return $this->hasMany(PageLike::class, 'member_id', 'id', $relation);
    }

    public function likedPages($relation = false)
    {
        return $this->belongsToMany(
            Page::class,
            'page_likes',
            'member_id',
            'page_id',
            $relation
        );
    }

    public function viewedPages($relation = false)
    {
        return $this->belongsToMany(
            Page::class,
            'page_views',
            'member_id',
            'page_id',
            $relation
        );
    }
}