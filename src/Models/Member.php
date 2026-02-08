<?php

namespace App\Models;

use App\Framework\Support\Collection;
use App\Framework\Support\SiteContext;

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
        'password_reset_expires_at',
        'created_at',
        'total_points',
        'activity_stats',
        'stripe_customer_id',
        'communication_preferences',
        'region',
        'timezone',
        'segment'
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
        'created_at' => 'datetime',
        'communication_preferences' => 'array'
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

    public function activeSubscription($relation = false, ?int $siteId = null)
    {
        $siteId = $siteId ?? SiteContext::getId();

        return Subscription::where('member_id', $this->id)
            ->where('status', 'active')
            ->where('site_id', $siteId)
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>', date('Y-m-d H:i:s'));
            })
            ->orderBy('created_at', 'desc') // Most recent first
            ->first();
    }

    public function hasActiveSubscriptionOfType(string $type, ?int $siteId = null): bool
    {
        $siteId = $siteId ?? SiteContext::getId();

        return Subscription::where('member_id', $this->id)
            ->where('site_id', $siteId)
            ->where('status', 'active')
            ->where('type', $type)
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>', date('Y-m-d H:i:s'));
            })
            ->exists();
    }

    public function getSubscriptionWindows(?int $siteId = null): Collection
    {
        $siteId = $siteId ?? SiteContext::getId();

        return SubscriptionWindow::where('member_id', $this->id)
            ->where('site_id', $siteId)
            ->where('type', 'paid') // Only paid subscriptions create retention windows
            ->orderBy('window_start', 'desc')
            ->get();
    }

    public function comments($relation = false)
    {
        return $this->hasMany(Comment::class, 'member_id', 'id', $relation);
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

    public function badges($relation = false)
    {
        return $this->belongsToMany(
            Badge::class,
            'member_badges',
            'member_id',
            'badge_id',
            $relation
        );
    }

    public function activities($relation = false)
    {
        return $this->hasMany(MemberActivity::class, 'member_id', 'id', $relation);
    }

    public function points($relation = false)
    {
        return $this->hasMany(MemberPoint::class, 'member_id', 'id', $relation);
    }

    public function getTotalPointsAttribute(): int
    {
        return $this->points()->sum('points');
    }

    public function getActivityStatsAttribute(): array
    {
        return [
            'comments' => $this->comments()->count(),
            'pages_read' => $this->pageViews()->unique('page_id')->count(),
            'likes' => $this->pageLikes()->count(),
            'orders' => Order::where('user_id', $this->id)->where('status', 'completed')->count(),
            'member_days' => now_datetime()->diffInDays($this->created_at)
        ];
    }

    public function site($relation = false)
    {
        return $this->belongsTo(Site::class, 'site_id', 'id', $relation);
    }

    public function subscriptionPreference($relation = false)
    {
        return $this->hasOne(MemberSubscriptionPreference::class, 'member_id', 'id', $relation);
    }

    public function newsletters($relation = false)
    {
        return $this->hasMany(Subscriber::class, 'email', 'email', $relation)
            ->where('site_id', $this->site_id);
    }

    public function hasActiveSubscription(): bool
    {
        if (!$this->relationLoaded('subscriptionPreference')) {
            $this->load(['subscriptionPreference']);
        }

        $preference = $this->subscriptionPreference;
        return $preference && $preference->is_active;
    }

    public function getSubscriptionPreference(): ?MemberSubscriptionPreference
    {
        if (!$this->relationLoaded('subscriptionPreference')) {
            $this->load(['subscriptionPreference']);
        }

        return $this->subscriptionPreference;
    }

    public function createDefaultSubscriptionPreference(): MemberSubscriptionPreference
    {
        $token = bin2hex(random_bytes(32));

        return MemberSubscriptionPreference::create([
            'member_id' => $this->id,
            'site_id' => $this->site_id,
            'email_notifications' => true,
            'newsletter_frequency' => 'weekly',
            'content_types' => null,
            'category_preferences' => null,
            'unsubscribe_token' => $token,
            'is_active' => true
        ]);
    }

    /**
     * Get communication preference for a specific key
     */
    public function getCommunicationPreference(string $key, $default = true): bool
    {
        $preferences = $this->communication_preferences ?? [];
        return $preferences[$key] ?? $default;
    }

    /**
     * Check if member wants to receive marketing emails
     */
    public function wantsMarketingEmails(): bool
    {
        return $this->getCommunicationPreference('marketing_emails', true);
    }

    /**
     * Check if member wants special offers
     */
    public function wantsSpecialOffers(): bool
    {
        return $this->getCommunicationPreference('special_offers', true);
    }

    /**
     * Check if member wants third party communications
     */
    public function wantsThirdPartyCommunications(): bool
    {
        return $this->getCommunicationPreference('third_party_communications', false);
    }

    /**
     * Always receive transactional emails
     */
    public function wantsTransactionalEmails(): bool
    {
        return true; // Always send transactional emails
    }

    /**
     * Update communication preferences
     */
    public function updateCommunicationPreferences(array $preferences): bool
    {
        $this->communication_preferences = array_merge(
            $this->communication_preferences ?? [],
            $preferences
        );
        return $this->save();
    }

    /**
     * Get member's region (ISO country code)
     */
    public function getRegion(): ?string
    {
        return $this->region;
    }

    /**
     * Set member's region
     */
    public function setRegion(?string $region): void
    {
        $this->region = $region ? strtoupper($region) : null;
        $this->save();
    }

    /**
     * Get member's timezone
     */
    public function getTimezone(): string
    {
        return $this->timezone ?? 'UTC';
    }

    public function isPaid(): bool
    {
        $subscription = $this->activeSubscription(false, $this->site_id);

        return $subscription
            && $subscription->status === 'active'
            && $subscription->type === 'paid';
    }
}