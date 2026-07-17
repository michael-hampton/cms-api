<?php

declare(strict_types=1);

namespace App\Listeners\Subscriptions;

use App\Events\Subscriptions\SubscriptionPolicySettingOverrideCleared;
use App\Events\Subscriptions\SubscriptionPolicySettingOverridden;
use App\Framework\Support\Logger;

/**
 * Audit trail for admin overrides of subscription policy settings. The
 * override rows themselves are the durable record (kept, never deleted,
 * per SubscriptionPolicySettingOverrideRepository::deactivateActive);
 * this listener additionally surfaces the change in the application log
 * stream so it shows up alongside other admin/CRM actions without
 * needing a dedicated audit-log query.
 */
class LogSubscriptionPolicySettingOverrideListener
{
    public function handleOverridden(SubscriptionPolicySettingOverridden $event): void
    {
        Logger::info('Subscription policy setting overridden', [
            'site_id' => $event->override->site_id,
            'policy_class' => $event->override->policy_class,
            'setting_key' => $event->override->setting_key,
            'value' => $event->override->value,
            'reason' => $event->override->reason,
            'admin_user_id' => $event->adminUserId,
        ]);
    }

    public function handleCleared(SubscriptionPolicySettingOverrideCleared $event): void
    {
        Logger::info('Subscription policy setting override cleared', [
            'site_id' => $event->siteId,
            'policy_class' => $event->policyClass,
            'setting_key' => $event->settingKey,
            'reason' => $event->reason,
            'admin_user_id' => $event->adminUserId,
        ]);
    }
}