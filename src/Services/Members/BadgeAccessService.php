<?php

namespace App\Services\Members;

use App\Models\Member;
use App\Models\Site;
use App\Repositories\Subscriptions\SubscriptionRepository;

class BadgeAccessService
{
    public const REQUIRE_ACTIVE_SUBSCRIPTION_SETTING = 'badges_require_active_subscription';

    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
    ) {
    }

    public function badgesRequireActiveSubscription(int|Site $site): bool
    {
        $site = $site instanceof Site ? $site : Site::find($site);

        if (!$site) {
            return false;
        }

        $settings = $site->settings ?? [];

        return $this->truthy($settings[self::REQUIRE_ACTIVE_SUBSCRIPTION_SETTING] ?? false);
    }

    public function canAccessBadges(?Member $member, int|Site $site): bool
    {
        if ($member === null) {
            return false;
        }

        $siteId = $site instanceof Site ? (int) $site->id : $site;

        if ((int) ($member->site_id ?? 0) !== $siteId) {
            return false;
        }

        if (!$this->badgesRequireActiveSubscription($site)) {
            return true;
        }

        return $this->hasActiveSiteSubscription($member, $siteId);
    }

    public function hasActiveSiteSubscription(Member $member, int $siteId): bool
    {
        return $this->subscriptions->getActiveSubscriptionForMember(
            (int) $member->id,
            $siteId,
            false,
        ) !== null;
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}
