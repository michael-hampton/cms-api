<?php

declare(strict_types=1);

namespace App\Events\Subscriptions;

use App\Models\SubscriptionPolicySettingOverride;

final class SubscriptionPolicySettingOverridden
{
    public function __construct(
        public readonly SubscriptionPolicySettingOverride $override,
        public readonly int $adminUserId,
    ) {
    }
}