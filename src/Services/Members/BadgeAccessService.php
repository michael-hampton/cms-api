<?php

namespace App\Services\Members;

use App\Models\Member;
use App\Models\Site;
use App\Repositories\Subscriptions\SubscriptionRepository;

final class BadgeAccessService
{
    public const REQUIRE_ACTIVE_SUBSCRIPTION_SETTING = 'badges_require_active_subscription';

    public function __construct(
        private readonly SubscriptionRepository $subscriptions,
    ) {
    }

    public function badgesRequireActiveSubscription(int $siteId): bool
    {
        $site = Site::find($siteId);

        if (!$site) {
            return false;
        }

        return $this->truthy($site->getSetting(self::REQUIRE_ACTIVE_SUBSCRIPTION_SETTING, false));
    }

    public function canAccessBadges(?Member $member, int $siteId): bool
    {
        if ($member === null) {
            return false;
        }

        if (!$this->badgesRequireActiveSubscription($siteId)) {
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
