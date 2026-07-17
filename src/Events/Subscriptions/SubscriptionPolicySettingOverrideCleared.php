<?php

declare(strict_types=1);

namespace App\Events\Subscriptions;

final class SubscriptionPolicySettingOverrideCleared
{
    public function __construct(
        public readonly int $siteId,
        public readonly string $policyClass,
        public readonly string $settingKey,
        public readonly string $reason,
        public readonly int $adminUserId,
    ) {
    }
}